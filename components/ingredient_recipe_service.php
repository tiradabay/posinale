<?php
require_once __DIR__ . '/inventory_stock.php';
require_once __DIR__.'/pending_schema.php';
// Shared validation and persistence. Callers own the database transaction.
class InventoryInputError extends RuntimeException {}

function inventory_id($value) {
    if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value) || (float)$value > 2147483647) {
        throw new InventoryInputError('Invalid record ID.', 422);
    }
    return (int)$value;
}

function inventory_decimal($value, $label, $positive = false) {
    try { return inventory_scaled_decimal(inventory_scaled($value, $label, $positive)); }
    catch (InvalidArgumentException $error) { throw new InventoryInputError($error->getMessage(), 422); }
}

function inventory_unit($value) {
    if (!is_string($value) || !in_array($value, ['mg', 'g', 'kg', 'ml', 'l', 'pcs'], true)) {
        throw new InventoryInputError('Choose a supported unit: mg, g, kg, ml, l or pcs.', 422);
    }
    return $value;
}

function inventory_ingredient_input(array $data) {
    $name = $data['name'] ?? null;
    if (!is_string($name) || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
        throw new InventoryInputError('Enter a valid ingredient name.', 422);
    }
    $name = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($name === '' || $length > 100) {
        throw new InventoryInputError('Ingredient name must contain 1 to 100 characters.', 422);
    }
    return [$name, inventory_unit($data['unit'] ?? null),
        inventory_decimal($data['stock'] ?? null, 'Stock'), inventory_decimal($data['reorder'] ?? null, 'Reorder threshold')];
}

function inventory_ingredient_version(array $row) {
    return hash('sha256', json_encode([(string)$row['RawMatID'], $row['RawMaterial'], $row['Unit'],
        (string)$row['StockAvailable'], (string)$row['ReorderingPt']]));
}

function inventory_ingredient($db, $id, $lock = false) {
    $stmt = $db->prepare('SELECT RawMatID, RawMaterial, Unit, StockAvailable, ReorderingPt FROM rawmaterial WHERE RawMatID=?' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) throw new InventoryInputError('Ingredient not found.', 404);
    $row['version'] = inventory_ingredient_version($row);
    return $row;
}

function inventory_save_ingredient($db, array $data, $editing = false) {
    [$name, $unit, $stock, $reorder] = inventory_ingredient_input($data);
    $id = $editing ? inventory_id($data['RawMatID'] ?? null) : 0;
    if ($editing) {
        $current = inventory_ingredient($db, $id, true);
        if (!is_string($data['version'] ?? null) || !hash_equals($current['version'], $data['version'])) {
            throw new InventoryInputError('Ingredient changed since this form opened. Close and reopen it before saving.', 409);
        }
        if ($unit !== $current['Unit']) {
            $refs = $db->prepare('SELECT COUNT(*) AS count FROM productmaterial WHERE RawMatID=?');
            $refs->bind_param('i', $id);
            $refs->execute();
            if ((float)$current['StockAvailable'] !== 0.0 || (int)$refs->get_result()->fetch_assoc()['count'] > 0) {
                throw new InventoryInputError('Unit cannot change while stock or recipe references exist. No automatic unit conversion is performed.', 409);
            }
        }
    }
    $duplicate = $db->prepare('SELECT RawMatID FROM rawmaterial WHERE RawMaterial=? AND RawMatID<>? LIMIT 1 FOR UPDATE');
    $duplicate->bind_param('si', $name, $id);
    $duplicate->execute();
    if ($duplicate->get_result()->fetch_assoc()) throw new InventoryInputError('An ingredient with this name already exists.', 409);
    if ($editing) {
        $stmt = $db->prepare('UPDATE rawmaterial SET RawMaterial=?, Unit=?, StockAvailable=?, ReorderingPt=? WHERE RawMatID=?');
        $stmt->bind_param('ssssi', $name, $unit, $stock, $reorder, $id);
    } else {
        $stmt = $db->prepare('INSERT INTO rawmaterial (RawMaterial, Unit, StockAvailable, ReorderingPt) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $unit, $stock, $reorder);
    }
    $stmt->execute();
    if (!$editing) $id = $db->insert_id;
    return inventory_ingredient($db, $id);
}

function inventory_recipe($db, $productID, $lock = false) {
    $stmt = $db->prepare('SELECT ProductID, Name, Size, isRecipe, TotalUsed FROM products WHERE ProductID=? AND isDeleted=0' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) throw new InventoryInputError('Product not found or unavailable.', 404);
    $stmt = $db->prepare('SELECT pm.RawMatID, pm.QtyUsed, r.RawMaterial AS IngredientName, r.Unit FROM productmaterial pm JOIN rawmaterial r ON r.RawMatID=pm.RawMatID WHERE pm.ProductID=? ORDER BY pm.RawMatID');
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    $ingredients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $versionRows = array_map(function ($row) { return [(string)$row['RawMatID'], (string)$row['QtyUsed'], $row['Unit']]; }, $ingredients);
    $version = hash('sha256', json_encode([(string)$productID, (string)$product['isRecipe'], $versionRows]));
    return ['success' => true, 'product' => $product, 'ingredients' => $ingredients, 'version' => $version];
}

function inventory_save_recipe($db, array $data) {
    $productID = inventory_id($data['ProductID'] ?? null);
    $items = $data['ingredients'] ?? null;
    if (!is_array($items) || count($items) < 1 || count($items) > 200 || array_keys($items) !== range(0, count($items) - 1)) {
        throw new InventoryInputError('Provide between 1 and 200 recipe ingredients.', 422);
    }
    $validated = [];
    foreach ($items as $item) {
        if (!is_array($item)) throw new InventoryInputError('Invalid recipe row.', 422);
        $id = inventory_id($item['RawMatID'] ?? null);
        if (isset($validated[$id])) throw new InventoryInputError('Each ingredient may appear only once.', 422);
        $validated[$id] = ['qty' => inventory_decimal($item['QtyUsed'] ?? null, 'Recipe quantity', true), 'unit' => inventory_unit($item['Unit'] ?? null)];
    }
    // Serialize recipe editors on the parent product, then lock ingredients in ID order.
    $current = inventory_recipe($db, $productID, true);
    if (!is_string($data['version'] ?? null) || !hash_equals($current['version'], $data['version'])) {
        throw new InventoryInputError('Recipe changed since this form opened. Close and reopen it before saving.', 409);
    }
    ksort($validated);
    foreach ($validated as $id => $item) {
        $ingredient = inventory_ingredient($db, $id, true);
        if ($ingredient['Unit'] !== $item['unit']) throw new InventoryInputError('Ingredient unit changed. Reopen the recipe before saving.', 409);
    }
    // Keep existing recipe rows where possible; all changes share one transaction.
    $insert = $db->prepare('INSERT INTO productmaterial (ProductID, RawMatID, QtyUsed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE QtyUsed=VALUES(QtyUsed)');
    foreach ($validated as $id => $item) {
        $qty = $item['qty'];
        $insert->bind_param('iis', $productID, $id, $qty);
        $insert->execute();
    }
    $delete = $db->prepare('DELETE FROM productmaterial WHERE ProductID=? AND RawMatID=?');
    foreach ($current['ingredients'] as $old) {
        $id = (int)$old['RawMatID'];
        if (!isset($validated[$id])) {
            $delete->bind_param('ii', $productID, $id);
            $delete->execute();
        }
    }
    $stmt = $db->prepare('UPDATE products SET isRecipe=1'.(p4b_schema_ready($db)?',ConfigVersion=ConfigVersion+1':'').' WHERE ProductID=?');
    $stmt->bind_param('i', $productID);
    $stmt->execute();
    return inventory_recipe($db, $productID);
}

// Caller owns the transaction. Serialize incoming stock with checkout and editors.
function inventory_restock($db, array $data) {
    if (array_key_exists('rawMatID', $data) === array_key_exists('productID', $data)) {
        throw new InventoryInputError('Select exactly one ingredient or product.', 422);
    }
    if (array_key_exists('rawMatID', $data)) {
        $id = inventory_id($data['rawMatID']);
        $quantity = inventory_decimal($data['qty'] ?? null, 'Incoming stock', true);
        $current = inventory_ingredient($db, $id, true);
        $total = inventory_scaled($current['StockAvailable'], 'Current stock') + inventory_scaled($quantity);
        if ($total > 9999999999) throw new InventoryInputError('Incoming stock exceeds the supported stock limit.', 422);
        $stock = inventory_scaled_decimal($total);
        $stmt = $db->prepare('UPDATE rawmaterial SET StockAvailable=? WHERE RawMatID=?');
        $stmt->bind_param('si', $stock, $id);
        $stmt->execute();
        $ingredient = inventory_ingredient($db, $id);
        return ['success'=>true, 'type'=>'raw', 'addedQty'=>$quantity, 'ingredient'=>$ingredient];
    }
    $id = inventory_id($data['productID']);
    // Ordinary product stock is integral in the existing schema.
    $quantity = inventory_id($data['change'] ?? null);
    $stmt = $db->prepare('SELECT QtyStock,isRecipe FROM products WHERE ProductID=? AND isDeleted=0 FOR UPDATE');
    $stmt->bind_param('i', $id); $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) throw new InventoryInputError('Product not found.', 404);
    $refs = $db->prepare('SELECT COUNT(*) FROM productmaterial WHERE ProductID=?');
    $refs->bind_param('i', $id); $refs->execute();
    if ((string)$product['isRecipe'] !== '0' || (int)$refs->get_result()->fetch_row()[0] > 0) {
        throw new InventoryInputError('Cannot restock made-to-order or incorrectly configured products.', 409);
    }
    $total = (int)$product['QtyStock'] + $quantity;
    if ((int)$product['QtyStock'] < 0 || $total > 2147483647) throw new InventoryInputError('Incoming stock exceeds the supported stock limit.', 422);
    $stmt = $db->prepare('UPDATE products SET QtyStock=? WHERE ProductID=?');
    $stmt->bind_param('ii', $total, $id); $stmt->execute();
    return ['success'=>true, 'type'=>'product', 'addedQty'=>$quantity, 'AvailableStock'=>$total];
}

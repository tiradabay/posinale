<?php
// Exact hundredths, matching the existing DECIMAL(10,2) inventory schema.
function inventory_scaled($value, $label = 'Quantity', $positive = false) {
    if ((!is_string($value) && !is_int($value) && !is_float($value)) ||
        !preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D', (string)$value)) {
        throw new InvalidArgumentException($label . ' must be a nonnegative decimal with at most two decimal places and maximum 99999999.99.');
    }
    $parts = explode('.', (string)$value);
    $scaled = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    if ($positive && $scaled === 0) throw new InvalidArgumentException($label . ' must be greater than zero.');
    return $scaled;
}

function inventory_scaled_decimal($value) {
    return intdiv($value, 100) . '.' . str_pad((string)($value % 100), 2, '0', STR_PAD_LEFT);
}

function inventory_stock_status($available, $threshold, $healthy = 'Available') {
    if ($available <= 0) return 'Out of Stock';
    try { $minimum = inventory_scaled($threshold, 'Reorder threshold'); }
    catch (InvalidArgumentException $error) { return 'Out of Stock'; }
    return $available * 100 <= $minimum ? 'Low Stock' : $healthy;
}

function inventory_recipe_map($db) {
    $map = [];
    // LEFT JOIN makes malformed/missing ingredient references fail closed as well.
    $rs = $db->query('SELECT pm.ProductID, pm.QtyUsed, r.StockAvailable FROM productmaterial pm LEFT JOIN rawmaterial r ON r.RawMatID=pm.RawMatID ORDER BY pm.ProductID, pm.RawMatID');
    while ($row = $rs->fetch_assoc()) $map[(int)$row['ProductID']][] = $row;
    return $map;
}

function inventory_ingredient_status(array $ingredient) {
    try {
        $stock = inventory_scaled($ingredient['StockAvailable'], 'Stock');
        $minimum = inventory_scaled($ingredient['ReorderingPt'], 'Reorder threshold');
    } catch (InvalidArgumentException $error) { return 'Out of Stock'; }
    if ($stock === 0) return 'Out of Stock';
    return $stock <= $minimum ? 'Low Stock' : 'OK';
}

function inventory_product_available(array $product, array $recipe) {
    if ((int)($product['isDeleted'] ?? 0) !== 0) return 0;
    if ((string)$product['isRecipe'] === '0') {
        if ($recipe || !preg_match('/^(?:0|[1-9][0-9]*)$/D', (string)$product['QtyStock'])) return 0;
        return max(0, (int)$product['QtyStock']);
    }
    if ((string)$product['isRecipe'] !== '1' || !$recipe) return 0;
    $available = null;
    foreach ($recipe as $ingredient) {
        try {
            $stock = inventory_scaled($ingredient['StockAvailable'], 'Ingredient stock');
            $usage = inventory_scaled($ingredient['QtyUsed'], 'Recipe quantity', true);
        } catch (InvalidArgumentException $error) { return 0; }
        $possible = intdiv($stock, $usage);
        $available = $available === null ? $possible : min($available, $possible);
    }
    return $available ?? 0;
}

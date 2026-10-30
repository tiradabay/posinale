<?php
/** Edit Product exposes name and price only. Stock belongs to inventory restocking. */
require_once __DIR__ . '/inventory_stock.php';
class CatalogInputError extends RuntimeException {}

function catalog_edit_fields(array $data): array {
    $allowed = ['productID', 'name', 'price'];
    if (array_diff(array_keys($data), $allowed) || array_diff($allowed, array_keys($data))) {
        throw new CatalogInputError('Supply only productID, name and price. Use inventory restocking to change stock.', 422);
    }
    $id = $data['productID'];
    if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$id) || (int)$id > 2147483647) {
        throw new CatalogInputError('Invalid product ID.', 422);
    }
    $name = $data['name'];
    if (!is_string($name) || !preg_match('//u', $name) || preg_match('/[\x00-\x1f\x7f]/u', $name)) {
        throw new CatalogInputError('Invalid product name.', 422);
    }
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 150) throw new CatalogInputError('Product name must contain 1–150 characters.', 422);
    try { $price = inventory_scaled($data['price'], 'Price'); }
    catch (InvalidArgumentException $e) { throw new CatalogInputError('Price must be between 0 and 99999999.99, with at most two decimal places.', 422); }
    return ['productID' => (int)$id, 'name' => $name, 'price' => inventory_scaled_decimal($price)];
}

function catalog_update_product(mysqli $db, array $fields, bool $versioned = false): void {
    // Caller owns the transaction. Serialize against checkout and quote snapshots.
    $stmt = $db->prepare('SELECT Name, Price, isDeleted FROM products WHERE ProductID=? FOR UPDATE');
    $stmt->bind_param('i', $fields['productID']); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || (int)$row['isDeleted'] !== 0) throw new CatalogInputError('Product not found.', 404);
    if ($row['Name'] === $fields['name'] && $row['Price'] === $fields['price']) return;
    $stmt = $db->prepare('UPDATE products SET Name=?, Price=?' . ($versioned ? ', ConfigVersion=ConfigVersion+1' : '') . ' WHERE ProductID=?');
    $stmt->bind_param('ssi', $fields['name'], $fields['price'], $fields['productID']);
    $stmt->execute();
}

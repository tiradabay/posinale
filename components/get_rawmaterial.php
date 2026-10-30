<?php
require_once __DIR__ . '/ingredient_recipe_api.php';
inventory_api(function ($db) {
    if (isset($_GET['RawMatID'])) {
        return ['success' => true, 'ingredient' => inventory_ingredient($db, inventory_id($_GET['RawMatID']))];
    }
    $rows = $db->query('SELECT RawMatID, RawMaterial, Unit, StockAvailable, ReorderingPt FROM rawmaterial ORDER BY RawMaterial, RawMatID')->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) $row['version'] = inventory_ingredient_version($row);
    unset($row);
    return $rows;
});
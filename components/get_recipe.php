<?php
require_once __DIR__ . '/ingredient_recipe_api.php';
inventory_api(function ($db) {
    $id = inventory_id($_GET['ProductID'] ?? $_GET['productID'] ?? null);
    $recipe = inventory_recipe($db, $id);
    if (($_GET['format'] ?? '') === 'edit') return $recipe;
    // Preserve the existing recipe viewer's array response.
    foreach ($recipe['ingredients'] as &$row) $row['TotalUsed'] = $recipe['product']['TotalUsed'];
    unset($row);
    return $recipe['ingredients'];
});
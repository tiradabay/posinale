<?php
require_once __DIR__ . '/ingredient_recipe_api.php';
inventory_api(function ($db, $data) {
    $ingredient = inventory_save_ingredient($db, $data);
    return ['success' => true, 'RawMatID' => $ingredient['RawMatID'], 'ingredient' => $ingredient];
}, true);
<?php
require_once __DIR__ . '/ingredient_recipe_api.php';
inventory_api(function ($db, $data) {
    return ['success' => true, 'ingredient' => inventory_save_ingredient($db, $data, true)];
}, true);
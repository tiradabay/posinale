<?php
require_once __DIR__ . '/ingredient_recipe_api.php';
inventory_api(function ($db, $data) {
    return inventory_save_recipe($db, $data);
}, true);
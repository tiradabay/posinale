<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/inventory_stock.php';
header('Content-Type: application/json');
$conn->query('START TRANSACTION READ ONLY');
$rawMaterials=[];
$raw=$conn->query('SELECT * FROM rawmaterial');
while($row=$raw->fetch_assoc()) {
    $row['AvailableStock']=(float)$row['StockAvailable'];
    $row['stockStatus']=inventory_ingredient_status($row);
    $rawMaterials[]=$row;
}
$recipes=inventory_recipe_map($conn);
$products=[];
$result=$conn->query('SELECT * FROM products');
while($row=$result->fetch_assoc()) {
    $row['AvailableStock']=inventory_product_available($row,$recipes[(int)$row['ProductID']]??[]);
    $row['stockStatus']=inventory_stock_status($row['AvailableStock'],$row['LowStockThreshold']);
    $products[]=$row;
}
$conn->commit();
echo json_encode(['success'=>true,'message'=>'Inventory loaded successfully!','rawMaterials'=>$rawMaterials,'products'=>$products]);
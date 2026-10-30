<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin','cashier']);
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/inventory_stock.php';
header('Content-Type: application/json');
$data=json_decode(file_get_contents('php://input'),true);
// Preserve the existing read-only product lookup used by the product editor.
if(!empty($data['productID'])) {
    $id=(int)$data['productID'];
    $stmt=$conn->prepare('SELECT ProductID,Name,Price FROM products WHERE ProductID=?');
    $stmt->bind_param('i',$id);$stmt->execute();$product=$stmt->get_result()->fetch_assoc();
    echo json_encode($product?['success'=>true,'data'=>$product]:['success'=>false,'error'=>'Product not found']);exit;
}
$search=isset($_GET['search'])?'%'.$_GET['search'].'%':'%';
$conn->query('START TRANSACTION READ ONLY');
$stmt=$conn->prepare('SELECT ProductID,Name,Size,Price,ImagePath,QtyStock,isRecipe,LowStockThreshold FROM products WHERE Name LIKE ? AND isDeleted=0 ORDER BY Name');
$stmt->bind_param('s',$search);$stmt->execute();$result=$stmt->get_result();
$recipes=inventory_recipe_map($conn);$products=[];
while($row=$result->fetch_assoc()) {
    $row['AvailableStock']=inventory_product_available($row,$recipes[(int)$row['ProductID']]??[]);
    $row['stockStatus']=inventory_stock_status($row['AvailableStock'],$row['LowStockThreshold']);
    $row['isRecipe']=(int)$row['isRecipe']===1;
    $products[]=$row;
}
$conn->commit();
echo json_encode($products);
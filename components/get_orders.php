<?php
require_once __DIR__.'/auth_check.php';
require_api_auth(['admin','cashier']);
require_once __DIR__.'/../connection.php';
require_once __DIR__.'/pending_schema.php';
header('Content-Type: application/json');
$ready=p4b_schema_ready($conn);
$name=$ready?'COALESCE(s.ProductNameSnapshot,pr.Name)':'pr.Name';
$snap=$ready?'LEFT JOIN order_item_snapshots s ON s.OrderDetailID=od.OrderDetailID':'';
// Shared historical Cash access remains; Cashiers see only their own new Pending history.
$where=$ready&&$_SESSION['role']==='cashier'?'WHERE (o.SnapshotVersion=1 OR o.UserID=?)':'';
$sql="SELECT o.OrderID,o.Date,o.TotalAmount,o.Status,p.Method,
 GROUP_CONCAT(CONCAT($name,' x',od.Quantity) ORDER BY od.OrderDetailID SEPARATOR ', ') AS Items
 FROM orders o LEFT JOIN payments p ON p.OrderID=o.OrderID
 LEFT JOIN orderdetails od ON od.OrderID=o.OrderID
 LEFT JOIN products pr ON pr.ProductID=od.ProductID $snap $where
 GROUP BY o.OrderID,o.Date,o.TotalAmount,o.Status,p.Method ORDER BY o.Date DESC,o.OrderID DESC LIMIT 50";
$stmt=$conn->prepare($sql);
if($where!=='')$stmt->bind_param('i',$_SESSION['user_id']);
$stmt->execute();
echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

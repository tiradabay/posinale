<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

$res = mysqli_query($conn, "
    SELECT LoyaltyPoints 
    FROM customer 
    WHERE CustomerID = $id
");

$row = mysqli_fetch_assoc($res);

echo json_encode([
    "success" => true,
    "points" => intval($row['LoyaltyPoints'] ?? 0)
]);
?>
<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';

$customerID = $_GET['customerID'];

$sql = "SELECT lp.*, c.Name FROM loyaltyprogram lp
        JOIN customer c ON lp.CustomerID = c.CustomerID
        WHERE lp.CustomerID = ?
        ORDER BY lp.Date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerID);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

header('Content-Type: application/json');
echo json_encode($history);
?>
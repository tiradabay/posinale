<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$customerID = intval($data['customerID'] ?? 0);
$orderID    = intval($data['orderID'] ?? 0);
$amount     = floatval($data['amount'] ?? 0);

if ($customerID <= 0 || $orderID <= 0 || $amount <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid input data'
    ]);
    exit;
}

// RULE ₱10 = 1 point
$pointsEarned = floor($amount / 10);

// If no points, still return success but do nothing
if ($pointsEarned <= 0) {
    echo json_encode([
        'success' => true,
        'pointsEarned' => 0
    ]);
    exit;
}

// Update Customer total points
$updateSQL = "UPDATE Customer 
              SET LoyaltyPoints = LoyaltyPoints + ? 
              WHERE CustomerID = ?";

$stmt = mysqli_prepare($conn, $updateSQL);
mysqli_stmt_bind_param($stmt, "ii", $pointsEarned, $customerID);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

// Log into LoyaltyProgram
$logSQL = "INSERT INTO LoyaltyProgram 
           (CustomerID, OrderID, PointsEarned, PointsRedeemed, Note)
           VALUES (?, ?, ?, 0, 'Order Earned Points')";

$stmt2 = mysqli_prepare($conn, $logSQL);
mysqli_stmt_bind_param($stmt2, "iii", $customerID, $orderID, $pointsEarned);
mysqli_stmt_execute($stmt2);

echo json_encode([
    'success' => true,
    'pointsEarned' => $pointsEarned
]);
?>
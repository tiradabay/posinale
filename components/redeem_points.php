<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
// Phase 3 isolation: this legacy route cannot atomically fulfill a discount.
// Preserve its implementation for the scheduled Phase 7 removal, but never let
// it spend points independently of the authoritative receipt/payment path.
require_once __DIR__ . '/security.php';
header('Content-Type: application/json');
security_require_post();
security_require_csrf();
http_response_code(409);
echo json_encode(['success'=>false,'error'=>'Loyalty discounts are unavailable. No points were deducted and no discount was applied.']);
exit;
include '../connection.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$customerID = intval($data['customerID'] ?? 0);
$orderID    = intval($data['orderID'] ?? 0);
$pointsUsed  = intval($data['pointsUsed'] ?? 0);

if ($customerID <= 0 || $pointsUsed <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// check current balance
$check = mysqli_query($conn, "SELECT LoyaltyPoints FROM Customer WHERE CustomerID=$customerID");
$row = mysqli_fetch_assoc($check);

if (!$row || $row['LoyaltyPoints'] < $pointsUsed) {
    echo json_encode(['success' => false, 'error' => 'Not enough points']);
    exit;
}

// convert points → discount
$discount = floor($pointsUsed / 10); 

// deduct points
mysqli_query($conn, "
    UPDATE customer 
    SET LoyaltyPoints = LoyaltyPoints - $pointsUsed 
    WHERE CustomerID = $customerID
");

// log transaction
mysqli_query($conn, "
    INSERT INTO loyaltyProgram 
    (CustomerID, OrderID, PointsEarned, PointsRedeemed, Note)
    VALUES ($customerID, $orderID, 0, $pointsUsed, 'Redeemed for discount')
");

echo json_encode([
    'success' => true,
    'discount' => $discount,
    'pointsUsed' => $pointsUsed
]);
?>

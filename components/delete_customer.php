<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
include '../connection.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$customerID = intval($data['customerID'] ?? 0);

if ($customerID <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid customer ID'
    ]);
    exit;
}

/* DELETE CUSTOMER */
$sql = "DELETE FROM Customer WHERE CustomerID = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $customerID);

if (mysqli_stmt_execute($stmt)) {

    echo json_encode([
        'success' => true
    ]);

} else {

    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);

}
?>
<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$customerID = intval($data['customerID'] ?? 0);
$name = trim($data['name'] ?? '');
$contact = trim($data['contact'] ?? '');

if ($customerID <= 0 || $name === '') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid input'
    ]);
    exit;
}

$sql = "
    UPDATE Customer
    SET Name = ?, Contact = ?
    WHERE CustomerID = ?
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ssi", $name, $contact, $customerID);

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
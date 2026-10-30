<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin', 'cashier']);
include '../connection.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$name    = trim($data['name'] ?? '');
$contact = trim($data['contact'] ?? '');
$email   = trim($data['email'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Name is required']);
    exit;
}

// Qurry
$sql = "INSERT INTO customer (Name, Contact, Email, LoyaltyPoints)
        VALUES (?, ?, ?, 0)";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "sss", $name, $contact, $email);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'customerID' => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => mysqli_error($conn)
    ]);
}
?>
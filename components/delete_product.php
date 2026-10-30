<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
include '../connection.php';
header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['productID'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid product ID"
    ]);
    exit;
}

try {

    $stmt = $conn->prepare("
        DELETE FROM products 
        WHERE ProductID = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Product deleted successfully"
    ]);

} catch (mysqli_sql_exception $e) {

    echo json_encode([
        "success" => false,
        "error" => "Cannot delete: product is used in orders"
    ]);
}
?>
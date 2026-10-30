<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/secure_upload.php';
security_require_post();
security_require_csrf();
include '../connection.php';
header('Content-Type: application/json');

$data = $_POST;
if (empty($data)) {
    $data = json_decode(file_get_contents("php://input"), true);
}

$name = trim($data['name'] ?? '');
$size = $data['size'] ?? 'Medium';
$price = floatval($data['price'] ?? 0);
$qtyStock = intval($data['qtyStock'] ?? 1);
$threshold = intval($data['lowStockThreshold'] ?? 5);

$imagePath = null;

if ($name === '') {
    echo json_encode(["success" => false, "error" => "Name required"]);
    exit;
}

/* Validate uploads before inserting any product record. */
try {
    if (isset($_FILES['imageFile']) && ($_FILES['imageFile']['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
        $imagePath = security_store_image($_FILES['imageFile'], 'uploads');
    }
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to store image.']);
    exit;
}

try {
/* INSERT */
$stmt = $conn->prepare("
    INSERT INTO products 
    (Name, Size, Price, QtyStock, ImagePath, LowStockThreshold)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssdisi",
    $name,
    $size,
    $price,
    $qtyStock,
    $imagePath,
    $threshold
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "ProductID" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => $stmt->error
    ]);
}
} catch (Throwable $error) {
    // Only remove this request's newly generated image if product insertion failed.
    if ($imagePath !== null) {
        $newFile = __DIR__ . '/../' . $imagePath;
        if (is_file($newFile)) unlink($newFile);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save product.']);
}
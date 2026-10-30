<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/secure_upload.php';
security_require_post();
security_require_csrf();
try {
    if (!isset($_FILES['imageFile']) || !is_array($_FILES['imageFile'])) throw new InvalidArgumentException('Choose an image.');
    $path = security_store_image($_FILES['imageFile'], 'assets/uploads');
    echo json_encode(['success' => true, 'path' => $path]);
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Unable to store image.']);
}
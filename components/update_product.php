<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/catalog_service.php';
require_once __DIR__ . '/pending_schema.php';
header('Content-Type: application/json; charset=utf-8');
security_require_post();
security_require_csrf();
$conn = null;
try {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new CatalogInputError('Send a JSON object.', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if (strlen($raw) > 65536) throw new CatalogInputError('Request is too large.', 413);
    try { $data = json_decode($raw, false, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new CatalogInputError('Invalid JSON object.', 400); }
    if (!is_object($data)) throw new CatalogInputError('Send a JSON object.', 400);
    $fields = catalog_edit_fields(get_object_vars($data));
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/../connection.php';
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    catalog_update_product($conn, $fields, p4b_schema_ready($conn));
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Product updated.']);
} catch (CatalogInputError $e) {
    if ($conn instanceof mysqli) $conn->rollback();
    http_response_code($e->getCode());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) $conn->rollback();
    error_log('Product update failed: ' . get_class($e));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update product. No changes were saved.']);
}

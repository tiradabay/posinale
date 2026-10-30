<?php
require_once __DIR__ . '/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/ingredient_recipe_service.php';
require_once __DIR__ . '/security.php';
header('Content-Type: application/json');

function inventory_json_input() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        throw new InventoryInputError('Use POST for this operation.', 405);
    }
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') throw new InventoryInputError('Send application/json.', 415);
    security_require_csrf();
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 65536) throw new InventoryInputError('Inventory request is too large.', 413);
    $data = json_decode($raw, true);
    if (!is_array($data) || !$data || substr(ltrim($raw), 0, 1) !== '{') {
        throw new InventoryInputError('Send a valid JSON object.', 400);
    }
    return $data;
}

function inventory_api($operation, $write = false) {
    $db = null;
    $started = false;
    try {
        $data = $write ? inventory_json_input() : [];
        if (!$write && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Allow: GET');
            throw new InventoryInputError('Use GET for this operation.', 405);
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/../connection.php';
        $db = $conn;
        $db->set_charset('utf8mb4');
        $db->begin_transaction();
        $started = true;
        $result = $operation($db, $data);
        $db->commit();
        $started = false;
        echo json_encode($result);
    } catch (Throwable $error) {
        if ($started) $db->rollback();
        $inputError = $error instanceof InventoryInputError;
        http_response_code($inputError ? $error->getCode() : 500);
        echo json_encode(['success' => false, 'error' => $inputError ? $error->getMessage() : 'Unable to save or load inventory. No changes were committed.']);
    }
}

<?php
require_once __DIR__ . '/../components/auth_check.php';
require_api_auth(['admin']);
require_once __DIR__ . '/../components/security.php';
require_once __DIR__ . '/../components/staff_registration.php';
security_require_post();
security_require_csrf();
$started = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/../connection.php';
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    $started = true;
    security_register_staff($conn, $_POST);
    $conn->commit();
    $started = false;
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Staff account created.']);
} catch (Throwable $error) {
    if ($started) $conn->rollback();
    $inputError = $error instanceof InvalidArgumentException;
    http_response_code($inputError ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $inputError ? $error->getMessage() : 'Unable to create staff account. No changes were committed.']);
}
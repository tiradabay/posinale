<?php
function security_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function security_image_path($value) {
    if (!is_string($value) || !preg_match('~^(?:assets|uploads)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_. -]+\.(?:png|jpe?g|gif|webp)$~iD', $value)) {
        return 'assets/logo.png';
    }
    return $value;
}

function security_csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_SESSION['security_csrf_token'])) $_SESSION['security_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['security_csrf_token'];
}

function security_require_csrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !isset($_SESSION['security_csrf_token']) || !hash_equals($_SESSION['security_csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or expired request token. Reload this page and try again.']);
        exit;
    }
}

function security_require_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Use POST for this operation.']);
        exit;
    }
}

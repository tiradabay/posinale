<?php
require_once __DIR__ . '/../components/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

// Repeated logout remains safe even when the session has already expired.
if (!empty($_SESSION['user_id'])) {
    $token = $_POST['logout_token'] ?? null;
    if (!is_string($token) || !isset($_SESSION['logout_token']) || !hash_equals($_SESSION['logout_token'], $token)) {
        http_response_code(403);
        exit;
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? ''
    ]);
}
session_destroy();
header('Location: ' . get_auth_redirect_urls()['login'], true, 303);
exit;

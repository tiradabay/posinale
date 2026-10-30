<?php
require_once __DIR__ . '/../components/auth_check.php';
header("Content-Type: application/json");

$role = get_authenticated_role();
$isLoggedIn = $role !== null;
if (!$isLoggedIn) {
    http_response_code(401);
}

echo json_encode([
    "loggedIn" => $isLoggedIn,
    "user_id"  => $isLoggedIn ? $_SESSION['user_id'] : null,
    "role"     => $role
]);
?>

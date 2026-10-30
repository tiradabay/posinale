<?php
require_once __DIR__ . '/../login/password_security.php';

function security_staff_input(array $data) {
    $name = $data['name'] ?? null;
    $role = $data['role'] ?? null;
    if (!is_string($name) || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1f\x7f]/u', $name)) throw new InvalidArgumentException('Enter a valid staff name.');
    $name = trim($name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    if ($length < 1 || $length > 100) throw new InvalidArgumentException('Staff name must contain 1 to 100 characters.');
    if (!is_string($role) || !in_array($role, ['admin', 'cashier'], true)) throw new InvalidArgumentException('Choose Admin or Cashier.');
    // Preserve the established password hashing and validation implementation.
    $hash = pos_hash_password($data['password'] ?? null);
    return [$name, $hash, $role];
}

// The authorized HTTP caller owns the transaction.
function security_register_staff($db, array $data) {
    [$name, $hash, $role] = security_staff_input($data);
    $check = $db->prepare('SELECT UserID FROM users WHERE Name=? FOR UPDATE');
    $check->bind_param('s', $name);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) throw new InvalidArgumentException('A staff account with this name already exists.');
    $insert = $db->prepare('INSERT INTO users (Name, Password, Role) VALUES (?, ?, ?)');
    $insert->bind_param('sss', $name, $hash, $role);
    $insert->execute();
    return $db->insert_id;
}

<?php
function pos_hash_password($password) {
    if (!is_string($password) || $password === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
        throw new InvalidArgumentException('Password must contain 1 to 72 bytes and no null bytes.');
    }
    return password_hash($password, PASSWORD_BCRYPT);
}
function pos_verify_password($password, $stored) {
    if (!is_string($password) || !is_string($stored) || $password === '' || $stored === '') return false;
    $info = password_get_info($stored);
    if ($info['algoName'] !== 'unknown') {
        if ($info['algoName'] === 'bcrypt' && (strlen($password) > 72 || strpos($password, "\0") !== false)) return false;
        return password_verify($password, $stored);
    }
    // Temporary plaintext compatibility pending approval to convert existing records.
    if (preg_match('/^\$(2[aby]\$|argon2)/', $stored)) return false;
    return hash_equals($stored, $password);
}
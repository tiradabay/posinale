<?php
session_start();
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/password_security.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success"=>false,"message"=>"No input"]);
    exit;
}


$name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

if ($name === '' || $password === '') {
    echo json_encode(["success"=>false,"message"=>"Missing fields"]);
    exit;
}

$stmt = $conn->prepare("SELECT UserID, Password, Role FROM users WHERE Name=?");
$stmt->bind_param("s", $name);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    if (pos_verify_password($password, $row['Password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $row['UserID'];
        $_SESSION['role'] = $row['Role'];

        echo json_encode([
            "success" => true,
            "role" => $row['Role'],
            "message" => "Login successful"
        ]);

    } else {
        echo json_encode([
            "success" => false,
            "message" => "Wrong password"
        ]);
    }

} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid username"
    ]);
}
?>
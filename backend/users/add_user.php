<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$full_name = trim($data["full_name"] ?? "");
$student_id = trim($data["student_id"] ?? "");
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "");
$status = trim($data["status"] ?? "Active");

if ($full_name === "" || $student_id === "" || $email === "" || $password === "" || $role === "") {
    echo json_encode([
        "success" => false,
        "message" => "All required fields must be filled out."
    ]);
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (full_name, student_id, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $full_name, $student_id, $email, $hashedPassword, $role, $status);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "User added successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add user: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

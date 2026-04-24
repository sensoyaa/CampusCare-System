<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$full_name = trim($data["full_name"] ?? "");
$student_id = trim($data["student_id"] ?? "");
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "Student");

if ($full_name === "" || $email === "" || $password === "" || $role === "") {
    echo json_encode([
        "success" => false,
        "message" => "All required fields must be filled out."
    ]);
    exit();
}

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

if (!$checkStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Unable to validate email uniqueness."
    ]);
    exit();
}

$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$emailExists = $checkStmt->get_result()->num_rows > 0;
$checkStmt->close();

if ($emailExists) {
    echo json_encode([
        "success" => false,
        "message" => "Email must be unique. Duplicate email is not allowed."
    ]);
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (full_name, student_id, email, password, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $full_name, $student_id, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Registration successful."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => intval($stmt->errno) === 1062
            ? "Email must be unique. Duplicate email is not allowed."
            : "Registration failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

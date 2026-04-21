<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/recaptcha.php";

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$full_name = trim($data["full_name"] ?? "");
$student_id = trim($data["student_id"] ?? "");
$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$role = trim($data["role"] ?? "Student");
$recaptcha_token = trim($data["recaptcha_token"] ?? "");

if ($full_name === "" || $email === "" || $password === "" || $role === "") {
    echo json_encode([
        "success" => false,
        "message" => "All required fields must be filled out."
    ]);
    exit();
}

if ($recaptcha_token === "") {
    echo json_encode([
        "success" => false,
        "message" => "Please complete the reCAPTCHA challenge."
    ]);
    exit();
}

$recaptchaCheck = verify_recaptcha_token($recaptcha_token, $_SERVER["REMOTE_ADDR"] ?? null);

if (!($recaptchaCheck["success"] ?? false)) {
    echo json_encode([
        "success" => false,
        "message" => $recaptchaCheck["message"] ?? "reCAPTCHA verification failed."
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
        "message" => "Registration failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

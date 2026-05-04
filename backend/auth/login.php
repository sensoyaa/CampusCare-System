<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/recaptcha.php";

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$email = trim($data["email"] ?? "");
$password = trim($data["password"] ?? "");
$recaptcha_token = trim($data["recaptcha_token"] ?? "");

if ($email === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
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

$stmt = $conn->prepare("SELECT id, full_name, student_id, email, password, role, avatar_path, college, program FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {
        $user["avatar_path"] = trim((string) ($user["avatar_path"] ?? ""));
        $user["college"] = trim((string) ($user["college"] ?? ""));
        $user["program"] = trim((string) ($user["program"] ?? ""));
        unset($user["password"]);
        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "user" => $user
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid password."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
}

$stmt->close();
$conn->close();

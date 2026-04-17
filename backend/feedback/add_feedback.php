<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = intval($data["user_id"] ?? 0);
$message = trim($data["message"] ?? "");

if ($user_id <= 0 || $message === "") {
    echo json_encode([
        "success" => false,
        "message" => "User ID and message are required."
    ]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO feedback (user_id, message) VALUES (?, ?)");
$stmt->bind_param("is", $user_id, $message);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Feedback submitted successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save feedback."
    ]);
}

$stmt->close();
$conn->close();

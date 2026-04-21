<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = intval($data["user_id"] ?? 0);
$answer_1 = trim($data["answer_1"] ?? "");
$answer_2 = trim($data["answer_2"] ?? "");
$result_text = trim($data["result_text"] ?? "");

if ($user_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user ID."
    ]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO mental_health_tests (user_id, answer_1, answer_2, result_text) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $answer_1, $answer_2, $result_text);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Test submitted successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save test."
    ]);
}

$stmt->close();
$conn->close();

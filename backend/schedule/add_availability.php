<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$counselor_id = intval($data["counselor_id"] ?? 0);
$day = trim($data["day"] ?? "");
$from = trim($data["from"] ?? "");
$to = trim($data["to"] ?? "");

if ($counselor_id <= 0 || $day === "" || $from === "" || $to === "") {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit();
}

if ($from === $to) {
    echo json_encode([
        "success" => false,
        "message" => "Start and end times cannot be the same."
    ]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO counselor_availability (counselor_id, day, start_time, end_time)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param("isss", $counselor_id, $day, $from, $to);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Availability added successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add availability."
    ]);
}

$stmt->close();
$conn->close();
?>
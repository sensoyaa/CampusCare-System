<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$event_id = intval($data["event_id"] ?? 0);
$user_id = intval($data["user_id"] ?? 0);

if ($event_id <= 0 || $user_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Event ID and user ID are required."
    ]);
    exit();
}

$stmt = $conn->prepare("
    INSERT IGNORE INTO event_participants (event_id, user_id)
    VALUES (?, ?)
");

$stmt->bind_param("ii", $event_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "You have joined the event successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to join event."
    ]);
}

$stmt->close();
$conn->close();
?>
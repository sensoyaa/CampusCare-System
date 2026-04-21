<?php
require_once "../config/cors.php";
require_once "../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$appointment_id = intval($data["appointment_id"] ?? 0);
$counselor_id = intval($data["counselor_id"] ?? 0);
$student_id = intval($data["student_id"] ?? 0);
$notes = trim($data["notes"] ?? "");

if ($appointment_id <= 0 || $counselor_id <= 0 || $student_id <= 0 || $notes === "") {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO session_feedback (appointment_id, counselor_id, student_id, notes)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
");

$stmt->bind_param("iiis", $appointment_id, $counselor_id, $student_id, $notes);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Session notes saved successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save session notes."
    ]);
}

$stmt->close();
$conn->close();
?>
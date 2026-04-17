<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = intval($data["user_id"] ?? 0);
$counselor_id = intval($data["counselor_id"] ?? 0);
$service = trim($data["service"] ?? "");
$counselor = trim($data["counselor"] ?? "");
$appointment_date = trim($data["appointment_date"] ?? "");
$appointment_time = trim($data["appointment_time"] ?? "");

if (
    $user_id <= 0 ||
    $counselor_id <= 0 ||
    $service === "" ||
    $counselor === "" ||
    $appointment_date === "" ||
    $appointment_time === ""
) {
    echo json_encode([
        "success" => false,
        "message" => "All appointment fields are required."
    ]);
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO appointments (user_id, counselor_id, service, counselor, appointment_date, appointment_time)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iissss", $user_id, $counselor_id, $service, $counselor, $appointment_date, $appointment_time);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Appointment booked successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save appointment."
    ]);
}

$stmt->close();
$conn->close();
?>

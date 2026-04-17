<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);
$status = trim($data["status"] ?? "");

$allowedStatuses = ["Pending", "Approved", "Cancelled", "Rejected"];

if ($id <= 0 || $status === "") {
    echo json_encode([
        "success" => false,
        "message" => "Appointment ID and status are required."
    ]);
    exit();
}

if (!in_array($status, $allowedStatuses)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid status."
    ]);
    exit();
}

$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Appointment status updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update appointment status."
    ]);
}

$stmt->close();
$conn->close();
?>

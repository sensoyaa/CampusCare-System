<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);
$counselor_id = intval($data["counselor_id"] ?? 0);

if ($id <= 0 || $counselor_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid availability ID."
    ]);
    exit();
}

$stmt = $conn->prepare("
    DELETE FROM counselor_availability
    WHERE id = ? AND counselor_id = ?
");

$stmt->bind_param("ii", $id, $counselor_id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Availability removed successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to remove availability."
    ]);
}

$stmt->close();
$conn->close();
?>
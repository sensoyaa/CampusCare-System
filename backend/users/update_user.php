<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);
$full_name = trim($data["full_name"] ?? "");
$role = trim($data["role"] ?? "");
$status = trim($data["status"] ?? "");

if ($id <= 0 || $full_name === "" || $role === "" || $status === "") {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields."
    ]);
    exit();
}

$stmt = $conn->prepare("UPDATE users SET full_name = ?, role = ?, status = ? WHERE id = ?");
$stmt->bind_param("sssi", $full_name, $role, $status, $id);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "User updated successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update user."
    ]);
}

$stmt->close();
$conn->close();
?>

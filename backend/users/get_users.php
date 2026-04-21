<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$sql = "SELECT id, full_name, student_id, email, role, status FROM users ORDER BY id DESC";
$result = $conn->query($sql);

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode([
    "success" => true,
    "users" => $users
]);

$conn->close();
?>

<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$stmt = $conn->prepare("
    SELECT id, full_name
    FROM users
    WHERE role = 'Counsellor' AND status = 'Active'
    ORDER BY full_name ASC
");
$stmt->execute();
$result = $stmt->get_result();

$counselors = [];

while ($row = $result->fetch_assoc()) {
    $counselors[] = $row;
}

echo json_encode([
    "success" => true,
    "counselors" => $counselors
]);

$stmt->close();
$conn->close();
?>

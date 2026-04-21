<?php
require_once "../config/cors.php";
require_once "../config/db.php";

$counselor_id = intval($_GET["counselor_id"] ?? 0);

if ($counselor_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Counselor ID is required."
    ]);
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.service,
        a.counselor,
        a.appointment_date,
        a.appointment_time,
        a.status,
        u.full_name AS student_name,
        u.student_id
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.counselor_id = ?
      AND u.role = 'Student'
      AND a.status != 'Cancelled'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

echo json_encode([
    "success" => true,
    "appointments" => $appointments
]);

$stmt->close();
$conn->close();
?>
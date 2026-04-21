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
        a.id AS appointment_id,
        a.service,
        a.appointment_date,
        a.appointment_time,
        a.status,
        u.id AS student_user_id,
        u.full_name AS student_name,
        u.student_id,
        sf.notes
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    LEFT JOIN session_feedback sf ON sf.appointment_id = a.id
    WHERE a.counselor_id = ?
      AND u.role = 'Student'
      AND a.status != 'Cancelled'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];

while ($row = $result->fetch_assoc()) {
    $dateTime = $row["appointment_date"] . " " . $row["appointment_time"];

    $sessions[] = [
        "id" => (int)$row["appointment_id"],
        "student_user_id" => (int)$row["student_user_id"],
        "student" => $row["student_name"],
        "student_id" => $row["student_id"],
        "date" => date("M j, Y g:i A", strtotime($dateTime)),
        "type" => $row["service"],
        "status" => $row["status"],
        "notes" => $row["notes"] ?? "",
        "saved" => !empty($row["notes"])
    ];
}

echo json_encode([
    "success" => true,
    "sessions" => $sessions
]);

$stmt->close();
$conn->close();
?>
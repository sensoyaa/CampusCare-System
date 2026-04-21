<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$counselor_id = intval($_GET["counselor_id"] ?? 0);

if ($counselor_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Counselor ID is required."
    ]);
    exit();
}

$today = date("Y-m-d");
$weekStart = date("Y-m-d", strtotime("monday this week"));
$weekEnd = date("Y-m-d", strtotime("sunday this week"));

$todaySessions = 0;
$pendingNotes = 0;
$thisWeek = 0;
$todayAppointments = [];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE counselor_id = ? AND appointment_date = ? AND status != 'Cancelled'
");
$stmt->bind_param("is", $counselor_id, $today);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $todaySessions = (int)$row["total"];
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE counselor_id = ? AND status = 'Approved'
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $pendingNotes = (int)$row["total"];
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM appointments
    WHERE counselor_id = ? AND appointment_date BETWEEN ? AND ? AND status != 'Cancelled'
");
$stmt->bind_param("iss", $counselor_id, $weekStart, $weekEnd);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $thisWeek = (int)$row["total"];
}
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        users.full_name AS student,
        appointments.appointment_time,
        appointments.service,
        appointments.status
    FROM appointments
    INNER JOIN users ON appointments.user_id = users.id
    WHERE appointments.counselor_id = ? AND appointments.appointment_date = ?
    ORDER BY appointments.appointment_time ASC
");
$stmt->bind_param("is", $counselor_id, $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $todayAppointments[] = [
        "student" => $row["student"],
        "time" => date("g:i A", strtotime($row["appointment_time"])),
        "type" => $row["service"],
        "status" => $row["status"]
    ];
}
$stmt->close();

echo json_encode([
    "success" => true,
    "stats" => [
        "today_sessions" => $todaySessions,
        "pending_notes" => $pendingNotes,
        "this_week" => $thisWeek
    ],
    "today_appointments" => $todayAppointments
]);

$conn->close();
?>

<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$totalUsers = 0;
$appointmentsThisMonth = 0;
$assessmentsTaken = 0;
$eventsHeld = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($row = $result->fetch_assoc()) {
    $totalUsers = (int)$row["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM appointments 
    WHERE MONTH(appointment_date) = MONTH(CURDATE()) 
      AND YEAR(appointment_date) = YEAR(CURDATE())
");
if ($row = $result->fetch_assoc()) {
    $appointmentsThisMonth = (int)$row["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM mental_health_tests
");
if ($row = $result->fetch_assoc()) {
    $assessmentsTaken = (int)$row["total"];
}

$result = $conn->query("
    SELECT COUNT(*) AS total 
    FROM events 
    WHERE MONTH(event_date) = MONTH(CURDATE()) 
      AND YEAR(event_date) = YEAR(CURDATE())
");
if ($row = $result->fetch_assoc()) {
    $eventsHeld = (int)$row["total"];
}

$recentActivity = [];

// recent registered users
$result = $conn->query("
    SELECT full_name, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 2
");
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = [
        "action" => "New user registered",
        "detail" => $row["full_name"] . " â€” " . $row["role"],
        "time" => $row["created_at"]
    ];
}

// recent appointments
$result = $conn->query("
    SELECT u.full_name AS student, a.counselor, a.created_at
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 2
");
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = [
        "action" => "Appointment booked",
        "detail" => $row["student"] . " with " . $row["counselor"],
        "time" => $row["created_at"]
    ];
}

// recent assessments
$result = $conn->query("
    SELECT u.full_name AS student, m.result_text, m.created_at
    FROM mental_health_tests m
    INNER JOIN users u ON m.user_id = u.id
    ORDER BY m.created_at DESC
    LIMIT 2
");
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = [
        "action" => "Assessment submitted",
        "detail" => $row["student"],
        "time" => $row["created_at"]
    ];
}

// recent events
$result = $conn->query("
    SELECT title, created_at
    FROM events
    ORDER BY created_at DESC
    LIMIT 2
");
while ($row = $result->fetch_assoc()) {
    $recentActivity[] = [
        "action" => "Event created",
        "detail" => $row["title"],
        "time" => $row["created_at"]
    ];
}

// sort all activity by time desc
usort($recentActivity, function ($a, $b) {
    return strtotime($b["time"]) - strtotime($a["time"]);
});

// keep top 6 only
$recentActivity = array_slice($recentActivity, 0, 6);

echo json_encode([
    "success" => true,
    "stats" => [
        "total_users" => $totalUsers,
        "appointments_this_month" => $appointmentsThisMonth,
        "assessments_taken" => $assessmentsTaken,
        "events_held" => $eventsHeld
    ],
    "recent_activity" => $recentActivity
]);

$conn->close();
?>

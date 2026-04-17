<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$studentsMonitored = 0;
$availableEvents = 0;
$studentOverview = [];

// Count students
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'Student'
");
if ($row = $result->fetch_assoc()) {
    $studentsMonitored = (int)$row["total"];
}

// Count available events
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE event_date >= CURDATE()
");
if ($row = $result->fetch_assoc()) {
    $availableEvents = (int)$row["total"];
}

// Student participation overview
$result = $conn->query("
    SELECT 
        users.full_name AS name,
        COUNT(appointments.id) AS sessions
    FROM users
    LEFT JOIN appointments ON users.id = appointments.user_id
    WHERE users.role = 'Student'
    GROUP BY users.id, users.full_name
    ORDER BY sessions DESC, users.full_name ASC
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    $sessions = (int)$row["sessions"];

    if ($sessions >= 5) {
        $status = "Follow-up";
    } else {
        $status = "Active";
    }

    $studentOverview[] = [
        "name" => $row["name"],
        "sessions" => $sessions,
        "status" => $status
    ];
}

echo json_encode([
    "success" => true,
    "stats" => [
        "students_monitored" => $studentsMonitored,
        "available_events" => $availableEvents
    ],
    "student_overview" => $studentOverview
]);

$conn->close();
?>

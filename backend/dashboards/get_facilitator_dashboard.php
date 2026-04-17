<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$thisMonthStart = date("Y-m-01");
$thisMonthEnd = date("Y-m-t");
$thisWeekStart = date("Y-m-d", strtotime("monday this week"));
$thisWeekEnd = date("Y-m-d", strtotime("sunday this week"));

$yourEvents = 0;
$totalParticipants = 0;
$thisWeek = 0;
$upcomingEvents = [];

// Total events this month
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE event_date BETWEEN '$thisMonthStart' AND '$thisMonthEnd'
");
if ($row = $result->fetch_assoc()) {
    $yourEvents = (int)$row["total"];
}

// Total participants (placeholder logic: use number of assessments as engagement count)
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM mental_health_tests
");
if ($row = $result->fetch_assoc()) {
    $totalParticipants = (int)$row["total"];
}

// Events this week
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM events
    WHERE event_date BETWEEN '$thisWeekStart' AND '$thisWeekEnd'
");
if ($row = $result->fetch_assoc()) {
    $thisWeek = (int)$row["total"];
}

// Upcoming events
$result = $conn->query("
    SELECT id, title, event_date, event_time, location
    FROM events
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC, event_time ASC
    LIMIT 5
");

while ($row = $result->fetch_assoc()) {
    $upcomingEvents[] = [
        "id" => $row["id"],
        "title" => $row["title"],
        "date" => date("M j, g:i A", strtotime($row["event_date"] . " " . $row["event_time"])),
        "participants" => 0
    ];
}

echo json_encode([
    "success" => true,
    "stats" => [
        "your_events" => $yourEvents,
        "total_participants" => $totalParticipants,
        "this_week" => $thisWeek
    ],
    "upcoming_events" => $upcomingEvents
]);

$conn->close();
?>

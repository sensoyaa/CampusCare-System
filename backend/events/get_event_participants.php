<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$sql = "
    SELECT 
        e.id AS event_id,
        e.title,
        e.event_date,
        e.event_time,
        u.id AS user_id,
        u.full_name,
        u.student_id
    FROM events e
    LEFT JOIN event_participants ep ON e.id = ep.event_id
    LEFT JOIN users u ON ep.user_id = u.id
    ORDER BY e.event_date ASC, e.event_time ASC, e.title ASC
";

$result = $conn->query($sql);

$events = [];

while ($row = $result->fetch_assoc()) {
    $eventId = $row["event_id"];

    if (!isset($events[$eventId])) {
        $dateTime = $row["event_date"] . " " . ($row["event_time"] ?? "00:00:00");

        $events[$eventId] = [
            "id" => (int)$row["event_id"],
            "title" => $row["title"],
            "date" => date("M j, Y", strtotime($dateTime)),
            "participants" => []
        ];
    }

    if (!empty($row["user_id"])) {
        $events[$eventId]["participants"][] = [
            "id" => $row["student_id"] ?: "N/A",
            "name" => $row["full_name"]
        ];
    }
}

echo json_encode([
    "success" => true,
    "events" => array_values($events)
]);

$conn->close();
?>
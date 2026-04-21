<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$result = $conn->query("SELECT id, title, event_date, event_time, location, description FROM events ORDER BY event_date ASC");

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

echo json_encode([
    "success" => true,
    "events" => $events
]);

$conn->close();

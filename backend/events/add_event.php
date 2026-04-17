<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);

$title = trim($data["title"] ?? "");
$event_date = trim($data["event_date"] ?? "");
$event_time = trim($data["event_time"] ?? "");
$location = trim($data["location"] ?? "");
$description = trim($data["description"] ?? "");

if ($title === "" || $event_date === "" || $event_time === "" || $location === "") {
    echo json_encode([
        "success" => false,
        "message" => "All required event fields must be filled out."
    ]);
    exit();
}

$stmt = $conn->prepare("INSERT INTO events (title, event_date, event_time, location, description) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $title, $event_date, $event_time, $location, $description);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Event added successfully."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to add event."
    ]);
}

$stmt->close();
$conn->close();

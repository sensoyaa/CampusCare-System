<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$counselor_id = intval($_GET["counselor_id"] ?? 0);
$date = trim($_GET["date"] ?? "");

if ($counselor_id <= 0 || $date === "") {
    echo json_encode([
        "success" => false,
        "message" => "Counselor ID and date are required."
    ]);
    exit();
}

$day = date("l", strtotime($date));

$stmt = $conn->prepare("
    SELECT start_time, end_time
    FROM counselor_availability
    WHERE counselor_id = ?
      AND day = ?
    ORDER BY start_time ASC
");

$stmt->bind_param("is", $counselor_id, $day);
$stmt->execute();
$result = $stmt->get_result();

$availableRanges = [];

while ($row = $result->fetch_assoc()) {
    $availableRanges[] = [
        "from" => $row["start_time"],
        "to" => $row["end_time"]
    ];
}

$allSlots = [
    "8:00 AM",
    "9:00 AM",
    "10:00 AM",
    "11:00 AM",
    "12:00 PM",
    "1:00 PM",
    "2:00 PM",
    "3:00 PM",
    "4:00 PM",
    "5:00 PM"
];

function timeToMinutes($time) {
    return intval(date("H", strtotime($time))) * 60 + intval(date("i", strtotime($time)));
}

$slots = [];

foreach ($availableRanges as $range) {
    $fromMinutes = timeToMinutes($range["from"]);
    $toMinutes = timeToMinutes($range["to"]);

    foreach ($allSlots as $slot) {
        $slotMinutes = timeToMinutes($slot);

        if ($slotMinutes >= $fromMinutes && $slotMinutes <= $toMinutes) {
            if (!in_array($slot, $slots)) {
                $slots[] = $slot;
            }
        }
    }
}

echo json_encode([
    "success" => true,
    "day" => $day,
    "slots" => $slots
]);

$stmt->close();
$conn->close();
?>
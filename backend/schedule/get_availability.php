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

$stmt = $conn->prepare("
    SELECT id, day, start_time, end_time
    FROM counselor_availability
    WHERE counselor_id = ?
    ORDER BY 
      FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
      start_time ASC
");

$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

$availability = [];

while ($row = $result->fetch_assoc()) {
    $availability[] = [
        "id" => (int)$row["id"],
        "day" => $row["day"],
        "from" => $row["start_time"],
        "to" => $row["end_time"]
    ];
}

echo json_encode([
    "success" => true,
    "availability" => $availability
]);

$stmt->close();
$conn->close();
?>
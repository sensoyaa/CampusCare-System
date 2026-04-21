<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$sql = "
SELECT 
    appointments.id,
    users.full_name AS student,
    appointments.counselor,
    appointments.appointment_date,
    appointments.appointment_time,
    appointments.service AS type,
    appointments.status
FROM appointments
INNER JOIN users ON appointments.user_id = users.id
ORDER BY appointments.appointment_date ASC, appointments.appointment_time ASC
";

$result = $conn->query($sql);

$appointments = [];

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

echo json_encode([
    "success" => true,
    "appointments" => $appointments
]);

$conn->close();
?>

<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$students = [];

$sql = "
SELECT 
    u.id,
    u.full_name,
    u.student_id,
    COUNT(a.id) AS sessions,
    MAX(a.appointment_date) AS last_visit
FROM users u
LEFT JOIN appointments a ON u.id = a.user_id
WHERE u.role = 'Student'
GROUP BY u.id, u.full_name, u.student_id
ORDER BY u.full_name ASC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $sessions = (int)$row["sessions"];
    $lastVisit = $row["last_visit"];

    if ($sessions >= 5) {
        $status = "Follow-up";
    } elseif ($sessions > 0) {
        $status = "Active";
    } else {
        $status = "No sessions";
    }

    $students[] = [
        "name" => $row["full_name"],
        "id" => $row["student_id"],
        "sessions" => $sessions,
        "lastVisit" => $lastVisit ? date("M j", strtotime($lastVisit)) : "â€”",
        "status" => $status
    ];
}

echo json_encode([
    "success" => true,
    "students" => $students
]);

$conn->close();
?>

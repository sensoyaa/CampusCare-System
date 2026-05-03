<?php
require_once __DIR__ . "/../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../includes/db.php";

header("Content-Type: application/json");

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

// Only allow counselors and administrators to view participants
if (!in_array($role, ["Administrator", "Counselor"], true)) {
    echo json_encode([
        "success" => false,
        "message" => "You do not have permission to view participants."
    ]);
    exit;
}

$eventId = isset($_GET["event_id"]) ? intval($_GET["event_id"]) : 0;

if ($eventId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid event ID."
    ]);
    exit;
}

try {
    // Get all participants for the event with their check-in status
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.role,
            ec.checked_in_at IS NOT NULL AS checked_in
        FROM event_participants ep
        INNER JOIN users u ON ep.user_id = u.id
        LEFT JOIN event_checkins ec ON ep.event_id = ec.event_id AND ep.user_id = ec.user_id
        WHERE ep.event_id = ?
        ORDER BY u.full_name ASC
    ");

    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();

    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = [
            "id" => $row["id"],
            "full_name" => $row["full_name"],
            "role" => $row["role"],
            "checked_in" => (bool)$row["checked_in"]
        ];
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "participants" => $participants
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to load participants: " . $e->getMessage()
    ]);
}
?>
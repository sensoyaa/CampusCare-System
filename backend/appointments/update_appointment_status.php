<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/mail.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);
$status = trim($data["status"] ?? "");

$allowedStatuses = ["Pending", "Approved", "Cancelled", "Rejected"];

if ($id <= 0 || $status === "") {
    echo json_encode([
        "success" => false,
        "message" => "Appointment ID and status are required."
    ]);
    exit();
}

if (!in_array($status, $allowedStatuses)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid status."
    ]);
    exit();
}
session_start();

$sessionRole = $_SESSION["role"] ?? null;
$sessionUserId = intval($_SESSION["user_id"] ?? 0);
$sessionFullName = $_SESSION["full_name"] ?? null;

// If approving, only counselors may approve and we also save the approving counselor info
if ($status === "Approved") {
    if ($sessionRole !== "Counselor") {
        echo json_encode([
            "success" => false,
            "message" => "Only counselors are allowed to approve appointments."
        ]);
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE appointments SET status = ?, counselor_id = ?, counselor = ?, approved_by_user_id = ?, approved_at = NOW() WHERE id = ?"
    );

    $stmt->bind_param("sisii", $status, $sessionUserId, $sessionFullName, $sessionUserId, $id);
} else {
    // For other status changes, enforce role-based rules:
    // - Rejected: only the assigned counselor may reject
    // - Cancelled: only the assigned counselor or the student who booked may cancel

    $sel = $conn->prepare("SELECT user_id, counselor_id FROM appointments WHERE id = ? LIMIT 1");
    if (!$sel) {
        echo json_encode(["success" => false, "message" => "Failed to validate appointment."]);
        exit();
    }
    $sel->bind_param("i", $id);
    $sel->execute();
    $aptRow = $sel->get_result()->fetch_assoc();
    $sel->close();

    $aptUserId = intval($aptRow['user_id'] ?? 0);
    $aptCounselorId = intval($aptRow['counselor_id'] ?? 0);

    if ($status === 'Rejected') {
        if ($sessionRole !== 'Counselor' || $sessionUserId !== $aptCounselorId) {
            echo json_encode(["success" => false, "message" => "Only the assigned counselor may decline appointments."]);
            exit();
        }
    }

    if ($status === 'Cancelled') {
        $isStudent = ($sessionUserId === $aptUserId);
        $isAssignedCounselor = ($sessionRole === 'Counselor' && $sessionUserId === $aptCounselorId);
        if (!($isStudent || $isAssignedCounselor)) {
            echo json_encode(["success" => false, "message" => "Only the assigned counselor or the student may cancel appointments."]);
            exit();
        }
    }

    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
}

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Insert audit entry
        $action = $status;
        $meta = json_encode(["by_user_id" => $sessionUserId]);
        $auditStmt = $conn->prepare("INSERT INTO appointment_audit (appointment_id, user_id, action, metadata) VALUES (?, ?, ?, ?)");
        if ($auditStmt) {
            $auditStmt->bind_param("iiss", $id, $sessionUserId, $action, $meta);
            $auditStmt->execute();
            $auditStmt->close();
        }

        // Load appointment + user data for notifications
        $uStmt = $conn->prepare("SELECT a.user_id AS student_id, su.email AS student_email, su.full_name AS student_name, a.counselor_id, cu.email AS counselor_email, cu.full_name AS counselor_name, a.appointment_date, a.appointment_time FROM appointments a LEFT JOIN users su ON su.id = a.user_id LEFT JOIN users cu ON cu.id = a.counselor_id WHERE a.id = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param("i", $id);
            $uStmt->execute();
            $row = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();

            $studentEmail = $row['student_email'] ?? '';
            $studentName = $row['student_name'] ?? '';
            $counselorEmail = $row['counselor_email'] ?? '';
            $counselorName = $row['counselor_name'] ?? '';
            $displayDate = isset($row['appointment_date']) ? date('F j, Y', strtotime($row['appointment_date'])) : '';
            $displayTime = isset($row['appointment_time']) ? date('g:i A', strtotime($row['appointment_time'])) : '';

            if ($status === 'Rejected') {
                if ($studentEmail !== '') {
                    $html = campuscare_email_template(
                        'Appointment Declined',
                        'Your appointment request was declined by the counselor.',
                        "<p>Hello " . htmlspecialchars($studentName) . ",</p><p>Your appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was declined by the counselor.</p>",
                        []
                    );
                    send_smtp_mail($studentEmail, $studentName, 'Appointment Declined', $html, '');
                }
            }

            if ($status === 'Cancelled') {
                if ($studentEmail !== '') {
                    $html = campuscare_email_template(
                        'Appointment Cancelled',
                        'Your appointment was cancelled.',
                        "<p>Hello " . htmlspecialchars($studentName) . ",</p><p>Your appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was cancelled.</p>",
                        []
                    );
                    send_smtp_mail($studentEmail, $studentName, 'Appointment Cancelled', $html, '');
                }
                if ($counselorEmail !== '') {
                    $html = campuscare_email_template(
                        'Appointment Cancelled',
                        'An appointment assigned to you was cancelled.',
                        "<p>Hello " . htmlspecialchars($counselorName) . ",</p><p>The appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was cancelled.</p>",
                        []
                    );
                    send_smtp_mail($counselorEmail, $counselorName, 'Assigned Appointment Cancelled', $html, '');
                }
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Appointment status updated successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No appointment was updated. Check appointment ID."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to update appointment status."
    ]);
}

$stmt->close();
$conn->close();
?>
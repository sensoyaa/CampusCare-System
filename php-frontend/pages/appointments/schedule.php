<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../../backend/config/appointment_audit.php";
require_once __DIR__ . "/../../../backend/config/mail.php";

$pageTitle = "Schedule";

$userId = intval($_SESSION["user_id"] ?? 0);
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "";

$error = "";
$success = "";
$shouldOpenAdjustModal = false;
$adjustAppointmentId = 0;
$adjustStudentName = "";
$adjustCurrentCounselorId = 0;
$adjustCurrentDate = "";
$adjustCurrentTime = "";
$timeOptions = [
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

function schedule_format_time_label(string $timeValue): string
{
    return date("g:i A", strtotime($timeValue));
}

function schedule_time_label_to_sql(string $timeLabel): string
{
    return date("H:i:s", strtotime($timeLabel));
}

function schedule_counselor_has_availability(mysqli $conn, int $counselorId, string $appointmentDate, string $appointmentTimeSql): bool
{
    $dayName = date("l", strtotime($appointmentDate));
    $stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM counselor_availability
        WHERE counselor_id = ? AND day = ?
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("is", $counselorId, $dayName);
    $stmt->execute();
    $result = $stmt->get_result();
    $slotTime = strtotime($appointmentTimeSql);
    $hasAvailability = false;

    while ($row = $result->fetch_assoc()) {
        $start = strtotime((string) $row["start_time"]);
        $end = strtotime((string) $row["end_time"]);

        if ($slotTime >= $start && $slotTime <= $end) {
            $hasAvailability = true;
            break;
        }
    }

    $stmt->close();

    return $hasAvailability;
}

function schedule_slot_is_taken(mysqli $conn, int $counselorId, string $appointmentDate, string $appointmentTimeSql, int $excludeAppointmentId): bool
{
    $stmt = $conn->prepare("
        SELECT id
        FROM appointments
        WHERE counselor_id = ?
          AND appointment_date = ?
          AND appointment_time = ?
          AND id != ?
          AND COALESCE(NULLIF(status, ''), 'Pending') NOT IN ('Cancelled', 'Rejected')
        LIMIT 1
    ");

    if (!$stmt) {
        return true;
    }

    $stmt->bind_param("issi", $counselorId, $appointmentDate, $appointmentTimeSql, $excludeAppointmentId);
    $stmt->execute();
    $isTaken = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    return $isTaken;
}

$activeCounselors = [];

if ($role === "Counselor") {
    $activeCounselorResult = $conn->query("
        SELECT id, full_name, email
        FROM users
        WHERE role IN ('Counselor', 'Counsellor', 'Counselors')
          AND status = 'Active'
        ORDER BY full_name ASC
    ");

    while ($row = $activeCounselorResult->fetch_assoc()) {
        $activeCounselors[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));
    $appointmentId = intval($_POST["appointment_id"] ?? 0);
    $status = trim((string) ($_POST["status"] ?? ""));

    if ($role === "Counselor" && $action === "update_status") {
        $allowedStatuses = ["Approved", "Rejected", "Cancelled"];

        if ($appointmentId <= 0 || !in_array($status, $allowedStatuses, true)) {
            $error = "Invalid appointment update request.";
        } else {
            // Ensure counselor info is also stored when a counselor updates status (e.g., approves)
            $stmt = $conn->prepare("
                UPDATE appointments
                SET status = ?, counselor_id = ?, counselor = ?
                WHERE id = ? AND counselor_id = ?
            ");
            if ($status === "Approved") {
                $stmt = $conn->prepare("\n                UPDATE appointments\n                SET status = ?, counselor_id = ?, counselor = ?, approved_by_user_id = ?, approved_at = NOW()\n                WHERE id = ? AND counselor_id = ?\n            ");
                $stmt->bind_param("sisiii", $status, $userId, $fullName, $userId, $appointmentId, $userId);
            } else {
                $stmt->bind_param("sisis", $status, $userId, $fullName, $appointmentId, $userId);
            }

            if ($stmt->execute()) {
                // Insert audit entry
                appointment_audit_insert($conn, $appointmentId, $userId, $status, ['by' => $fullName]);

                // Notify student/counselor depending on action
                $p = $conn->prepare("SELECT a.user_id AS student_id, su.email AS student_email, su.full_name AS student_name, a.counselor_id, cu.email AS counselor_email, cu.full_name AS counselor_name, a.appointment_date, a.appointment_time FROM appointments a LEFT JOIN users su ON su.id = a.user_id LEFT JOIN users cu ON cu.id = a.counselor_id WHERE a.id = ? LIMIT 1");
                if ($p) {
                    $p->bind_param("i", $appointmentId);
                    $p->execute();
                    $row = $p->get_result()->fetch_assoc();
                    $p->close();

                    $studentEmail = $row['student_email'] ?? '';
                    $studentName = $row['student_name'] ?? '';
                    $counselorEmail = $row['counselor_email'] ?? '';
                    $counselorName = $row['counselor_name'] ?? '';
                    $displayDate = isset($row['appointment_date']) ? date('F j, Y', strtotime($row['appointment_date'])) : '';
                    $displayTime = isset($row['appointment_time']) ? date('g:i A', strtotime($row['appointment_time'])) : '';

                    if ($status === 'Rejected') {
                        if ($studentEmail !== '') {
                            $html = campuscare_email_template('Appointment Declined', 'Your appointment request was declined by the counselor.', "<p>Hello " . htmlspecialchars($studentName) . ",</p><p>Your appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was declined by the counselor.</p>", []);
                            send_smtp_mail($studentEmail, $studentName, 'Appointment Declined', $html, '');
                        }
                    }

                    if ($status === 'Cancelled') {
                        if ($studentEmail !== '') {
                            $html = campuscare_email_template('Appointment Cancelled', 'Your appointment was cancelled.', "<p>Hello " . htmlspecialchars($studentName) . ",</p><p>Your appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was cancelled.</p>", []);
                            send_smtp_mail($studentEmail, $studentName, 'Appointment Cancelled', $html, '');
                        }
                        if ($counselorEmail !== '') {
                            $html = campuscare_email_template('Appointment Cancelled', 'An appointment assigned to you was cancelled.', "<p>Hello " . htmlspecialchars($counselorName) . ",</p><p>The appointment on <strong>" . htmlspecialchars($displayDate) . " at " . htmlspecialchars($displayTime) . "</strong> was cancelled.</p>", []);
                            send_smtp_mail($counselorEmail, $counselorName, 'Assigned Appointment Cancelled', $html, '');
                        }
                    }

                }

                $success = "Appointment status updated to " . $status . ".";
            } else {
                $error = "Failed to update appointment status.";
            }

            $stmt->close();
        }
    } elseif ($role === "Counselor" && $action === "reassign_or_reschedule") {
        $newCounselorId = intval($_POST["new_counselor_id"] ?? 0);
        $newDateInput = trim((string) ($_POST["new_appointment_date"] ?? ""));
        $newTimeInput = trim((string) ($_POST["new_appointment_time"] ?? ""));

        $shouldOpenAdjustModal = true;
        $adjustAppointmentId = $appointmentId;
        $adjustCurrentCounselorId = $newCounselorId;
        $adjustCurrentDate = $newDateInput;
        $adjustCurrentTime = $newTimeInput;

        if ($appointmentId <= 0) {
            $error = "Invalid appointment selected.";
        } else {
            $appointmentStmt = $conn->prepare("
                SELECT
                    a.id,
                    a.user_id,
                    a.counselor_id,
                    a.counselor,
                    a.service,
                    a.appointment_date,
                    a.appointment_time,
                    COALESCE(NULLIF(a.status, ''), 'Pending') AS status,
                    u.full_name AS student_name,
                    u.email AS student_email
                FROM appointments a
                INNER JOIN users u ON u.id = a.user_id
                WHERE a.id = ? AND a.counselor_id = ?
                LIMIT 1
            ");

            if (!$appointmentStmt) {
                $error = "Unable to load appointment details.";
            } else {
                $appointmentStmt->bind_param("ii", $appointmentId, $userId);
                $appointmentStmt->execute();
                $appointment = $appointmentStmt->get_result()->fetch_assoc();
                $appointmentStmt->close();

                if (!$appointment) {
                    $error = "Appointment not found.";
                } elseif (in_array($appointment["status"], ["Cancelled", "Rejected"], true)) {
                    $error = "This appointment can no longer be reassigned or rescheduled.";
                } else {
                    $adjustStudentName = trim((string) ($appointment["student_name"] ?? ""));
                    $currentCounselorId = intval($appointment["counselor_id"]);
                    $currentCounselorName = trim((string) ($appointment["counselor"] ?? ""));
                    $currentDate = trim((string) ($appointment["appointment_date"] ?? ""));
                    $currentTimeSql = trim((string) ($appointment["appointment_time"] ?? ""));
                    $currentTimeLabel = schedule_format_time_label($currentTimeSql);
                    $targetCounselorId = $newCounselorId > 0 ? $newCounselorId : $currentCounselorId;
                    $targetDate = $newDateInput !== "" ? $newDateInput : $currentDate;
                    $targetTimeLabel = $newTimeInput !== "" ? $newTimeInput : $currentTimeLabel;
                    $targetTimeSql = schedule_time_label_to_sql($targetTimeLabel);
                    $isReassigned = $targetCounselorId !== $currentCounselorId;
                    $isRescheduled = $targetDate !== $currentDate || $targetTimeSql !== $currentTimeSql;

                    if (!$isReassigned && !$isRescheduled) {
                        $error = "Choose a new counselor or a new date/time to update this appointment.";
                    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                        $error = "Please choose a valid date.";
                    } elseif (!in_array($targetTimeLabel, $timeOptions, true)) {
                        $error = "Please choose a valid counseling time.";
                    } elseif ($targetDate < date("Y-m-d")) {
                        $error = "Please choose a current or future date.";
                    } else {
                        $targetCounselor = null;

                        foreach ($activeCounselors as $counselorRow) {
                            if (intval($counselorRow["id"]) === $targetCounselorId) {
                                $targetCounselor = $counselorRow;
                                break;
                            }
                        }

                        if (!$targetCounselor) {
                            $error = "Please choose a valid active counselor.";
                        } elseif (!schedule_counselor_has_availability($conn, $targetCounselorId, $targetDate, $targetTimeSql)) {
                            $error = "The selected counselor is not available at that date and time.";
                        } elseif (schedule_slot_is_taken($conn, $targetCounselorId, $targetDate, $targetTimeSql, $appointmentId)) {
                            $error = "That counseling slot is already booked.";
                        } else {
                            $targetCounselorName = trim((string) ($targetCounselor["full_name"] ?? $currentCounselorName));
                            $targetCounselorEmail = trim((string) ($targetCounselor["email"] ?? ""));

                            $updateStmt = $conn->prepare("
                                UPDATE appointments
                                SET counselor_id = ?, counselor = ?, appointment_date = ?, appointment_time = ?, status = 'Pending'
                                WHERE id = ? AND counselor_id = ?
                            ");

                            if (!$updateStmt) {
                                $error = "Unable to update appointment.";
                            } else {
                                $updateStmt->bind_param(
                                    "isssii",
                                    $targetCounselorId,
                                    $targetCounselorName,
                                    $targetDate,
                                    $targetTimeSql,
                                    $appointmentId,
                                    $userId
                                );

                                if ($updateStmt->execute()) {
                                    $displayDate = date("F j, Y", strtotime($targetDate));
                                    $displayTime = schedule_format_time_label($targetTimeSql);
                                    $studentName = trim((string) ($appointment["student_name"] ?? "Student"));
                                    $studentEmail = trim((string) ($appointment["student_email"] ?? ""));
                                    $serviceName = trim((string) ($appointment["service"] ?? "Counseling"));

                                    if ($studentEmail !== "") {
                                        $changeLabel = $isReassigned && $isRescheduled
                                            ? "Your counselor updated your appointment and transferred you to another counselor."
                                            : ($isReassigned
                                                ? "Your appointment has been forwarded to another counselor."
                                                : "Your counselor updated your appointment schedule.");
                                        $htmlBody = campuscare_email_template(
                                            "Appointment Updated",
                                            "Your CampusCare appointment details have been updated by the counselor.",
                                            "
                                            <p style=\"margin:0 0 16px;\">Hello " . htmlspecialchars($studentName, ENT_QUOTES, "UTF-8") . ",</p>
                                            <p style=\"margin:0 0 24px;\">" . htmlspecialchars($changeLabel, ENT_QUOTES, "UTF-8") . "</p>
                                            <div style=\"margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2;\">
                                                <div style=\"font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:14px;\">Updated Appointment Details</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Service:</strong> " . htmlspecialchars($serviceName, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Counselor:</strong> " . htmlspecialchars($targetCounselorName, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Date:</strong> " . htmlspecialchars($displayDate, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Time:</strong> " . htmlspecialchars($displayTime, ENT_QUOTES, "UTF-8") . "</div>
                                                <div><strong>Status:</strong> Pending</div>
                                            </div>
                                            <p style=\"margin:0;\">Please review your updated appointment details in CampusCare.</p>
                                            ",
                                            [
                                                "preview" => "Your CampusCare appointment details were updated.",
                                                "footer" => "If you have concerns about the change, please contact the guidance office."
                                            ]
                                        );
                                        $textBody = "Hello {$studentName},\n\n{$changeLabel}\n\nService: {$serviceName}\nCounselor: {$targetCounselorName}\nDate: {$displayDate}\nTime: {$displayTime}\nStatus: Pending\n\nPlease review your updated appointment details in CampusCare.";
                                        send_smtp_mail($studentEmail, $studentName, "CampusCare Appointment Updated", $htmlBody, $textBody);
                                    }

                                    if ($isReassigned && $targetCounselorEmail !== "") {
                                        $htmlBody = campuscare_email_template(
                                            "Appointment Forwarded To You",
                                            "A student appointment was reassigned to you in CampusCare.",
                                            "
                                            <p style=\"margin:0 0 16px;\">Hello " . htmlspecialchars($targetCounselorName, ENT_QUOTES, "UTF-8") . ",</p>
                                            <p style=\"margin:0 0 24px;\">An appointment has been forwarded to you due to a counselor schedule change.</p>
                                            <div style=\"margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2;\">
                                                <div style=\"font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:14px;\">Appointment Details</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Student:</strong> " . htmlspecialchars($studentName, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Service:</strong> " . htmlspecialchars($serviceName, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Date:</strong> " . htmlspecialchars($displayDate, ENT_QUOTES, "UTF-8") . "</div>
                                                <div style=\"margin-bottom:10px;\"><strong>Time:</strong> " . htmlspecialchars($displayTime, ENT_QUOTES, "UTF-8") . "</div>
                                                <div><strong>Status:</strong> Pending</div>
                                            </div>
                                            <p style=\"margin:0;\">Please check your CampusCare schedule for the full appointment details.</p>
                                            ",
                                            [
                                                "preview" => "A student appointment was forwarded to you.",
                                                "footer" => "This notification was sent because the appointment was reassigned to you."
                                            ]
                                        );
                                        $textBody = "Hello {$targetCounselorName},\n\nAn appointment has been forwarded to you.\n\nStudent: {$studentName}\nService: {$serviceName}\nDate: {$displayDate}\nTime: {$displayTime}\nStatus: Pending\n\nPlease check your CampusCare schedule for the full appointment details.";
                                        send_smtp_mail($targetCounselorEmail, $targetCounselorName, "CampusCare Appointment Forwarded", $htmlBody, $textBody);
                                    }

                                    $success = $isReassigned && $isRescheduled
                                        ? "Appointment reassigned and rescheduled successfully."
                                        : ($isReassigned ? "Appointment forwarded to another counselor successfully." : "Appointment schedule updated successfully.");
                                    $shouldOpenAdjustModal = false;
                                } else {
                                    $error = "Failed to update appointment.";
                                }

                                $updateStmt->close();
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === "cancel_appointment") {
        if ($role === "Counselor") {
            $stmt = $conn->prepare("
                UPDATE appointments
                SET status = 'Cancelled'
                WHERE id = ? AND counselor_id = ?
            ");
            $stmt->bind_param("ii", $appointmentId, $userId);
        } else {
            $stmt = $conn->prepare("
                UPDATE appointments
                SET status = 'Cancelled'
                WHERE id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $appointmentId, $userId);
        }

        if ($stmt->execute()) {
            $success = "Appointment cancelled successfully.";
        } else {
            $error = "Failed to cancel appointment.";
        }

        $stmt->close();
    }
}

$appointments = [];

if ($role === "Counselor") {
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.service,
            a.counselor,
            a.appointment_date,
            a.appointment_time,
            COALESCE(NULLIF(a.status, ''), 'Pending') AS status,
            u.full_name AS student_name,
            u.student_id
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.counselor_id = ?
          AND u.role = 'Student'
          AND COALESCE(NULLIF(a.status, ''), 'Pending') != 'Cancelled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare("
        SELECT 
            id,
            service,
            counselor,
            appointment_date,
            appointment_time,
            COALESCE(NULLIF(status, ''), 'Pending') AS status
        FROM appointments
        WHERE user_id = ?
        ORDER BY appointment_date ASC, appointment_time ASC
    ");
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

$stmt->close();

$pendingAppointments = 0;
$approvedAppointments = 0;
$actionNeededAppointments = 0;

foreach ($appointments as $appointmentSummary) {
    $summaryStatus = trim((string) ($appointmentSummary["status"] ?? "Pending"));

    if ($summaryStatus === "Pending") {
        $pendingAppointments++;
    } elseif ($summaryStatus === "Approved") {
        $approvedAppointments++;
    }

    if ($role === "Counselor" && !in_array($summaryStatus, ["Cancelled", "Rejected"], true)) {
        $actionNeededAppointments++;
    }
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell schedule-shell">
            <div class="schedule-head">
            <h1 class="page-title">
                <?php echo $role === "Counselor" ? "View Appointments" : "My Schedule"; ?>
            </h1>

            <p class="page-subtitle">
                <?php echo $role === "Counselor" ? "Appointments assigned to you" : "Your upcoming appointments"; ?>
            </p>
            </div>

            <div class="schedule-summary-grid">
                <article class="schedule-summary-card">
                    <span class="schedule-summary-label">Total</span>
                    <strong><?php echo count($appointments); ?></strong>
                </article>
                <article class="schedule-summary-card">
                    <span class="schedule-summary-label">Pending</span>
                    <strong><?php echo $pendingAppointments; ?></strong>
                </article>
                <article class="schedule-summary-card">
                    <span class="schedule-summary-label">Approved</span>
                    <strong><?php echo $approvedAppointments; ?></strong>
                </article>
                <?php if ($role === "Counselor"): ?>
                    <article class="schedule-summary-card schedule-summary-card-accent">
                        <span class="schedule-summary-label">Active Cases</span>
                        <strong><?php echo $actionNeededAppointments; ?></strong>
                    </article>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($appointments)): ?>
                <div class="card" style="text-align: center; color: #5e6b75;">
                    No appointments found.
                </div>
            <?php else: ?>
                <div class="schedule-list">
                    <?php foreach ($appointments as $apt): ?>
                        <?php
                            $status = $apt["status"] ?: "Pending";
                            $statusClass = "status-pending";

                            if ($status === "Approved") {
                                $statusClass = "status-approved";
                            } elseif ($status === "Cancelled" || $status === "Rejected") {
                                $statusClass = "status-cancelled";
                            }

                            $title = "";
                            if ($role === "Counselor") {
                                $title = $apt["service"] . " with " . ($apt["student_name"] ?? "Student");
                            } else {
                                $title = $apt["service"] . " with " . $apt["counselor"];
                            }

                            $dateTime = date("D, F j \a\t g:i A", strtotime($apt["appointment_date"] . " " . $apt["appointment_time"]));
                        ?>

                        <article class="schedule-item schedule-item-clean" onclick="window.location.href='/campuscare-api/php-frontend/pages/appointments/appointment_detail.php?id=<?php echo intval($apt["id"]); ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';" style="cursor: pointer;">
                            <div class="schedule-main">
                                <div class="schedule-left">
                                    <span class="schedule-icon"><?php echo $role === "Counselor" ? sidebarIconSvg("user") : sidebarIconSvg("calendar"); ?></span>
                                    <div>
                                        <div class="schedule-heading-row">
                                            <h2 class="schedule-title"><?php echo htmlspecialchars($title); ?></h2>
                                            <span class="status-pill <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </div>

                                        <div class="schedule-meta-row">
                                            <?php if ($role === "Counselor" && isset($apt["student_id"])): ?>
                                                <span class="schedule-meta-chip">Student ID: <?php echo htmlspecialchars($apt["student_id"]); ?></span>
                                            <?php endif; ?>
                                            <span class="schedule-meta-chip"><?php echo htmlspecialchars($dateTime); ?></span>
                                            <span class="schedule-meta-chip">Guidance Office</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="schedule-actions schedule-actions-clean" onclick="event.stopPropagation();">
                                <div class="schedule-action-group schedule-action-group-primary">

                                <?php if ($role === "Counselor" && $status === "Pending"): ?>
                                    <form
                                        method="POST"
                                        class="inline-form"
                                        data-confirm-title="Approve appointment"
                                        data-confirm-message="Approve this appointment request for the assigned student?"
                                        data-confirm-button="Approve"
                                    >
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo intval($apt["id"]); ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" class="btn event-join-btn btn-sm">Approve</button>
                                    </form>

                                    <form
                                        method="POST"
                                        class="inline-form"
                                        data-confirm-title="Decline appointment"
                                        data-confirm-message="Decline this appointment request?"
                                        data-confirm-button="Decline"
                                        data-confirm-variant="danger"
                                    >
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo intval($apt["id"]); ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="btn event-join-btn btn-outline btn-sm">Decline</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($role === "Counselor" && $status !== "Cancelled" && $status !== "Rejected"): ?>
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        data-open-adjust-modal
                                        data-appointment-id="<?php echo intval($apt["id"]); ?>"
                                        data-student-name="<?php echo htmlspecialchars((string) ($apt["student_name"] ?? "Student"), ENT_QUOTES); ?>"
                                        data-counselor-id="<?php echo $role === 'Counselor' ? intval($userId) : 0; ?>"
                                        data-date="<?php echo htmlspecialchars((string) $apt["appointment_date"], ENT_QUOTES); ?>"
                                        data-time="<?php echo htmlspecialchars(schedule_format_time_label((string) $apt["appointment_time"]), ENT_QUOTES); ?>"
                                    >
                                        Reassign / Reschedule
                                    </button>
                                <?php endif; ?>
                                </div>

                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($role === "Counselor"): ?>
            <div class="modal-overlay<?php echo $shouldOpenAdjustModal ? " open" : ""; ?>" id="adjustAppointmentModal" aria-hidden="<?php echo $shouldOpenAdjustModal ? "false" : "true"; ?>">
                <div class="modal-card">
                    <div class="modal-head">
                        <h3>Reassign or Reschedule Appointment</h3>
                        <button type="button" class="modal-close" data-close-adjust-modal aria-label="Close">&times;</button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="reassign_or_reschedule">
                        <input type="hidden" name="appointment_id" id="adjustAppointmentId" value="<?php echo intval($adjustAppointmentId); ?>">

                        <p class="page-subtitle" style="margin-bottom: 18px;">
                            Update the counselor or the counseling time for <strong id="adjustStudentName"><?php echo htmlspecialchars($adjustStudentName !== "" ? $adjustStudentName : "the selected student"); ?></strong>.
                        </p>

                        <div class="form-group">
                            <label for="new_counselor_id">Forward To Another Counselor</label>
                            <select id="new_counselor_id" name="new_counselor_id">
                                <option value="0">Keep current counselor</option>
                                <?php foreach ($activeCounselors as $counselorOption): ?>
                                    <option value="<?php echo intval($counselorOption["id"]); ?>" <?php echo intval($adjustCurrentCounselorId) === intval($counselorOption["id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars((string) $counselorOption["full_name"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="modal-grid">
                            <div class="form-group">
                                <label for="new_appointment_date">New Date</label>
                                <input id="new_appointment_date" type="date" name="new_appointment_date" value="<?php echo htmlspecialchars($adjustCurrentDate); ?>" min="<?php echo date("Y-m-d"); ?>">
                            </div>

                            <div class="form-group">
                                <label for="new_appointment_time">New Time</label>
                                <select id="new_appointment_time" name="new_appointment_time">
                                    <option value="">Keep current time</option>
                                    <?php foreach ($timeOptions as $timeOption): ?>
                                        <option value="<?php echo htmlspecialchars($timeOption); ?>" <?php echo $adjustCurrentTime === $timeOption ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($timeOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-outline" data-close-adjust-modal>Cancel</button>
                            <button type="submit" class="btn">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

<style>
    .schedule-head {
        padding: 30px 34px 20px;
        background: var(--primary);
        color: #fff;
        border-radius: 22px;
        margin-bottom: 1rem;
        box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    }

    .schedule-head .page-title {
        margin: 0 0 8px;
        font-size: 34px;
        color: #fff;
    }

    .schedule-head .page-subtitle {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, 0.9);
    }

    .schedule-item.schedule-item-clean {
        transition: all 0.2s ease;
    }

    .schedule-item.schedule-item-clean:hover {
        box-shadow: 0 4px 16px rgba(0, 102, 204, 0.15) !important;
        transform: translateY(-2px);
        background: #f9faff;
    }
</style>

<?php if ($role === "Counselor"): ?>
<script>
(function () {
    const modal = document.getElementById("adjustAppointmentModal");
    const appointmentIdInput = document.getElementById("adjustAppointmentId");
    const studentNameLabel = document.getElementById("adjustStudentName");
    const counselorSelect = document.getElementById("new_counselor_id");
    const dateInput = document.getElementById("new_appointment_date");
    const timeSelect = document.getElementById("new_appointment_time");
    const openButtons = document.querySelectorAll("[data-open-adjust-modal]");
    const closeButtons = modal ? modal.querySelectorAll("[data-close-adjust-modal]") : [];

    if (!modal || !appointmentIdInput || !studentNameLabel || !counselorSelect || !dateInput || !timeSelect) {
        return;
    }

    function openModal() {
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            appointmentIdInput.value = button.getAttribute("data-appointment-id") || "";
            studentNameLabel.textContent = button.getAttribute("data-student-name") || "the selected student";
            counselorSelect.value = "0";
            dateInput.value = button.getAttribute("data-date") || "";
            timeSelect.value = "";
            openModal();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
</script>
<?php endif; ?>

</div>
<script>
(function () {
    const profileMenuToggle = document.querySelector(".profile-menu-toggle");
    const profileDropdown = document.querySelector(".profile-dropdown");

    if (!profileMenuToggle || !profileDropdown) {
        return;
    }

    profileMenuToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        const parent = profileMenuToggle.closest(".topbar-user");
        const isOpen = parent.classList.toggle("is-open");
        profileMenuToggle.setAttribute("aria-expanded", isOpen);
    });

    document.addEventListener("click", function () {
        const parent = profileMenuToggle.closest(".topbar-user");
        if (parent) parent.classList.remove("is-open");
        profileMenuToggle.setAttribute("aria-expanded", "false");
    });

    profileDropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });
})();
</script>
</body>
</html>


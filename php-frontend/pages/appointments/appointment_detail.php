<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Appointment Details";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "User";

$appointmentId = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

if ($appointmentId <= 0) {
    header("Location: /campuscare-api/php-frontend/pages/appointments/book_appointment.php");
    exit();
}

// Get appointment details
$appointmentStmt = $conn->prepare("
    SELECT a.*, u.full_name as student_name, u.email as student_email, u.id as student_id
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
");
$appointmentStmt->bind_param("i", $appointmentId);
$appointmentStmt->execute();
$appointment = $appointmentStmt->get_result()->fetch_assoc();
$appointmentStmt->close();

if (!$appointment) {
    header("Location: /campuscare-api/php-frontend/pages/appointments/book_appointment.php");
    exit();
}

// Check permissions - only student with appointment, assigned counselor, or admin can view
$canView = ($role === "Administrator" || 
            ($role === "Counselor" && $appointment["counselor"] === $fullName) ||
            ($userId === $appointment["user_id"]));

if (!$canView) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

// Format appointment details
$appointmentDate = new DateTime($appointment["appointment_date"]);
$dateStr = $appointmentDate->format("F j, Y");
$timeStr = $appointment["appointment_time"] ?? "Not specified";
$service = htmlspecialchars($appointment["service"] ?? "Counseling");
$counselor = htmlspecialchars($appointment["counselor"] ?? "Not assigned");
$status = htmlspecialchars($appointment["status"] ?? "Pending");
$notes = htmlspecialchars($appointment["notes"] ?? "");

// Calculate status display
$currentTime = time();
$appointmentDateTime = strtotime($appointment["appointment_date"] . " " . $appointment["appointment_time"]);
$hasAppointmentPassed = $currentTime > $appointmentDateTime;

// Get student's appointment history (for counselor view)
$appointmentHistory = [];


// Get related referrals for this student (for counselor view)
$relatedReferrals = [];
if ($role === "Counselor" || $role === "Administrator") {
    $referralStmt = $conn->prepare("
        SELECT id, reasons_json, status, referral_datetime
        FROM referral_forms
        WHERE student_user_id = ?
        ORDER BY referral_datetime DESC
        LIMIT 3
    ");
    $referralStmt->bind_param("i", $appointment["user_id"]);
    $referralStmt->execute();
    $referralResult = $referralStmt->get_result();
    while ($row = $referralResult->fetch_assoc()) {
        $relatedReferrals[] = $row;
    }
    $referralStmt->close();
}

// Handle form submissions
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    // Cancel appointment
    if ($action === "cancel" && (($role === "Student" && $userId === $appointment["user_id"]) || $role === "Administrator")) {
        if (!$hasAppointmentPassed && $status !== "Cancelled") {
            $updateStmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
            $updateStmt->bind_param("i", $appointmentId);
            if ($updateStmt->execute()) {
                $success = "Appointment has been cancelled.";
                $status = "Cancelled";
            } else {
                $error = "Failed to cancel appointment. Please try again.";
            }
            $updateStmt->close();
        } else {
            $error = "You cannot cancel an appointment that has already passed.";
        }
    }

    // Save counselor notes
    if ($action === "save_notes" && ($role === "Counselor" || $role === "Administrator")) {
        $appointmentNotes = trim((string) ($_POST["appointment_notes"] ?? ""));
        $updateStmt = $conn->prepare("UPDATE appointments SET notes = ? WHERE id = ?");
        $updateStmt->bind_param("si", $appointmentNotes, $appointmentId);
        if ($updateStmt->execute()) {
            $success = "Notes saved successfully.";
            $notes = htmlspecialchars($appointmentNotes);
        } else {
            $error = "Failed to save notes. Please try again.";
        }
        $updateStmt->close();
    }

    // Mark as completed
    if ($action === "mark_completed" && ($role === "Counselor" || $role === "Administrator")) {
        if ($status !== "Completed" && $status !== "Cancelled") {
            $updateStmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
            $updateStmt->bind_param("i", $appointmentId);
            if ($updateStmt->execute()) {
                $success = "Appointment marked as completed.";
                $status = "Completed";
            } else {
                $error = "Failed to update appointment status.";
            }
            $updateStmt->close();
        }
    }
}

function getStatusBadgeClass($status) {
    $classes = [
        "Pending" => "status-pending",
        "Approved" => "status-approved",
        "Cancelled" => "status-cancelled",
        "Rejected" => "status-cancelled",
        "Completed" => "status-completed",
    ];
    return $classes[$status] ?? "status-pending";
}

function getStatusIcon($status) {
    $icons = [
        "Pending" => "clock",
        "Approved" => "check-circle",
        "Cancelled" => "x-circle",
        "Rejected" => "x-circle",
        "Completed" => "check-circle",
    ];
    return $icons[$status] ?? "clock";
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

    <div class="content appointment-detail-root">
        <div class="event-detail-page">
            <!-- Back Button -->
            <a href="/campuscare-api/php-frontend/pages/appointments/book_appointment.php" class="btn btn-outline event-back-link">
                <?php echo sidebarIconSvg("arrow-left"); ?>
                Back to Appointments
            </a>

            <?php if ($error !== ""): ?>
                <div class="appointment-alert appointment-alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="appointment-alert appointment-alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Appointment Header -->
            <header class="event-detail-header">
                <div class="event-detail-header-main">
                    <div class="event-detail-kicker">
                        <span class="event-detail-kicker-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <span><?php echo $service; ?></span>
                    </div>
                    <h1 class="event-detail-title">Appointment with <?php echo $counselor; ?></h1>
                    <p class="event-detail-subtitle">View your appointment details, time, and status.</p>
                </div>

                <div class="event-detail-status">
                    <span class="status-badge <?php echo getStatusBadgeClass($status); ?>">
                        <?php echo sidebarIconSvg(getStatusIcon($status)); ?>
                        <?php echo $status; ?>
                    </span>
                </div>
            </header>

            <!-- Appointment Meta Information -->
            <section class="event-detail-meta-grid">
                <div class="event-meta-card">
                    <span class="event-meta-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                    <div>
                        <span class="event-meta-label">Date</span>
                        <span class="event-meta-value"><?php echo htmlspecialchars($dateStr); ?></span>
                    </div>
                </div>
                <div class="event-meta-card">
                    <span class="event-meta-icon"><?php echo sidebarIconSvg("clock"); ?></span>
                    <div>
                        <span class="event-meta-label">Time</span>
                        <span class="event-meta-value"><?php echo htmlspecialchars($timeStr); ?></span>
                    </div>
                </div>
                <div class="event-meta-card">
                    <span class="event-meta-icon"><?php echo sidebarIconSvg("user"); ?></span>
                    <div>
                        <span class="event-meta-label">Counselor</span>
                        <span class="event-meta-value"><?php echo $counselor; ?></span>
                    </div>
                </div>
                <div class="event-meta-card">
                    <span class="event-meta-icon"><?php echo sidebarIconSvg("briefcase"); ?></span>
                    <div>
                        <span class="event-meta-label">Service Type</span>
                        <span class="event-meta-value"><?php echo $service; ?></span>
                    </div>
                </div>
            </section>

            <!-- Appointment Details -->
            <section class="event-detail-description">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("message"); ?></span>
                    <div>
                        <h2>Appointment Details</h2>
                        <p class="section-heading-subtext">Complete information about your appointment.</p>
                    </div>
                </div>
                
                <div class="appointment-info-card">
                    <div class="appointment-info-row">
                        <strong class="appointment-info-label">Student:</strong>
                        <span class="appointment-info-value"><?php echo htmlspecialchars($appointment["student_name"]); ?></span>
                    </div>
                    <div class="appointment-info-row">
                        <strong class="appointment-info-label">Student Email:</strong>
                        <span class="appointment-info-value"><?php echo htmlspecialchars($appointment["student_email"]); ?></span>
                    </div>
                    <?php if (!empty($notes)): ?>
                    <div class="appointment-info-row appointment-info-row-last">
                        <strong class="appointment-info-label">Notes:</strong>
                        <span class="appointment-info-value"><?php echo nl2br($notes); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Counselor Preparation (Student View) -->
            <?php if ($role === "Student" && $userId === $appointment["user_id"]): ?>
            <section class="appointment-section">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("clipboard"); ?></span>
                    <div>
                        <h2>How to Prepare</h2>
                        <p class="section-heading-subtext">Tips to make the most of your appointment.</p>
                    </div>
                </div>
                <div class="appointment-prep-card">
                    <ul class="appointment-prep-list">
                        <li>Arrive 5-10 minutes before your scheduled time</li>
                        <li>Bring a valid ID and any relevant documents</li>
                        <li>Think about what you'd like to discuss beforehand</li>
                        <li>Find a quiet place for the meeting if it's virtual</li>
                        <li>Have pen and paper ready if you want to take notes</li>
                    </ul>
                </div>
            </section>
            <?php endif; ?>

            <!-- Counselor Notes Section (Counselor/Admin View) -->
            <?php if (in_array($role, ["Counselor", "Administrator"], true)): ?>
            <section class="appointment-section">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("edit"); ?></span>
                    <div>
                        <h2>Session Notes</h2>
                        <p class="section-heading-subtext">Add or update notes about this appointment.</p>
                    </div>
                </div>
                <form method="POST" class="appointment-notes-form">
                    <input type="hidden" name="action" value="save_notes">
                    <div class="appointment-notes-stack">
                        <textarea 
                            name="appointment_notes" 
                            class="appointment-notes-textarea"
                            placeholder="Add pre-appointment notes, observations, or follow-up items..."><?php echo $notes; ?></textarea>
                        <button type="submit" class="btn btn-primary appointment-notes-submit">
                            <?php echo sidebarIconSvg("save"); ?>
                            Save Notes
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <!-- Related Referrals (Counselor/Admin View) -->
            <?php if ((in_array($role, ["Counselor", "Administrator"], true)) && !empty($relatedReferrals)): ?>
            <section class="appointment-section">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("flag"); ?></span>
                    <div>
                        <h2>Related Referrals</h2>
                        <p class="section-heading-subtext">Referrals submitted for this student.</p>
                    </div>
                </div>
                <div class="appointment-referrals-list">
                    <?php foreach ($relatedReferrals as $ref): ?>
                        <?php
                            $refReasons = json_decode($ref["reasons_json"] ?? "[]", true) ?? [];
                            $reasonList = implode(", ", array_slice($refReasons, 0, 2));
                            if (count($refReasons) > 2) $reasonList .= " +more";
                        ?>
                        <div class="appointment-referral-card">
                            <div class="appointment-referral-top">
                                <div class="appointment-referral-main">
                                    <strong class="appointment-referral-title">Referral</strong>
                                    <div class="appointment-referral-reason">
                                        <?php echo htmlspecialchars($reasonList); ?>
                                    </div>
                                    <div class="appointment-referral-date">
                                        <?php echo date("F j, Y", strtotime($ref["referral_datetime"])); ?>
                                    </div>
                                </div>
                                <span class="status-badge appointment-referral-badge <?php echo getStatusBadgeClass($ref["status"]); ?>">
                                    <?php echo htmlspecialchars($ref["status"]); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Appointment Statistics (Admin/Counselor Only) -->
            <?php if (in_array($role, ["Administrator", "Counselor"], true)): ?>
            <?php endif; ?>

            <!-- Action Buttons -->
            <section class="event-detail-actions appointment-section">
                <?php if ($role === "Student" && $userId === $appointment["user_id"]): ?>
                    <!-- Student Actions -->
                    <?php if (!$hasAppointmentPassed && $status !== "Cancelled"): ?>
                        <form method="POST" class="appointment-inline-form">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-outline btn-large" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                <?php echo sidebarIconSvg("x"); ?>
                                Cancel Appointment
                            </button>
                        </form>

                        <a href="/campuscare-api/php-frontend/pages/appointments/book_appointment.php" class="btn btn-primary btn-large">
                            <?php echo sidebarIconSvg("calendar"); ?>
                            Reschedule Appointment
                        </a>
                    <?php elseif ($status === "Cancelled" || $hasAppointmentPassed): ?>
                        <button class="btn btn-disabled btn-large" disabled>
                            <?php echo sidebarIconSvg("check-circle"); ?>
                            Appointment Unavailable
                        </button>
                    <?php endif; ?>

                <?php elseif (in_array($role, ["Counselor", "Administrator"], true)): ?>
                    <!-- Counselor/Admin Actions -->
                    <?php if ($status === "Pending"): ?>
                        <a href="/campuscare-api/php-frontend/pages/appointments/schedule.php" class="btn btn-primary btn-large">
                            <?php echo sidebarIconSvg("check"); ?>
                            Approve Appointment
                        </a>
                    <?php elseif ($status === "Approved" && !$hasAppointmentPassed): ?>
                        <form method="POST" class="appointment-inline-form">
                            <input type="hidden" name="action" value="mark_completed">
                            <button type="submit" class="btn btn-primary btn-large">
                                <?php echo sidebarIconSvg("check-circle"); ?>
                                Mark as Completed
                            </button>
                        </form>

                        <a href="/campuscare-api/php-frontend/pages/appointments/schedule.php" class="btn btn-outline btn-large">
                            <?php echo sidebarIconSvg("edit"); ?>
                            Reschedule/Reassign
                        </a>
                    <?php endif; ?>

                    <?php if ($status !== "Cancelled"): ?>
                        <form method="POST" class="appointment-inline-form">
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-outline btn-large" onclick="return confirm('Cancel this appointment?');">
                                <?php echo sidebarIconSvg("x"); ?>
                                Cancel
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Important Information -->
            <section class="appointment-important-card appointment-section">
                <h3 class="appointment-important-title">📌 Important Information:</h3>
                <ul class="appointment-important-list">
                    <?php if ($role === "Student"): ?>
                        <li>Location: Guidance Office (Building A, 2nd Floor)</li>
                        <li>Arrive 5-10 minutes early to allow time for check-in</li>
                        <li>Bring a valid student ID if available</li>
                        <li>For cancellations, please cancel at least 24 hours in advance</li>
                        <li>If you have questions, contact the counselor directly</li>
                    <?php else: ?>
                        <li>Manage appointment status and notes in this view</li>
                        <li>Use the Notes section to document session details</li>
                        <li>Check the student's appointment history and referrals below</li>
                        <li>Mark as completed after the appointment ends</li>
                        <li>Email notifications are sent automatically when you make changes</li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>
    </div>
</main>

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

    <div class="content">
        <div class="event-detail-page">
            <!-- Back Button -->
            <a href="/campuscare-api/php-frontend/pages/appointments/book_appointment.php" class="btn btn-outline event-back-link">
                <?php echo sidebarIconSvg("arrow-left"); ?>
                Back to Appointments
            </a>

            <?php if ($error !== ""): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #c33; background: #fee; color: #c33; font-size: 13px;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #080; background: #efe; color: #080; font-size: 13px;">
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
                
                <div style="background: #f9f9f9; padding: 16px; border-radius: 8px; border-left: 4px solid #0066cc;">
                    <div style="margin-bottom: 12px;">
                        <strong style="display: block; color: #333; margin-bottom: 4px;">Student:</strong>
                        <span style="color: #666;"><?php echo htmlspecialchars($appointment["student_name"]); ?></span>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <strong style="display: block; color: #333; margin-bottom: 4px;">Student Email:</strong>
                        <span style="color: #666;"><?php echo htmlspecialchars($appointment["student_email"]); ?></span>
                    </div>
                    <?php if (!empty($notes)): ?>
                    <div style="margin-bottom: 0;">
                        <strong style="display: block; color: #333; margin-bottom: 4px;">Notes:</strong>
                        <span style="color: #666;"><?php echo nl2br($notes); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Counselor Preparation (Student View) -->
            <?php if ($role === "Student" && $userId === $appointment["user_id"]): ?>
            <section style="margin-top: 32px;">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("clipboard"); ?></span>
                    <div>
                        <h2>How to Prepare</h2>
                        <p class="section-heading-subtext">Tips to make the most of your appointment.</p>
                    </div>
                </div>
                <div style="background: #f0f7ff; padding: 16px; border-radius: 8px; border-left: 4px solid #0066cc;">
                    <ul style="margin: 0; padding-left: 20px; color: #333; font-size: 13px; line-height: 1.8;">
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
            <section style="margin-top: 32px;">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("edit"); ?></span>
                    <div>
                        <h2>Session Notes</h2>
                        <p class="section-heading-subtext">Add or update notes about this appointment.</p>
                    </div>
                </div>
                <form method="POST" style="margin-top: 16px;">
                    <input type="hidden" name="action" value="save_notes">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <textarea 
                            name="appointment_notes" 
                            style="width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 13px; resize: vertical;"
                            placeholder="Add pre-appointment notes, observations, or follow-up items..."><?php echo $notes; ?></textarea>
                        <button type="submit" class="btn btn-primary" style="align-self: flex-start;">
                            <?php echo sidebarIconSvg("save"); ?>
                            Save Notes
                        </button>
                    </div>
                </form>
            </section>
            <?php endif; ?>

            <!-- Related Referrals (Counselor/Admin View) -->
            <?php if ((in_array($role, ["Counselor", "Administrator"], true)) && !empty($relatedReferrals)): ?>
            <section style="margin-top: 32px;">
                <div class="section-heading">
                    <span class="section-heading-icon"><?php echo sidebarIconSvg("flag"); ?></span>
                    <div>
                        <h2>Related Referrals</h2>
                        <p class="section-heading-subtext">Referrals submitted for this student.</p>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 16px;">
                    <?php foreach ($relatedReferrals as $ref): ?>
                        <?php
                            $refReasons = json_decode($ref["reasons_json"] ?? "[]", true) ?? [];
                            $reasonList = implode(", ", array_slice($refReasons, 0, 2));
                            if (count($refReasons) > 2) $reasonList .= " +more";
                        ?>
                        <div style="padding: 12px; background: #fff5e6; border-radius: 6px; border-left: 3px solid #d29818;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <strong style="color: #333; display: block; margin-bottom: 4px;">Referral</strong>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars($reasonList); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999; margin-top: 4px;">
                                        <?php echo date("F j, Y", strtotime($ref["referral_datetime"])); ?>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo getStatusBadgeClass($ref["status"]); ?>" style="font-size: 11px;">
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
            <section class="event-detail-actions" style="margin-top: 32px;">
                <?php if ($role === "Student" && $userId === $appointment["user_id"]): ?>
                    <!-- Student Actions -->
                    <?php if (!$hasAppointmentPassed && $status !== "Cancelled"): ?>
                        <form method="POST" style="display: inline;">
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
                        <form method="POST" style="display: inline;">
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
                        <form method="POST" style="display: inline;">
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
            <section style="margin-top: 32px; padding: 16px; background: #f9f9f9; border-radius: 8px;">
                <h3 style="margin: 0 0 12px 0; color: #333; font-size: 14px; font-weight: 600;">📌 Important Information:</h3>
                <ul style="margin: 0; padding-left: 20px; color: #666; font-size: 13px; line-height: 1.6;">
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

<style>
    .event-detail-page {
        padding: 0 80px;
        border-radius: 10px;
        max-width: 1200px;
        margin: 20px auto;
    }

    .event-back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        padding: 8px 12px;
        font-size: 13px;
        text-decoration: none;
    }

    .event-detail-header {
    background: linear-gradient(135deg, var(--primary) 0%, #5f99ca 100%);
    color: white;
    padding: 1.75rem 1.75rem 1.6rem;
    border-radius: 22px;
    margin-bottom: 1rem;
    box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.25rem;
    }

    .event-detail-header-main {
        flex: 1;
    }

    .event-detail-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: #e6f2ff;
        color: #0066cc;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .event-detail-kicker-icon {
        display: flex;
        align-items: center;
    }

    .event-detail-title {
        font-size: 28px;
        font-weight: 700;
        color: #ffffff;
        margin: 0 0 8px 0;
        line-height: 1.2;
    }

    .event-detail-subtitle {
        font-size: 14px;
        color: #ffffff;
        margin: 0;
    }

    .event-detail-status {
        flex-shrink: 0;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.status-approved {
        background: #d1e7dd;
        color: #0f5132;
    }

    .status-badge.status-cancelled {
        background: #f8d7da;
        color: #842029;
    }

    .status-badge.status-completed {
        background: #d1e7dd;
        color: #0f5132;
    }

    .event-detail-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .event-meta-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: #f9f9f9;
        border-radius: 8px;
        border: 1px solid #eee;
    }

    .event-meta-icon {
        display: flex;
        align-items: center;
        color: #0066cc;
        flex-shrink: 0;
    }

    .event-meta-label {
        display: block;
        font-size: 11px;
        color: #999;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .event-meta-value {
        display: block;
        font-size: 14px;
        color: #333;
        font-weight: 500;
    }

    .event-detail-description {
        margin-bottom: 32px;
    }

    .section-heading {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
    }

    .section-heading-icon {
        display: flex;
        align-items: center;
        color: #0066cc;
        flex-shrink: 0;
    }

    .section-heading h2 {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin: 0 0 4px 0;
    }

    .section-heading-subtext {
        font-size: 12px;
        color: #999;
        margin: 0;
    }

    .event-detail-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .stat-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: #f0f7ff;
        border-radius: 8px;
        border-left: 4px solid #0066cc;
    }

    .stat-icon {
        display: flex;
        align-items: center;
        color: #0066cc;
        font-size: 24px;
    }

    .stat-value {
        display: block;
        font-size: 16px;
        font-weight: 700;
        color: #333;
    }

    .stat-label {
        display: block;
        font-size: 11px;
        color: #999;
        margin-top: 2px;
    }

    .event-detail-actions {
        display: flex;
        gap: 12px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .btn-primary {
        background: #0066cc;
        color: white;
    }

    .btn-primary:hover {
        background: #0052a3;
        box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    }

    .btn-outline {
        background: white;
        color: #0066cc;
        border: 1px solid #0066cc;
    }

    .btn-outline:hover {
        background: #f0f7ff;
    }

    .btn-large {
        padding: 12px 20px;
        font-size: 14px;
    }

    .btn-disabled {
        background: #ddd;
        color: #999;
        cursor: not-allowed;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    @media (max-width: 900px) {
        .event-detail-header {
            flex-direction: column;
        }

        .event-detail-title {
            font-size: 22px;
        }

        .event-detail-meta-grid {
            grid-template-columns: 1fr 1fr;
        }

        .event-detail-actions {
            flex-direction: column;
        }

        .btn-large {
            width: 100%;
            justify-content: center;
        }
    }
</style>

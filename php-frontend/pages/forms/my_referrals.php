<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "My Referrals";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

// Allow students and staff to view their referrals
$allowedRoles = ["Student", "Instructor", "Facilitator", "Administrator", "Counselor"];
if (!in_array($role, $allowedRoles, true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

campuscare_ensure_referral_forms_table($conn);
campuscare_ensure_referral_intake_forms_table($conn);

// Get referrals submitted by this user
$whereClause = "submitted_by_user_id = ?";
$params = [$userId];

$query = "SELECT id, student_name, student_email, is_external_student, reasons_json, status, referral_datetime, email_notification_sent FROM referral_forms WHERE {$whereClause} ORDER BY referral_datetime DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $error = "Database error: " . $conn->error;
    $referrals = [];
} else {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $referrals = [];
    while ($row = $result->fetch_assoc()) {
        $reasons = json_decode($row["reasons_json"], true);
        $row["reasons"] = is_array($reasons) ? $reasons : [];
        $referrals[] = $row;
    }
    $stmt->close();
}

function getStatusBadgeClass($status) {
    $classes = [
        "Pending" => "status-pending",
        "In Review" => "status-in-review",
        "For Scheduling" => "status-scheduling",
        "Completed" => "status-completed",
        "Closed" => "status-closed",
    ];
    return $classes[$status] ?? "status-pending";
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
        <div class="page-shell">
            <div class="page-header">
                <div>
                    <h1 class="page-title">My Referrals</h1>
                    <p class="page-subtitle">Track referrals you have submitted</p>
                </div>
                <a href="/campuscare-api/php-frontend/pages/forms/student_referral_form.php" class="btn event-join-btn btn-sm new-referral-btn">+ New Referral</a>
            </div>

            <?php if (empty($referrals)): ?>
                <div class="empty-card">
                    <div class="empty-icon">
                        <img src="/campuscare-api/php-frontend/assets/images/icons/refer%20icon.png" alt="Referral icon">
                    </div>
                    <h2 class="empty-title">No Referrals Yet</h2>
                    <p class="empty-text">You haven't submitted any referrals yet. Help support a student by submitting a referral.</p>
                    <a href="/campuscare-api/php-frontend/pages/forms/student_referral_form.php" class="btn event-join-btn primary-cta">Submit Your First Referral</a>
                </div>
            <?php else: ?>
                <?php foreach ($referrals as $referral): ?>
                    <div class="referral-card">
                        <div class="card-header">
                            <div>
                                <p class="card-title">
                                    <?php echo htmlspecialchars($referral["student_name"]); ?>
                                    <?php if ($referral["is_external_student"]): ?>
                                        <span class="external-tag">(External)</span>
                                    <?php endif; ?>
                                </p>
                                <p class="card-meta">
                                    Submitted on <?php echo date("M d, Y \a\t g:i A", strtotime($referral["referral_datetime"])); ?>
                                    <?php if ($referral["is_external_student"] && $referral["email_notification_sent"]): ?>
                                        <span class="email-sent">✓ Email Sent</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status-badge <?php echo getStatusBadgeClass($referral["status"]); ?>">
                                <?php echo htmlspecialchars($referral["status"]); ?>
                            </span>
                        </div>

                        <div class="card-info">
                            <div class="info-item">
                                <div class="info-label">Student</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($referral["student_name"]); ?>
                                    <?php if ($referral["student_email"]): ?>
                                        <br><span class="muted small"><?php echo htmlspecialchars($referral["student_email"]); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Type</div>
                                <div class="info-value"><?php echo $referral["is_external_student"] ? "External Student" : "System Student"; ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Current Status</div>
                                <div class="info-value"><?php echo htmlspecialchars($referral["status"]); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($referral["reasons"])): ?>
                            <div class="reasons-block">
                                <div class="info-label">Reasons</div>
                                <div class="reason-tags">
                                    <?php foreach ($referral["reasons"] as $reason): ?>
                                        <span class="reason-tag"><?php echo htmlspecialchars($reason); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a href="/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=<?php echo $referral["id"]; ?>" class="action-btn btn-secondary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
    /* Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
    }

    .page-header .page-title { margin: 0; }
    .new-referral-btn { box-shadow: 0 6px 18px rgba(0,120,60,0.06); }

    /* Empty state */
    .empty-card {
        text-align: center;
        padding: 48px 24px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #f0f2f4;
    }

    .empty-icon { font-size: 48px; margin-bottom: 12px; display: inline-block; }
    .empty-icon img,
    .empty-icon svg {
        width: 56px;
        height: 56px;
        display: inline-block;
        object-fit: contain;
    }
    .empty-title { color: #222; margin-bottom: 8px; font-size: 20px; }
    .empty-text { color: #666; margin-bottom: 18px; }
    .primary-cta { box-shadow: 0 8px 24px rgba(34,139,34,0.06); }

    /* Referral card */
    .referral-card {
        background: #fff;
        border: 1px solid #e6eaec;
        border-radius: 12px;
        padding: 18px;
        margin-bottom: 16px;
        transition: transform 0.12s ease, box-shadow 0.18s ease;
    }

    .referral-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(13,38,59,0.06);
    }

    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .card-title { font-size: 16px; font-weight: 700; color: #222; margin: 0 0 6px 0; }
    .external-tag { font-size: 12px; color: #888; margin-left: 8px; font-weight: 600; }
    .card-meta { font-size: 13px; color: #777; margin: 0; }
    .email-sent { display: inline-block; padding: 4px 8px; background: #d1e7dd; color: #0f5132; border-radius: 4px; font-size: 11px; margin-left: 10px; }

    .card-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 12px 0; padding: 12px 0; border-top: 1px solid #f1f3f5; border-bottom: 1px solid #f1f3f5; }
    .info-item { font-size: 13px; }
    .info-label { color: #999; text-transform: uppercase; font-weight: 700; font-size: 11px; margin-bottom: 6px; }
    .info-value { color: #333; font-weight: 500; }

    .reason-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .reason-tag { display: inline-block; padding: 6px 10px; background: #eef6ff; color: #0066cc; border-radius: 999px; font-size: 12px; border: 1px solid #cfe3ff; }

    .card-actions { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f3f5; display: flex; gap: 10px; }
    .action-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block; border: 1px solid #ddd; background: #fff; color: #333; transition: all .15s; }
    .action-btn:hover { border-color: #0066cc; color: #0066cc; transform: translateY(-1px); }

    .muted.small { color: #777; font-size: 12px; }

    /* Status badges (kept for accessibility) */
    .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    .status-in-review { background: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; }
    .status-scheduling { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
    .status-completed { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .status-closed { background: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8; }
</style>


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
            <div class="page-header-wrapper">
                <div class="page-header dashboard-head">
                    <div>
                        <h1 class="page-title">My Referrals</h1>
                        <p class="page-subtitle">Track referrals you have submitted</p>
                    </div>
                    <a href="/campuscare-api/php-frontend/pages/forms/student_referral_form.php" class="btn event-join-btn btn-sm new-referral-btn">+ New Referral</a>
                </div>
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
    .page-header-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 16px;
        margin-bottom: 1rem;
    }

    .page-header {
        width: 100%;
        padding: 30px 34px 20px;
        background: var(--primary);
        color: #fff;
        border-radius: 22px;
        box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    }

    .page-header .page-title {
        margin: 0 0 8px;
        font-size: 34px;
        color: #fff;
    }

    .page-header .page-subtitle {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, 0.9);
    }

    .new-referral-btn { box-shadow: 0 6px 18px rgba(0,120,60,0.06); }

    /* Empty state */
    .empty-card {
        margin-top: 16px;
        text-align: center;
        padding: 48px 24px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #f0f2f4;
    }

    body.theme-dark .empty-card {
        background: #1a2637;
        border: 1px solid #2b3b4f;
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
    body.theme-dark .empty-title { color: #e6edf5; }

    .empty-text { color: #666; margin-bottom: 18px; }
    body.theme-dark .empty-text { color: #9fb0c3; }

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

    body.theme-dark .referral-card {
        background: #121d2b;
        border-color: #2b3b4f;
    }

    .referral-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(13,38,59,0.06);
    }

    body.theme-dark .referral-card:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    }

    .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .card-title { font-size: 16px; font-weight: 700; color: #222; margin: 0 0 6px 0; }
    body.theme-dark .card-title { color: #e6edf5; }

    .external-tag { font-size: 12px; color: #888; margin-left: 8px; font-weight: 600; }
    body.theme-dark .external-tag { color: #9fb0c3; }

    .card-meta { font-size: 13px; color: #777; margin: 0; }
    body.theme-dark .card-meta { color: #9fb0c3; }

    .email-sent { display: inline-block; padding: 4px 8px; background: #d1e7dd; color: #0f5132; border-radius: 4px; font-size: 11px; margin-left: 10px; }
    body.theme-dark .email-sent { background: #143123; color: #76d39a; }

    .card-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 12px 0; padding: 12px 0; border-top: 1px solid #f1f3f5; border-bottom: 1px solid #f1f3f5; }
    body.theme-dark .card-info { border-top-color: #2b3b4f; border-bottom-color: #2b3b4f; }

    .info-item { font-size: 13px; }
    .info-label { color: #999; text-transform: uppercase; font-weight: 700; font-size: 11px; margin-bottom: 6px; }
    body.theme-dark .info-label { color: #8fa4ba; }

    .info-value { color: #333; font-weight: 500; }
    body.theme-dark .info-value { color: #e6edf5; }

    .reason-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
    .reason-tag { display: inline-block; padding: 6px 10px; background: #eef6ff; color: #0066cc; border-radius: 999px; font-size: 12px; border: 1px solid #cfe3ff; }
    body.theme-dark .reason-tag { background: #1a3248; color: #9dc6ee; border-color: #2f4458; }

    .card-actions { margin-top: 12px; padding-top: 12px; border-top: 1px solid #f1f3f5; display: flex; gap: 10px; }
    body.theme-dark .card-actions { border-top-color: #2b3b4f; }

    .action-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block; border: 1px solid #ddd; background: #fff; color: #333; transition: all .15s; }
    body.theme-dark .action-btn { background: #162534; border-color: #2f4458; color: #9dc6ee; }

    .action-btn:hover { border-color: #0066cc; color: #0066cc; transform: translateY(-1px); }
    body.theme-dark .action-btn:hover { border-color: #68a8dc; color: #68a8dc; background: #1a2f42; }

    .muted.small { color: #777; font-size: 12px; }
    body.theme-dark .muted.small { color: #8fa4ba; }

    /* Status badges (kept for accessibility) */
    .status-pending { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    body.theme-dark .status-pending { background: #3a3118; color: #f0cb74; border: 1px solid #5a4a1f; }

    .status-in-review { background: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; }
    body.theme-dark .status-in-review { background: #1a3248; color: #9dc6ee; border: 1px solid #2f4458; }

    .status-scheduling { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
    body.theme-dark .status-scheduling { background: #0f3840; color: #88d7da; border: 1px solid #1f4a5f; }

    .status-completed { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    body.theme-dark .status-completed { background: #143123; color: #76d39a; border: 1px solid #1f4a2f; }

    .status-closed { background: #e2e3e5; color: #41464b; border: 1px solid #d3d6d8; }
    body.theme-dark .status-closed { background: #233445; color: #a0b2c4; border: 1px solid #3a4d5f; }
</style>


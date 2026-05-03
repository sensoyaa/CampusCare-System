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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 class="page-title">My Referrals</h1>
                    <p class="page-subtitle">Track referrals you have submitted</p>
                </div>
                <a href="/campuscare-api/php-frontend/pages/forms/student_referral_form.php" class="btn event-join-btn btn-sm">+ New Referral</a>
            </div>

            <?php if (empty($referrals)): ?>
                <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 12px;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                    <h2 style="color: #333; margin-bottom: 10px;">No Referrals Yet</h2>
                    <p style="color: #666; margin-bottom: 20px;">You haven't submitted any referrals yet. Help support a student by submitting a referral.</p>
                    <a href="/campuscare-api/php-frontend/pages/forms/student_referral_form.php" class="btn event-join-btn">Submit Your First Referral</a>
                </div>
            <?php else: ?>
                <?php foreach ($referrals as $referral): ?>
                    <div style="background: white; border: 1px solid #ddd; border-radius: 12px; padding: 20px; margin-bottom: 20px; transition: all 0.3s;" class="referral-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div>
                                <p style="font-size: 16px; font-weight: 600; color: #333; margin: 0 0 5px 0;">
                                    <?php echo htmlspecialchars($referral["student_name"]); ?>
                                    <?php if ($referral["is_external_student"]): ?>
                                        <span style="font-size: 12px; color: #999;">(External)</span>
                                    <?php endif; ?>
                                </p>
                                <p style="font-size: 13px; color: #999; margin: 0;">
                                    Submitted on <?php echo date("M d, Y \a\t g:i A", strtotime($referral["referral_datetime"])); ?>
                                    <?php if ($referral["is_external_student"] && $referral["email_notification_sent"]): ?>
                                        <span style="display: inline-block; padding: 4px 8px; background: #d1e7dd; color: #0f5132; border-radius: 4px; font-size: 11px; margin-left: 10px;">✓ Email Sent</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="status-badge <?php echo getStatusBadgeClass($referral["status"]); ?>" style="display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?php echo htmlspecialchars($referral["status"]); ?>
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 15px 0; padding: 15px 0; border-top: 1px solid #eee; border-bottom: 1px solid #eee;">
                            <div style="font-size: 13px;">
                                <div style="color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Student</div>
                                <div style="color: #333; font-weight: 500;">
                                    <?php echo htmlspecialchars($referral["student_name"]); ?>
                                    <?php if ($referral["student_email"]): ?>
                                        <br><span style="font-size: 12px; color: #999;"><?php echo htmlspecialchars($referral["student_email"]); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="font-size: 13px;">
                                <div style="color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Type</div>
                                <div style="color: #333; font-weight: 500;">
                                    <?php echo $referral["is_external_student"] ? "External Student" : "System Student"; ?>
                                </div>
                            </div>

                            <div style="font-size: 13px;">
                                <div style="color: #999; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Current Status</div>
                                <div style="color: #333; font-weight: 500;"><?php echo htmlspecialchars($referral["status"]); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($referral["reasons"])): ?>
                            <div>
                                <div style="font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Reasons</div>
                                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <?php foreach ($referral["reasons"] as $reason): ?>
                                        <span style="display: inline-block; padding: 4px 10px; background: #e6f2ff; color: #0066cc; border-radius: 12px; font-size: 12px; border: 1px solid #b6d4fe;">
                                            <?php echo htmlspecialchars($reason); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee; display: flex; gap: 10px;">
                            <a href="/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=<?php echo $referral["id"]; ?>" style="padding: 8px 16px; border: 1px solid #ddd; background: white; color: #333; border-radius: 6px; font-size: 13px; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block;" class="action-btn">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
    .referral-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .action-btn:hover {
        border-color: #0066cc;
        color: #0066cc;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .status-in-review {
        background: #cfe2ff;
        color: #084298;
        border: 1px solid #b6d4fe;
    }

    .status-scheduling {
        background: #cff4fc;
        color: #055160;
        border: 1px solid #b6effb;
    }

    .status-completed {
        background: #d1e7dd;
        color: #0f5132;
        border: 1px solid #badbcc;
    }

    .status-closed {
        background: #e2e3e5;
        color: #41464b;
        border: 1px solid #d3d6d8;
    }
</style>


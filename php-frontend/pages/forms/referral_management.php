<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Referral Management";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

// Only counselors and administrators can access this
if (!in_array($role, ["Counselor", "Administrator"], true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

campuscare_ensure_referral_forms_table($conn);

$error = "";
$success = "";
$filter = trim((string) ($_GET["status"] ?? ""));

// Handle referral status update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    $action = trim((string) $_POST["action"]);
    $referralId = intval($_POST["referral_id"] ?? 0);

    if ($action === "update_status") {
        $status = trim((string) ($_POST["status"] ?? ""));
        $counselorNotes = trim((string) ($_POST["counselor_notes"] ?? ""));

        $validStatuses = campuscare_status_choices();
        if (!in_array($status, $validStatuses, true)) {
            $error = "Invalid status selected.";
        } else {
            $update = $conn->prepare("UPDATE referral_forms SET status = ?, counselor_signature_typed = ? WHERE id = ? LIMIT 1");
            if (!$update) {
                $error = "Unable to update referral: " . $conn->error;
            } else {
                $update->bind_param("ssi", $status, $counselorNotes, $referralId);
                if ($update->execute()) {
                    $success = "✓ Referral status updated successfully!";
                } else {
                    $error = "Unable to update referral: " . $conn->error;
                }
                $update->close();
            }
        }
    }
}

// Get referrals
$where = "1=1";
$params = [];

if ($role === "Counselor") {
    $where = "(referred_to_counselor_id = ? OR referred_to_counselor_id IS NULL)";
    $params[] = $userId;
}

if ($filter !== "" && in_array($filter, campuscare_status_choices(), true)) {
    $where .= " AND status = ?";
    $params[] = $filter;
}

$query = "SELECT id, student_name, student_email, is_external_student, reasons_json, status, referral_datetime, intake_form_id FROM referral_forms WHERE {$where} ORDER BY referral_datetime DESC";

$stmt = $conn->prepare($query);
$referrals = [];

if ($stmt) {
    if (!empty($params)) {
        $types = str_repeat("i", count($params) - 1) . "s";
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

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
            <div class="booking-head">
                <div>
                    <h1 class="page-title">Referral Management</h1>
                    <p class="page-subtitle">Review and manage student referrals</p>
                </div>
            </div>
            <?php if ($error): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #c33; background: #fee; color: #c33; font-size: 13px;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #3c3; background: #efe; color: #3c3; font-size: 13px;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <style>
                /* Booking head (copied from book_appointment) with no top margin */
                .booking-head {
                    padding: 1.75rem 1.75rem 1.6rem;
                    background: var(--primary);
                    color: #fff;
                    border-radius: 22px;
                    margin-top: 0;
                    margin-bottom: 1rem;
                    box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
                    padding: 30px 34px 20px;
                }

                .booking-head h1 {
                    margin: 0 0 8px;
                    font-size: 34px;
                    color: #fff;
                }

                .booking-head p {
                    margin: 0;
                    max-width: 680px;
                    color: rgba(255,255,255,0.9);
                }

                .filter-section {
                    margin-bottom: 20px;
                }

                .filter-section select {
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    font-size: 13px;
                    max-width: 200px;
                }

                .referral-card {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 10px;
                    padding: 16px;
                    margin-bottom: 16px;
                    transition: all 0.3s;
                }

                .referral-card:hover {
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }

                .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                }

                .card-title {
                    font-size: 14px;
                    font-weight: 600;
                    color: #333;
                    margin: 0;
                }

                body.theme-dark .referral-item h3 {
                    color: #e6edf5;
                }

                .status-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 16px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }

                .status-pending {
                    background: #fff3cd;
                    color: #856404;
                }

                body.theme-dark .status-pending {
                    background: #3a3118;
                    color: #f0cb74;
                }

                .status-in-review {
                    background: #cfe2ff;
                    color: #084298;
                }

                body.theme-dark .status-in-review {
                    background: #1a3248;
                    color: #9dc6ee;
                }

                .status-scheduling {
                    background: #cff4fc;
                    color: #055160;
                }

                body.theme-dark .status-scheduling {
                    background: #0f3840;
                    color: #88d7da;
                }

                .status-completed {
                    background: #d1e7dd;
                    color: #0f5132;
                }

                body.theme-dark .status-completed {
                    background: #143123;
                    color: #76d39a;
                }

                .status-closed {
                    background: #e2e3e5;
                    color: #41464b;
                }

                body.theme-dark .status-closed {
                    background: #233445;
                    color: #a0b2c4;
                }

                .card-info {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 12px;
                    padding: 12px 0;
                    border-top: 1px solid #eee;
                    border-bottom: 1px solid #eee;
                    margin: 12px 0;
                    font-size: 13px;
                }

                body.theme-dark .card-info {
                    border-top-color: #2b3b4f;
                    border-bottom-color: #2b3b4f;
                }

                .info-item {
                    display: flex;
                    flex-direction: column;
                }

                .info-label {
                    color: #999;
                    font-weight: 600;
                    font-size: 11px;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                    letter-spacing: 0.5px;
                }

                body.theme-dark .info-label {
                    color: #8fa4ba;
                }

                .info-value {
                    color: #333;
                    font-weight: 500;
                }

                body.theme-dark .info-value {
                    color: #e6edf5;
                }

                .reason-tags {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin: 10px 0;
                }

                .reason-tag {
                    display: inline-block;
                    padding: 3px 8px;
                    background: #e6f2ff;
                    color: #0066cc;
                    border-radius: 8px;
                    font-size: 11px;
                }

                body.theme-dark .reason-tag {
                    background: #1a3248;
                    color: #9dc6ee;
                }

                .action-form {
                    background: #f8f9fa;
                    padding: 12px;
                    border-radius: 6px;
                    margin-top: 12px;
                }

                body.theme-dark .action-form {
                    background: #1a2637;
                    border: 1px solid #2b3b4f;
                }

                .form-group {
                    margin-bottom: 10px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 4px;
                    font-weight: 600;
                    font-size: 13px;
                    color: #333;
                }

                .form-group select,
                .form-group textarea {
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 13px;
                    box-sizing: border-box;
                    font-family: inherit;
                }

                .form-group textarea {
                    resize: vertical;
                    min-height: 70px;
                }

                .button-group {
                    display: flex;
                    gap: 8px;
                    margin-top: 10px;
                }

                .btn-primary {
                    padding: 8px 16px;
                    background: #0066cc;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }

                .btn-primary:hover {
                    background: #0052a3;
                }

                .btn-secondary {
                    padding: 8px 16px;
                    background: #f0f0f0;
                    color: #333;
                    border: none;
                    border-radius: 6px;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    text-decoration: none;
                    display: inline-block;
                    transition: all 0.2s;
                }

                .btn-secondary:hover {
                    background: #e0e0e0;
                }

                .empty-state {
                    text-align: center;
                    padding: 40px 20px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    color: #666;
                    font-size: 14px;
                }

                @media (max-width: 768px) {
                    .card-info {
                        grid-template-columns: 1fr;
                    }

                    .button-group {
                        flex-direction: column;
                    }

                    .btn-primary,
                    .btn-secondary {
                        width: 100%;
                    }
                }
            </style>

            <div class="filter-section">
                    <select onchange="location.href='?view=referrals&status=' + this.value;">
                            <option value="">All Statuses</option>
                            <?php foreach (campuscare_status_choices() as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filter === $status ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                </div>

                <?php if (empty($referrals)): ?>
                    <div class="empty-state">
                        📭 No referrals found.
                    </div>
                <?php else: ?>
                    <?php foreach ($referrals as $ref): ?>
                            <div class="referral-card">
                                <div class="card-header">
                                    <p class="card-title"><?php echo htmlspecialchars($ref["student_name"]); ?></p>
                                    <span class="status-badge <?php echo getStatusBadgeClass($ref["status"]); ?>">
                                        <?php echo htmlspecialchars($ref["status"]); ?>
                                    </span>
                                </div>

                                <div class="card-info">
                                    <div class="info-item">
                                        <div class="info-label">Student</div>
                                        <div class="info-value">
                                            <?php echo htmlspecialchars($ref["student_name"]); ?>
                                            <?php if ($ref["student_email"]): ?>
                                                <br><span style="font-size: 11px; color: #999;"><?php echo htmlspecialchars($ref["student_email"]); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Type</div>
                                        <div class="info-value"><?php echo $ref["is_external_student"] ? "External" : "System"; ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Date</div>
                                        <div class="info-value"><?php echo date("M d, Y", strtotime($ref["referral_datetime"])); ?></div>
                                    </div>
                                </div>

                                <?php if (!empty($ref["reasons"])): ?>
                                    <div class="reason-tags">
                                        <?php foreach ($ref["reasons"] as $reason): ?>
                                            <span class="reason-tag"><?php echo htmlspecialchars($reason); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="action-form">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="referral_id" value="<?php echo htmlspecialchars($ref["id"]); ?>">

                                        <div class="form-group">
                                            <label for="status_<?php echo $ref["id"]; ?>">Update Status</label>
                                            <select id="status_<?php echo $ref["id"]; ?>" name="status">
                                                <?php foreach (campuscare_status_choices() as $status): ?>
                                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $ref["status"] === $status ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="button-group">
                                            <button type="submit" class="btn-primary">Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

        </div>
    </div>
</main>

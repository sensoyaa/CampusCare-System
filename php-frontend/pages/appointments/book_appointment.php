<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Book Appointment";
$userId = intval($_SESSION["user_id"] ?? 0);
$role = normalizeRole($_SESSION["role"] ?? "Student");
$preselectedService = strtolower(trim((string) ($_GET["service"] ?? "")));
$allowedServices = ["counseling", "testing", "referral"];
if (!in_array($preselectedService, $allowedServices, true)) {
    $preselectedService = "";
}

$canUseReferralForm = true;
$counselors = [];
$counselorResult = $conn->query("
    SELECT id, full_name
    FROM users
    WHERE role IN ('Counselor', 'Counsellor', 'Counselors')
      AND status = 'Active'
    ORDER BY full_name ASC
");

while ($counselorResult && ($row = $counselorResult->fetch_assoc())) {
    $counselors[] = $row;
}

$recentForms = [];

$intakeTableCheck = $conn->query("SHOW TABLES LIKE 'counseling_intake_forms'");
if ($intakeTableCheck !== false && $intakeTableCheck->num_rows > 0) {
    $recentIntakeStmt = $conn->prepare("SELECT payload_json, created_at FROM counseling_intake_forms WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    if ($recentIntakeStmt) {
        $recentIntakeStmt->bind_param("i", $userId);
        $recentIntakeStmt->execute();
        $recentIntakeResult = $recentIntakeStmt->get_result();
        $recentIntakeStmt->close();

        while ($recentIntakeResult && ($recentIntakeRow = $recentIntakeResult->fetch_assoc())) {
            $payload = json_decode((string) ($recentIntakeRow["payload_json"] ?? ""), true);
            $clientName = "";
            if (is_array($payload)) {
                $clientName = trim(((string) ($payload["client_first_name"] ?? "")) . " " . ((string) ($payload["client_last_name"] ?? "")));
            }

            $recentForms[] = [
                "service" => "Counseling",
                "title" => "Counseling Intake Form",
                "subtitle" => $clientName !== "" ? $clientName : "Latest saved intake form",
                "date" => trim((string) ($recentIntakeRow["created_at"] ?? "")),
                "link" => "/campuscare-api/php-frontend/pages/appointments/counseling_intake_preview.php",
            ];
        }
    }
}

$referralTableCheck = $conn->query("SHOW TABLES LIKE 'referral_forms'");
if ($referralTableCheck !== false && $referralTableCheck->num_rows > 0) {
    $recentReferralStmt = $conn->prepare("
        SELECT id, student_name, course_year_section, created_at
        FROM referral_forms
        WHERE submitted_by_user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");

    if ($recentReferralStmt) {
        $recentReferralStmt->bind_param("i", $userId);
        $recentReferralStmt->execute();
        $recentReferralResult = $recentReferralStmt->get_result();
        $recentReferralStmt->close();

        while ($recentReferralResult && ($recentReferralRow = $recentReferralResult->fetch_assoc())) {
            $recentForms[] = [
                "service" => "Referral",
                "title" => "Referral Slip",
                "subtitle" => trim((string) ($recentReferralRow["student_name"] ?? "")) !== ""
                    ? trim((string) ($recentReferralRow["student_name"] ?? ""))
                    : trim((string) ($recentReferralRow["course_year_section"] ?? "Recent referral form")),
                "date" => trim((string) ($recentReferralRow["created_at"] ?? "")),
                "link" => "/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=" . intval($recentReferralRow["id"] ?? 0),
            ];
        }
    }
}

$testingTableCheck = $conn->query("SHOW TABLES LIKE 'testing_requests'");
if ($testingTableCheck !== false && $testingTableCheck->num_rows > 0) {
    $recentTestingStmt = $conn->prepare("
        SELECT id, target_student_name, created_at
        FROM testing_requests
        WHERE requester_user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");

    if ($recentTestingStmt) {
        $recentTestingStmt->bind_param("i", $userId);
        $recentTestingStmt->execute();
        $recentTestingResult = $recentTestingStmt->get_result();
        $recentTestingStmt->close();

        while ($recentTestingResult && ($recentTestingRow = $recentTestingResult->fetch_assoc())) {
            $recentForms[] = [
                "service" => "Testing",
                "title" => "Testing Request",
                "subtitle" => trim((string) ($recentTestingRow["target_student_name"] ?? "")) !== ""
                    ? trim((string) ($recentTestingRow["target_student_name"] ?? ""))
                    : "Recent testing request",
                "date" => trim((string) ($recentTestingRow["created_at"] ?? "")),
                "link" => "/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=" . intval($recentTestingRow["id"] ?? 0),
            ];
        }
    }
}

usort($recentForms, function (array $a, array $b): int {
    return strcmp((string) ($b["date"] ?? ""), (string) ($a["date"] ?? ""));
});
require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>
    <style>
        :root {
            --brand-1: #1c4f7b;
            --brand-2: #2f85a0;
            --brand-3: #dff1f4;
            --ink: #183247;
            --muted: #61778c;
            --line: #d7e2ea;
            --paper: #ffffff;
            --bg: linear-gradient(145deg, #e9f3fb 0%, #f3f9f7 48%, #eef6ff 100%);
            --success: #1f8b5f;
            --danger: #c14949;
        }

        * { box-sizing: border-box; }

        .booking-shell {
            max-width: 1120px;
            margin: 28px auto 40px;
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(23, 59, 94, 0.16);
            overflow: hidden;
        }

        .booking-head {
            padding: 30px 34px 20px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.34), transparent 36%),
                linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
        }

        .booking-head h1 {
            margin: 0 0 8px;
            font-size: 34px;
        }

        .booking-head p {
            margin: 0;
            max-width: 680px;
            color: rgba(255,255,255,0.9);
        }

        .head-actions {
            margin-bottom: 18px;
        }

        .head-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.28);
            background: rgba(255,255,255,0.12);
            font-size: 13px;
            font-weight: 700;
        }

        .step-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 24px;
        }

        .step-pill {
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.18);
            min-height: 68px;
        }

        .step-pill strong,
        .step-pill span {
            display: block;
        }

        .step-pill strong {
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .step-pill span {
            font-size: 15px;
            line-height: 1.35;
            opacity: 0.82;
        }

        .step-pill.active,
        .step-pill.done {
            background: rgba(255,255,255,0.24);
        }

        .step-pill.active span,
        .step-pill.done span {
            opacity: 1;
        }

        .booking-body {
            padding: 34px;
        }

        .step-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .step-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .panel-title {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .panel-subtitle {
            margin: 0 0 24px;
            color: var(--muted);
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .recent-forms {
            margin-top: 28px;
            border-top: 1px solid #e4edf4;
            padding-top: 24px;
        }

        .recent-forms h3 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        .recent-forms p {
            margin: 0 0 16px;
            color: var(--muted);
        }

        .recent-forms-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .recent-form-card {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            padding: 18px;
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .recent-form-card strong,
        .recent-form-card span,
        .recent-form-card small {
            display: block;
        }

        .recent-form-card strong {
            font-size: 18px;
            margin-bottom: 4px;
        }

        .recent-form-card span {
            color: var(--muted);
            margin-bottom: 10px;
        }

        .recent-form-card small {
            color: var(--brand-1);
            font-weight: 700;
            margin-bottom: 12px;
        }

        .recent-form-card a {
            color: var(--brand-2);
            font-weight: 700;
            text-decoration: none;
        }

        .availability-card {
            margin-top: 16px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fbfdff;
            padding: 16px;
        }

        .availability-card h4 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        .availability-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .availability-item {
            border: 1px solid #e7eef5;
            border-radius: 12px;
            padding: 10px 12px;
            background: #fff;
        }

        .availability-item strong,
        .availability-item span {
            display: block;
        }

        .availability-item strong {
            font-size: 13px;
            margin-bottom: 4px;
            color: var(--brand-1);
        }

        .service-card {
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .service-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e8f3fb;
            color: var(--brand-1);
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .service-card:hover {
            transform: translateY(-4px);
            border-color: #a6c4d7;
            box-shadow: 0 18px 34px rgba(33, 77, 118, 0.09);
        }

        .service-card.selected {
            border-color: var(--brand-2);
            box-shadow: 0 20px 40px rgba(47, 133, 160, 0.18);
            background: linear-gradient(160deg, #edf8fb 0%, #ffffff 82%);
        }

        .service-card.disabled {
            opacity: 0.58;
            cursor: not-allowed;
            filter: grayscale(0.12);
        }

        .service-card h3 {
            margin: 0 0 6px;
            font-size: 18px;
        }

        .service-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.45;
            font-size: 13px;
        }

        .service-card small {
            display: inline-block;
            margin-top: 10px;
            color: var(--brand-1);
            font-weight: 700;
            font-size: 12px;
        }

        .embed-shell {
            border: 1px solid var(--line);
            border-radius: 24px;
            background: #f7fbff;
            overflow: hidden;
        }

        .embed-note {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #f2f8fc 100%);
        }

        .embed-note p {
            margin: 0;
            color: var(--muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            background: #eef3f7;
            color: var(--muted);
            white-space: nowrap;
        }

        .status-badge.saved {
            background: #e6f6ee;
            color: var(--success);
        }

        .status-badge.error {
            background: #fdeeee;
            color: var(--danger);
        }

        .embedded-form-frame {
            width: 100%;
            height: 980px;
            border: 0;
            display: block;
            background: #fff;
        }

        .empty-state {
            border: 1px dashed var(--line);
            border-radius: 18px;
            padding: 28px;
            background: #fcfdff;
            color: var(--muted);
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            align-items: end;
        }

        .field-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--brand-1);
        }

        .field-group input,
        .field-group select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 13px 14px;
            font-size: 15px;
            background: #fff;
            color: var(--ink);
        }

        .field-group input:focus,
        .field-group select:focus {
            outline: none;
            border-color: var(--brand-2);
            box-shadow: 0 0 0 4px rgba(47, 133, 160, 0.12);
        }

        .schedule-help,
        .message-line {
            margin-top: 14px;
            color: var(--muted);
        }

        .message-line.error {
            color: var(--danger);
        }

        .review-layout {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 20px;
        }

        .review-card,
        .summary-card {
            border: 1px solid var(--line);
            border-radius: 20px;
            background: #fff;
            padding: 20px;
        }

        .review-card h3,
        .summary-card h3 {
            margin: 0 0 14px;
            font-size: 20px;
        }

        .review-list {
            display: grid;
            gap: 12px;
        }

        .review-item {
            padding-bottom: 12px;
            border-bottom: 1px solid #edf2f7;
        }

        .review-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .review-item strong,
        .summary-entry strong {
            display: block;
            font-size: 12px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .summary-card {
            display: grid;
            gap: 16px;
        }

        .summary-section {
            border: 1px solid #edf2f7;
            border-radius: 16px;
            padding: 16px;
            background: #fbfdff;
        }

        .summary-section h4 {
            margin: 0 0 12px;
            font-size: 16px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .summary-entry {
            min-height: 42px;
        }

        .summary-entry.full {
            grid-column: 1 / -1;
        }

        .footer-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 22px 34px 30px;
            border-top: 1px solid var(--line);
            background: rgba(245, 249, 252, 0.8);
        }

        .footer-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 14px;
            padding: 13px 22px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            box-shadow: 0 14px 30px rgba(28, 79, 123, 0.16);
        }

        .btn-secondary {
            background: #fff;
            color: var(--brand-1);
            border: 1px solid var(--line);
        }

        .btn-ghost {
            background: transparent;
            color: var(--brand-1);
            border: 1px dashed #9ab6c8;
        }

        .footer-hint {
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 920px) {
            .service-grid,
            .schedule-grid,
            .review-layout,
            .summary-grid,
            .step-strip {
                grid-template-columns: 1fr;
            }

            .embedded-form-frame {
                height: 1180px;
            }
        }

        @media (max-width: 720px) {
            body {
                padding: 14px 10px 22px;
            }

            .booking-head,
            .booking-body,
            .footer-bar {
                padding-left: 18px;
                padding-right: 18px;
            }

            .embed-note,
            .footer-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .booking-head,
            .footer-bar,
            #step1,
            #step2,
            #step3 {
                display: none !important;
            }

            .booking-shell {
                box-shadow: none;
                border: 0;
                background: #fff;
            }

            .booking-body {
                padding: 0;
            }

            #step4 {
                display: block !important;
            }

            .review-layout {
                display: block;
            }

            .review-card,
            .summary-card {
                border: 0;
                padding: 0;
            }

            .summary-section {
                break-inside: avoid;
                border-color: #d7e2ea;
                background: #fff;
            }
        }
    </style>
<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>
        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content" style="background: var(--bg); padding-top: 1px; padding-bottom: 28px;">
    <div class="booking-shell">
        <header class="booking-head">
            <h1>Book Your Appointment</h1>
            <p>Choose your service, fill up the form, set your schedule, then review before you submit.</p>

            <div class="step-strip">
                <div class="step-pill active" data-step-pill="1">
                    <strong>Step 1</strong>
                    <span>Select service</span>
                </div>
                <div class="step-pill" data-step-pill="2">
                    <strong>Step 2</strong>
                    <span>Fill up form</span>
                </div>
                <div class="step-pill" data-step-pill="3">
                    <strong>Step 3</strong>
                    <span>Set schedule</span>
                </div>
                <div class="step-pill" data-step-pill="4">
                    <strong>Step 4</strong>
                    <span>Check info</span>
                </div>
            </div>
        </header>

        <div class="booking-body">
            <section id="step1" class="step-panel active">
                <h2 class="panel-title">Select a service</h2>
                <p class="panel-subtitle">Choose the service you need.</p>

                <div class="service-grid">
                    <article class="service-card" data-service-card data-service="counseling">
                        <div class="service-icon">C</div>
                        <h3>Counseling</h3>
                        <p>Guidance and one-on-one support.</p>
                        <small>Intake form required</small>
                    </article>

                    <article class="service-card" data-service-card data-service="testing">
                        <div class="service-icon">P</div>
                        <h3>Psychological Testing</h3>
                        <p>Assessment and testing request.</p>
                        <small>Testing request form required</small>
                    </article>

                    <article class="service-card" data-service-card data-service="referral" data-disabled="0">
                        <div class="service-icon">R</div>
                        <h3>Referral</h3>
                        <p>Submit a referral for someone else.</p>
                        <small>Referral slip required</small>
                    </article>
                </div>

                <div class="recent-forms">
                    <h3>Recent Forms</h3>
                    <p>These are the most recent forms you already filled up.</p>

                    <?php if (!empty($recentForms)): ?>
                        <div class="recent-forms-grid">
                            <?php foreach ($recentForms as $recentForm): ?>
                                <a class="recent-form-card" href="#" data-recent-form-service="<?php echo htmlspecialchars(strtolower((string) $recentForm["service"])); ?>">
                                    <small><?php echo htmlspecialchars((string) $recentForm["service"]); ?></small>
                                    <strong><?php echo htmlspecialchars((string) $recentForm["title"]); ?></strong>
                                    <span><?php echo htmlspecialchars((string) $recentForm["subtitle"]); ?></span>
                                    <span><?php echo htmlspecialchars(date("F j, Y g:i A", strtotime((string) $recentForm["date"]))); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No recent filled forms found yet.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section id="step2" class="step-panel">
                <h2 class="panel-title">Fill up the required form</h2>
                <p class="panel-subtitle">Complete the form below and submit it inside this page first. After it is saved, you can continue to the review step.</p>

                <div id="embedShell" class="embed-shell">
                    <div class="embed-note">
                        <p id="embedMessage">Select a service first so the correct form can load here.</p>
                        <span id="formSaveStatus" class="status-badge">Waiting for form</span>
                    </div>
                    <iframe id="embeddedFormFrame" class="embedded-form-frame" src="about:blank" title="Embedded appointment form"></iframe>
                </div>

                <div id="embedEmptyState" class="empty-state" style="display:none; margin-top:18px;"></div>
            </section>

            <section id="step3" class="step-panel">
                <h2 class="panel-title">Choose your preferred schedule</h2>
                <p class="panel-subtitle">Pick a counselor, date, and available time. The available time list updates based on the selected counselor and date.</p>

                <div class="schedule-grid">
                    <div class="field-group">
                        <label for="counselorId">Counselor</label>
                        <select id="counselorId">
                            <option value="" selected disabled>Select Counselor</option>
                            <?php foreach ($counselors as $counselor): ?>
                                <option value="<?php echo intval($counselor["id"]); ?>" data-name="<?php echo htmlspecialchars((string) $counselor["full_name"]); ?>">
                                    <?php echo htmlspecialchars((string) $counselor["full_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label for="appointmentDate">Date</label>
                        <input id="appointmentDate" type="date" min="<?php echo htmlspecialchars(date("Y-m-d")); ?>">
                    </div>

                    <div class="field-group">
                        <label for="appointmentTime">Time</label>
                        <input id="appointmentTime" type="time" step="3600">
                    </div>
                </div>

                <p id="slotMessage" class="schedule-help">Choose a counselor and date to load available slots.</p>
                <div id="availabilityCard" class="availability-card" style="display:none;">
                    <h4>Counselor Availability</h4>
                    <div id="availabilityContent" class="availability-list"></div>
                </div>
            </section>

            <section id="step4" class="step-panel">
                <h2 class="panel-title">Check the information before submitting</h2>
                <p class="panel-subtitle">Review your details before final submission.</p>

                <div class="review-layout">
                    <aside class="review-card">
                        <h3>Appointment Summary</h3>
                        <div id="appointmentReviewList" class="review-list"></div>
                    </aside>

                    <div id="formSummaryWrapper" class="summary-card">
                        <h3>Filled Form Details</h3>
                        <div id="formSummaryContent" class="empty-state">Fill up and save the form in Step 2 to see the summary here.</div>
                    </div>
                </div>
            </section>
        </div>

        <footer class="footer-bar">
            <div class="footer-actions">
                <button id="prevBtn" type="button" class="btn btn-secondary" style="display:none;">Previous</button>
                <button id="changeInfoBtn" type="button" class="btn btn-secondary" style="display:none;">Change Info</button>
                <button id="saveBtn" type="button" class="btn btn-secondary" style="display:none;">Save</button>
                <button id="pdfBtn" type="button" class="btn btn-ghost" style="display:none;">Save as PDF</button>
            </div>

            <div id="footerHint" class="footer-hint">Step 1 of 4</div>

            <div class="footer-actions">
                <button id="nextBtn" type="button" class="btn btn-primary">Next</button>
            </div>
        </footer>
    </div>

    <script>
        const userId = <?php echo json_encode($userId); ?>;
        const preselectedService = <?php echo json_encode($preselectedService); ?>;
        const canUseReferralForm = <?php echo $canUseReferralForm ? "true" : "false"; ?>;
        const stepLabels = {
            1: "Step 1 of 4",
            2: "Step 2 of 4",
            3: "Step 3 of 4",
            4: "Step 4 of 4"
        };

        let currentStep = 1;
        let selectedService = "";
        let embeddedFormSaved = false;
        let embeddedFormSummary = null;
        let embeddedFormPreviewUrl = "";
        let isSubmittingAppointment = false;
        let availableTimeValues = [];

        const serviceCards = Array.from(document.querySelectorAll("[data-service-card]"));
        const stepPanels = Array.from(document.querySelectorAll(".step-panel"));
        const prevBtn = document.getElementById("prevBtn");
        const changeInfoBtn = document.getElementById("changeInfoBtn");
        const saveBtn = document.getElementById("saveBtn");
        const nextBtn = document.getElementById("nextBtn");
        const pdfBtn = document.getElementById("pdfBtn");
        const footerHint = document.getElementById("footerHint");
        const embeddedFrame = document.getElementById("embeddedFormFrame");
        const embedMessage = document.getElementById("embedMessage");
        const formSaveStatus = document.getElementById("formSaveStatus");
        const embedEmptyState = document.getElementById("embedEmptyState");
        const counselorIdInput = document.getElementById("counselorId");
        const appointmentDateInput = document.getElementById("appointmentDate");
        const appointmentTimeInput = document.getElementById("appointmentTime");
        const slotMessage = document.getElementById("slotMessage");
        const appointmentReviewList = document.getElementById("appointmentReviewList");
        const formSummaryContent = document.getElementById("formSummaryContent");
        const availabilityCard = document.getElementById("availabilityCard");
        const availabilityContent = document.getElementById("availabilityContent");
        const recentFormCards = Array.from(document.querySelectorAll("[data-recent-form-service]"));

        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function normalizeServiceLabel(service) {
            if (service === "counseling") return "Counseling";
            if (service === "testing") return "Psychological Testing";
            if (service === "referral") return "Referral";
            return "Not selected";
        }

        function timeLabelToValue(label) {
            if (!label) {
                return "";
            }

            const probe = new Date(`2000-01-01 ${label}`);
            if (Number.isNaN(probe.getTime())) {
                return "";
            }

            const hours = String(probe.getHours()).padStart(2, "0");
            const minutes = String(probe.getMinutes()).padStart(2, "0");
            return `${hours}:${minutes}`;
        }

        function timeValueToLabel(value) {
            if (!value) {
                return "";
            }

            const probe = new Date(`2000-01-01T${value}`);
            if (Number.isNaN(probe.getTime())) {
                return value;
            }

            return probe.toLocaleTimeString([], {
                hour: "numeric",
                minute: "2-digit",
                hour12: true
            });
        }

        function getCounselorName() {
            const selectedOption = counselorIdInput.options[counselorIdInput.selectedIndex];
            return selectedOption ? (selectedOption.getAttribute("data-name") || selectedOption.textContent || "") : "";
        }

        function resetEmbeddedFormState() {
            embeddedFormSaved = false;
            embeddedFormSummary = null;
            embeddedFormPreviewUrl = "";
            formSaveStatus.textContent = "Waiting for form";
            formSaveStatus.className = "status-badge";
            formSummaryContent.innerHTML = '<div class="empty-state">Fill up and save the form in Step 2 to see the summary here.</div>';
            embeddedFrame.style.display = "block";
        }

        async function loadCounselorAvailability() {
            const counselorId = counselorIdInput.value;

            if (!availabilityCard || !availabilityContent) {
                return;
            }

            availabilityCard.style.display = "none";
            availabilityContent.innerHTML = "";

            if (!counselorId) {
                return;
            }

            try {
                const response = await fetch(`/campuscare-api/backend/schedule/get_availability.php?counselor_id=${encodeURIComponent(counselorId)}`);
                const data = await response.json();

                if (!data.success || !Array.isArray(data.availability) || data.availability.length === 0) {
                    availabilityCard.style.display = "block";
                    availabilityContent.innerHTML = '<div class="availability-item"><strong>No availability saved</strong><span>This counselor has no saved schedule yet.</span></div>';
                    return;
                }

                availabilityCard.style.display = "block";
                availabilityContent.innerHTML = data.availability.map((item) => `
                    <div class="availability-item">
                        <strong>${escapeHtml(item.day || "")}</strong>
                        <span>${escapeHtml(item.from || "")} - ${escapeHtml(item.to || "")}</span>
                    </div>
                `).join("");
            } catch (error) {
                availabilityCard.style.display = "block";
                availabilityContent.innerHTML = '<div class="availability-item"><strong>Unable to load schedule</strong><span>Please try selecting the counselor again.</span></div>';
            }
        }

        function setSelectedService(service) {
            selectedService = service;
            resetEmbeddedFormState();

            serviceCards.forEach((card) => {
                card.classList.toggle("selected", card.getAttribute("data-service") === service);
            });

            embedEmptyState.style.display = "none";

            if (service === "counseling") {
                embedMessage.textContent = "Complete the counseling intake form, then click Submit Intake Form inside the page.";
                embeddedFrame.src = "/campuscare-api/php-frontend/pages/appointments/counseling_intake_form.php?iframe=1";
            } else if (service === "referral") {
                embedMessage.textContent = "Complete the referral slip below, then submit it inside the page before continuing.";
                embeddedFrame.src = "/campuscare-api/php-frontend/pages/forms/referral_form.php?iframe=1";
            } else if (service === "testing") {
                embedMessage.textContent = "Complete the testing request form below, then submit it inside the page before continuing.";
                embeddedFrame.src = "/campuscare-api/php-frontend/pages/forms/testing_request_form.php?iframe=1";
            }
        }

        function updateStepUi() {
            stepPanels.forEach((panel, index) => {
                panel.classList.toggle("active", index === currentStep - 1);
            });

            document.querySelectorAll("[data-step-pill]").forEach((pill) => {
                const stepNumber = Number(pill.getAttribute("data-step-pill"));
                pill.classList.toggle("active", stepNumber === currentStep);
                pill.classList.toggle("done", stepNumber < currentStep);
            });

            prevBtn.style.display = currentStep > 1 && currentStep !== 4 ? "inline-flex" : "none";
            changeInfoBtn.style.display = currentStep === 4 ? "inline-flex" : "none";
            saveBtn.style.display = currentStep === 2 ? "inline-flex" : "none";
            pdfBtn.style.display = currentStep === 4 ? "inline-flex" : "none";
            footerHint.textContent = stepLabels[currentStep];

            if (currentStep === 3) {
                nextBtn.textContent = "Review";
            } else if (currentStep === 4) {
                nextBtn.textContent = isSubmittingAppointment ? "Submitting..." : "Submit";
            } else {
                nextBtn.textContent = "Next";
            }

            if (currentStep === 4) {
                renderReview();
            }
        }

        function validateCurrentStep() {
            if (currentStep === 1) {
                if (!selectedService) {
                    CampusCareAlerts.warning('Please select a service first.', 'Service Required');
                    return false;
                }
            }

            if (currentStep === 2) {
                if (!embeddedFormSaved) {
                    CampusCareAlerts.warning('Please submit and save the form inside Step 2 before continuing.', 'Form Not Saved');
                    return false;
                }
            }

            if (currentStep === 3) {
                if (!counselorIdInput.value) {
                    CampusCareAlerts.warning('Please select a counselor.', 'Counselor Required');
                    return false;
                }

                if (!appointmentDateInput.value) {
                    CampusCareAlerts.warning('Please choose a preferred date.', 'Date Required');
                    return false;
                }

                if (!appointmentTimeInput.value) {
                    CampusCareAlerts.warning('Please choose an available time.', 'Time Required');
                    return false;
                }

                if (availableTimeValues.length > 0 && !availableTimeValues.includes(appointmentTimeInput.value)) {
                    CampusCareAlerts.warning('Please choose one of the available time slots.', 'Invalid Time');
                    return false;
                }
            }

            return true;
        }

        function nextStep() {
            if (!validateCurrentStep()) {
                return;
            }

            if (currentStep < 4) {
                currentStep += 1;
                updateStepUi();
            } else {
                submitAppointment();
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep -= 1;
                updateStepUi();
            }
        }

        function saveCurrentEmbeddedForm() {
            if (currentStep !== 2) {
                return;
            }

            if (!embeddedFrame || !embeddedFrame.contentWindow) {
                CampusCareAlerts.warning('The form is still loading. Please try again.', 'Form Loading');
                return;
            }

            try {
                const iframeDocument = embeddedFrame.contentWindow.document;
                const form = iframeDocument.querySelector("form");

                if (!form) {
                    CampusCareAlerts.warning('No form was found to save in this step.', 'Form Not Found');
                    return;
                }

                form.requestSubmit();
            } catch (error) {
                CampusCareAlerts.error('Unable to save the form from this page right now.', 'Save Failed');
            }
        }

        function renderReview() {
            const appointmentDate = appointmentDateInput.value;
            const appointmentTime = timeValueToLabel(appointmentTimeInput.value);
            const reviewRows = [
                ["Service", normalizeServiceLabel(selectedService)],
                ["Counselor", getCounselorName() || "Not selected"],
                ["Preferred Date", appointmentDate || "Not selected"],
                ["Preferred Time", appointmentTime || "Not selected"]
            ];

            appointmentReviewList.innerHTML = reviewRows.map(([label, value]) => {
                return `
                    <div class="review-item">
                        <strong>${escapeHtml(label)}</strong>
                        <div>${escapeHtml(value)}</div>
                    </div>
                `;
            }).join("");

            if (!embeddedFormSummary || !Array.isArray(embeddedFormSummary.sections)) {
                formSummaryContent.innerHTML = '<div class="empty-state">Fill up and save the form in Step 2 to see the summary here.</div>';
                return;
            }

            formSummaryContent.innerHTML = embeddedFormSummary.sections.map((section) => {
                const entries = Array.isArray(section.entries) ? section.entries : [];
                return `
                    <section class="summary-section">
                        <h4>${escapeHtml(section.title || "Form Section")}</h4>
                        <div class="summary-grid">
                            ${entries.map((entry) => `
                                <div class="summary-entry ${entry.full ? "full" : ""}">
                                    <strong>${escapeHtml(entry.label || "")}</strong>
                                    <div>${escapeHtml(entry.value || "-")}</div>
                                </div>
                            `).join("")}
                        </div>
                    </section>
                `;
            }).join("");
        }

        async function loadAvailableSlots() {
            const counselorId = counselorIdInput.value;
            const appointmentDate = appointmentDateInput.value;

            appointmentTimeInput.value = "";
            availableTimeValues = [];

            if (!counselorId || !appointmentDate) {
                slotMessage.textContent = "Choose a counselor and date to load available slots.";
                return;
            }

            slotMessage.textContent = "Loading available time slots...";

            try {
                const response = await fetch(`/campuscare-api/backend/schedule/get_available_slots.php?counselor_id=${encodeURIComponent(counselorId)}&date=${encodeURIComponent(appointmentDate)}`);
                const data = await response.json();

                if (!data.success || !Array.isArray(data.slots) || data.slots.length === 0) {
                    slotMessage.textContent = "No available time slots found for that date. Please choose another date or counselor.";
                    return;
                }

                availableTimeValues = data.slots
                    .map((slot) => timeLabelToValue(slot))
                    .filter((slot) => slot !== "");

                if (availableTimeValues.length > 0) {
                    appointmentTimeInput.value = availableTimeValues[0];
                }
                slotMessage.textContent = `Available slots for ${appointmentDate}: choose one time below.`;
            } catch (error) {
                slotMessage.textContent = "Unable to load available slots right now.";
            }
        }

        async function submitAppointment() {
            if (isSubmittingAppointment) {
                return;
            }

            if (!validateCurrentStep()) {
                return;
            }

            // Show submission notice popup
            const confirmed = await CampusCareAlerts.confirm(
                'After you submit, please wait for the approval of your form or submission. Your appointment will be reviewed by our team.',
                'Submit Appointment?',
                'Yes, Submit',
                'Cancel'
            );

            if (!confirmed) {
                return;
            }

            isSubmittingAppointment = true;
            updateStepUi();
            CampusCareAlerts.loading('Submitting your appointment...');

            const payload = {
                user_id: userId,
                counselor_id: Number(counselorIdInput.value),
                counselor: getCounselorName(),
                service: normalizeServiceLabel(selectedService),
                appointment_date: appointmentDateInput.value,
                appointment_time: timeValueToLabel(appointmentTimeInput.value)
            };

            try {
                const response = await fetch("/campuscare-api/backend/appointments/add_appointment.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || "Failed to save appointment.");
                }

                CampusCareAlerts.close();
                const params = new URLSearchParams({
                    service: payload.service,
                    counselor: payload.counselor,
                    date: payload.appointment_date,
                    time: payload.appointment_time,
                    email: data.email_sent ? "sent" : "not-sent"
                });

                window.location.href = `/campuscare-api/php-frontend/pages/appointments/confirmation.php?${params.toString()}`;
            } catch (error) {
                CampusCareAlerts.error(error.message || 'Unable to submit appointment right now.', 'Submission Failed');
                isSubmittingAppointment = false;
                updateStepUi();
            }
        }

        window.addEventListener("message", (event) => {
            if (!event || !event.data || event.data.type !== "campuscare-form-saved") {
                return;
            }

            embeddedFormSaved = true;
            embeddedFormSummary = event.data.summary || null;
            embeddedFormPreviewUrl = event.data.previewUrl || "";
            formSaveStatus.textContent = "Form saved";
            formSaveStatus.className = "status-badge saved";

            if (event.data.message) {
                embedMessage.textContent = event.data.message;
            }

            if (currentStep === 2) {
                currentStep = 3;
                updateStepUi();
            } else if (currentStep === 4) {
                renderReview();
            }
        });

        serviceCards.forEach((card) => {
            card.addEventListener("click", () => {
                if (card.getAttribute("data-disabled") === "1") {
                    return;
                }

                setSelectedService(card.getAttribute("data-service") || "");
            });
        });

        recentFormCards.forEach((card) => {
            card.addEventListener("click", (event) => {
                event.preventDefault();
                const service = (card.getAttribute("data-recent-form-service") || "").toLowerCase();
                if (!service) {
                    return;
                }

                setSelectedService(service);
                currentStep = 2;
                updateStepUi();
            });
        });

        counselorIdInput.addEventListener("change", () => {
            loadCounselorAvailability();
            loadAvailableSlots();
        });
        appointmentDateInput.addEventListener("change", loadAvailableSlots);
        prevBtn.addEventListener("click", previousStep);
        changeInfoBtn.addEventListener("click", () => {
            currentStep = 2;
            updateStepUi();
        });
        saveBtn.addEventListener("click", saveCurrentEmbeddedForm);
        nextBtn.addEventListener("click", nextStep);
        pdfBtn.addEventListener("click", () => window.print());

        if (preselectedService) {
            setSelectedService(preselectedService);
        }

        updateStepUi();
    </script>
</body>
</html>

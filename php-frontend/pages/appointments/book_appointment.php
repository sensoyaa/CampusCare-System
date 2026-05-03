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
            font-size: 22px;
        }

        .panel-subtitle {
            margin: 0 0 24px;
            color: var(--muted);
        }

        .service-section-wrap {
            border: 2px solid var(--brand-3);
            border-radius: 18px;
            padding: 18px 20px 20px;
            background: linear-gradient(135deg, #f0f7fc 0%, #eef6ff 100%);
            margin-bottom: 24px;
        }

        .service-section-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--brand-2);
            margin: 0 0 12px;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
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
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.08);
            padding: 22px 16px;
            min-height: 160px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            gap: 10px;
            cursor: pointer;
            color: #000;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            box-shadow: 0 6px 18px rgba(0,0,0,0.07);
            overflow: hidden;
        }

        .svc-counseling { background: url("/campuscare-api/php-frontend/assets/images/bg/containe1bg.png") center/cover no-repeat; }
        .svc-testing    { background: url("/campuscare-api/php-frontend/assets/images/bg/container-2-bg.png") center/cover no-repeat; }
        .svc-referral   { background: url("/campuscare-api/php-frontend/assets/images/bg/bg3.png") center/cover no-repeat; }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(28,79,123,0.18);
        }

        .service-card.selected {
            box-shadow: 0 0 0 3px var(--brand-1), 0 14px 30px rgba(28,79,123,0.18);
            transform: translateY(-3px);
        }

        .service-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .service-icon {
            width: 90px;
            height: 90px;
            border-radius: 16px;
            background: rgba(255,255,255,0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .service-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
            display: block;
        }

        .service-card h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
        }

        .embed-shell {
            border: 1px solid var(--line);
            border-radius: 24px;
            background: #f7fbff;
            overflow: visible;
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

        .form-edit-toolbar {
            display: none;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 22px;
            background: #f0f7fc;
            border-bottom: 1px solid var(--brand-3);
        }

        .form-edit-toolbar.visible { display: flex; }

        .form-edit-toolbar p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
        }

        .form-edit-toolbar .btn-edit {
            background: #fff;
            color: var(--brand-1);
            border: 1.5px solid var(--brand-2);
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
        }

        .form-edit-toolbar .btn-edit:hover { background: var(--brand-3); }

        .form-edit-toolbar .btn-save {
            background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: none;
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
            height: 1px;
            min-height: 600px;
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

        .schedule-hidden-inputs {
            display: none;
        }

        .schedule-showcase {
            display: grid;
            grid-template-columns: 360px minmax(0, 1fr);
            gap: 26px;
            align-items: start;
        }

        .schedule-card {
            border: 1px solid #e3ebf2;
            border-radius: 24px;
            background: #fff;
            padding: 24px 26px;
            box-shadow: 0 18px 40px rgba(24, 50, 71, 0.06);
        }

        .schedule-card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .schedule-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef6ff;
            color: var(--brand-1);
            font-size: 18px;
        }

        .schedule-card-head h3 {
            margin: 0;
            font-size: 16px;
        }

        .schedule-card-head p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .schedule-selected-date {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            background: linear-gradient(135deg, #f4f8fc 0%, #eef6ff 100%);
            border: 1px solid #deebf5;
        }

        .schedule-selected-date strong,
        .schedule-selected-date span {
            display: block;
        }

        .schedule-selected-date strong {
            margin-bottom: 4px;
            font-size: 12px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--muted);
        }

        .schedule-selected-date span {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
        }

        .time-slot-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .time-slot-btn {
            border: 1px solid #e0e8ef;
            border-radius: 16px;
            background: #f8fafc;
            color: var(--ink);
            padding: 16px 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease, background 0.16s ease;
        }

        .time-slot-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(24, 50, 71, 0.08);
            border-color: #bfd4e4;
        }

        .time-slot-btn.selected {
            background: linear-gradient(135deg, #dcecff 0%, #c6ddf8 100%);
            border-color: #8ab3d8;
            color: var(--brand-1);
            box-shadow: 0 14px 30px rgba(28, 79, 123, 0.12);
        }

        .calendar-shell {
            overflow: hidden;
        }

        .calendar-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .calendar-nav-btn {
            width: 42px;
            height: 42px;
            border: 1px solid #dbe5ee;
            border-radius: 14px;
            background: #fff;
            color: var(--brand-1);
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .calendar-nav-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .calendar-month-label {
            font-size: 28px;
            font-weight: 700;
            color: var(--ink);
            text-align: center;
            flex: 1;
        }

        .calendar-weekdays,
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
        }

        .calendar-weekdays {
            margin-bottom: 10px;
        }

        .calendar-weekdays span {
            text-align: center;
            font-size: 14px;
            color: #6c8094;
        }

        .calendar-day,
        .calendar-filler {
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            font-size: 18px;
        }

        .calendar-filler {
            color: #b8c5d2;
        }

        .calendar-day {
            border: 0;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
            transition: background 0.16s ease, color 0.16s ease, transform 0.16s ease;
        }

        .calendar-day:hover {
            background: #eef5fb;
            transform: translateY(-1px);
        }

        .calendar-day.today {
            box-shadow: inset 0 0 0 1px #bfd4e4;
        }

        .calendar-day.selected {
            background: #bfd7ee;
            color: var(--brand-1);
            font-weight: 700;
        }

        .calendar-day.disabled {
            color: #c4ced8;
            cursor: not-allowed;
        }

        .review-layout,
        .summary-card {
            border: 1px solid #d8e2ea;
            border-radius: 20px;
            background: #f5f8fb;
            padding: 22px;
            box-shadow: 0 18px 40px rgba(24, 50, 71, 0.06);
        }

        .summary-card h3 {
            margin: 0;
            font-size: 21px;
            color: var(--ink);
        }

        .summary-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .summary-card-head p {
            margin: 8px 0 0;
            color: var(--muted);
            max-width: 620px;
            line-height: 1.55;
        }

        .summary-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 999px;
            background: #fff;
            color: #324a60;
            border: 1px solid #d5dee7;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .review-sheet {
            border: 1px solid #cfd8df;
            border-radius: 8px;
            padding: 24px;
            background: #fff;
            color: #111;
            font-family: Georgia, "Times New Roman", serif;
        }

        .review-document {
            background: #fff;
            color: #111;
        }

        .review-document-header {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr);
            gap: 16px;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid #111;
        }

        .review-document-seal {
            width: 68px;
            height: 68px;
            object-fit: contain;
            justify-self: start;
        }

        .review-document-heading {
            text-align: center;
        }

        .review-document-heading h4 {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .review-document-heading p {
            margin: 4px 0 0;
            font-size: 12px;
            line-height: 1.45;
            color: #222;
        }

        .review-document-heading .office-title {
            margin-top: 8px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.9px;
            text-transform: uppercase;
        }

        .review-document-heading .office-subtitle {
            font-style: italic;
            font-size: 12px;
        }

        .review-document-heading .document-title {
            margin-top: 10px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .review-document-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
            margin-bottom: 18px;
        }

        .review-meta-box {
            border: 1px solid #111;
            min-height: 64px;
            padding: 10px 12px;
            background: #fff;
        }

        .review-meta-box strong,
        .review-document-field strong {
            display: block;
            margin-bottom: 6px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #111;
        }

        .review-meta-box div,
        .review-document-field div {
            font-size: 14px;
            line-height: 1.55;
            color: #111;
            word-break: break-word;
            white-space: pre-line;
        }

        .review-document-signature {
            margin-top: 8px;
            max-width: 220px;
            min-height: 54px;
            display: block;
            object-fit: contain;
        }

        .summary-card {
            display: grid;
            gap: 16px;
        }

        .summary-sheet {
            border: 0;
            border-radius: 0;
            padding: 0;
            background: transparent;
        }

        .summary-section {
            border: 1px solid #111;
            border-radius: 0;
            padding: 16px 18px;
            background: #fff;
            margin-bottom: 14px;
            box-shadow: none;
        }

        .summary-section:last-child {
            margin-bottom: 0;
        }

        .summary-section h4 {
            margin: 0 0 14px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            text-align: left;
            letter-spacing: 0.9px;
            text-transform: uppercase;
            color: #111;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .summary-entry,
        .review-document-field {
            min-height: 76px;
            text-align: left;
            border: 1px solid #111;
            border-radius: 0;
            background: #fff;
            padding: 12px 14px;
        }

        .summary-entry.full {
            grid-column: 1 / -1;
        }

        .review-document-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .review-document-fields.three-col {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .review-document-field.full {
            grid-column: 1 / -1;
        }

        .review-document-footer {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .summary-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-outline {
            background: #fff;
            color: var(--brand-1);
            border: 1.5px solid #c2d7e6;
            box-shadow: 0 10px 22px rgba(28, 79, 123, 0.08);
        }

        .btn-outline:hover {
            background: #f2f8fc;
        }

        .footer-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 22px 34px 30px;
            border-top: 2px solid var(--brand-3);
            background: linear-gradient(180deg, #f0f7fc 0%, #ffffff 100%);
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
            box-shadow: 0 8px 24px rgba(28, 79, 123, 0.22);
        }

        .btn-primary:hover { box-shadow: 0 12px 32px rgba(28, 79, 123, 0.30); }

        .btn-secondary {
            background: #fff;
            color: var(--brand-1);
            border: 1.5px solid var(--brand-2);
        }

        .btn-secondary:hover { background: var(--brand-3); }

        .btn-ghost {
            background: transparent;
            color: var(--brand-2);
            border: 1.5px dashed var(--brand-2);
        }

        .footer-hint {
            color: var(--muted);
            font-size: 14px;
        }

        .confirm-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 32, 46, 0.48);
            backdrop-filter: blur(8px);
            z-index: 1200;
        }

        .confirm-modal.open {
            display: flex;
        }

        .confirm-dialog {
            width: min(100%, 520px);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid rgba(220, 233, 244, 0.95);
            border-radius: 28px;
            padding: 30px 28px 26px;
            box-shadow: 0 30px 70px rgba(14, 34, 50, 0.24);
            text-align: center;
        }

        .confirm-dialog.is-loading .confirm-actions {
            opacity: 0.88;
        }

        .confirm-illustration {
            width: 92px;
            height: 92px;
            margin: 0 auto 18px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at top, rgba(255,255,255,0.9), transparent 46%),
                linear-gradient(180deg, #eff8fb 0%, #deeff7 100%);
            border: 2px solid #8bb9ce;
            color: var(--brand-2);
        }

        .confirm-dialog h3 {
            margin: 0 0 12px;
            font-size: 31px;
            line-height: 1.15;
            color: #33485b;
        }

        .confirm-dialog p {
            margin: 0 auto 24px;
            max-width: 390px;
            color: #5b6f82;
            font-size: 16px;
            line-height: 1.7;
        }

        .confirm-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 920px) {
            .service-grid,
            .schedule-showcase,
            .summary-grid,
            .review-document-meta,
            .review-document-fields,
            .review-document-fields.three-col,
            .review-document-footer {
                grid-template-columns: 1fr;
            }

            .embedded-form-frame {
                min-height: 420px;
            }

            .calendar-month-label {
                font-size: 22px;
            }

            .time-slot-grid {
                grid-template-columns: 1fr;
            }

            .summary-card-head {
                flex-direction: column;
                align-items: stretch;
            }

            .review-document-header {
                grid-template-columns: 1fr;
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

            .summary-actions,
            .confirm-actions {
                justify-content: stretch;
            }

            .summary-actions .btn,
            .confirm-actions .btn {
                width: 100%;
            }

            .confirm-dialog {
                padding: 26px 20px 22px;
            }

            .confirm-dialog h3 {
                font-size: 26px;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #fff;
                padding: 0;
            }

            .booking-head,
            .topbar,
            .sidebar,
            .panel-title,
            .panel-subtitle,
            .summary-card-head,
            .summary-actions,
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
                box-shadow: none;
                background: #fff;
            }

            .review-sheet {
                border: 0;
                padding: 0;
                box-shadow: none;
            }

            .review-document {
                width: 100%;
                max-width: 100%;
            }

            .review-document-header,
            .review-document-meta,
            .review-document-fields,
            .review-document-fields.three-col,
            .review-document-footer {
                break-inside: avoid;
            }

            .summary-section {
                break-inside: avoid;
                border-color: #111;
                background: #fff;
                box-shadow: none;
            }

            .summary-actions,
            .confirm-modal {
                display: none !important;
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
            <p>Select a service below, fill up the form, pick your schedule, then review and submit.</p>

        </header>

        <div class="booking-body">
            <section id="step1" class="step-panel active">

                <div class="service-section-wrap">
                <p class="service-section-label">Select a service</p>
                <div class="service-grid">
                    <article class="service-card svc-counseling" data-service-card data-service="counseling">
                        <span class="service-icon"><img src="/campuscare-api/php-frontend/assets/images/icons/counseling.png" alt=""></span>
                        <h4>Book Counseling</h4>
                    </article>
                    <article class="service-card svc-testing" data-service-card data-service="testing">
                        <span class="service-icon"><img src="/campuscare-api/php-frontend/assets/images/icons/Mental-Test.png" alt=""></span>
                        <h4>Psychological Testing</h4>
                    </article>
                    <article class="service-card svc-referral" data-service-card data-service="referral" data-disabled="0">
                        <span class="service-icon"><img src="/campuscare-api/php-frontend/assets/images/icons/workshop.png" alt=""></span>
                        <h4>Referral</h4>
                    </article>
                </div>
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
                <h2 class="panel-title">Fill up the form</h2>
                <p class="panel-subtitle">Complete and submit the form below. It will automatically move you to the next step when done.</p>

                <div id="embedShell" class="embed-shell">
                    <div class="embed-note">
                        <p id="embedMessage">Select a service first so the correct form can load here.</p>
                        <span id="formSaveStatus" class="status-badge">Waiting for form</span>
                    </div>
                    <div id="formEditToolbar" class="form-edit-toolbar">
                        <p id="formEditStatus">Form saved. Your information is currently read-only.</p>
                    </div>
                    <iframe id="embeddedFormFrame" class="embedded-form-frame" src="about:blank" title="Embedded appointment form"></iframe>
                </div>

                <div id="embedEmptyState" class="empty-state" style="display:none; margin-top:18px;"></div>
            </section>

            <section id="step3" class="step-panel">
                <h2 class="panel-title">Choose your preferred schedule</h2>
                <p class="panel-subtitle">Pick a date and available time slot for your appointment.</p>

                <div class="schedule-hidden-inputs">
                    <input id="appointmentDate" type="date" min="<?php echo htmlspecialchars(date("Y-m-d")); ?>">
                    <input id="appointmentTime" type="time" step="3600">
                </div>

                <div class="schedule-showcase">
                    <div class="schedule-card">
                        <div class="schedule-card-head">
                            <span class="schedule-card-icon">&#128339;</span>
                            <div>
                                <h3>Time Slot</h3>
                                <p>Select an available hour for your appointment.</p>
                            </div>
                        </div>

                        <div class="schedule-selected-date">
                            <strong>Selected Date</strong>
                            <span id="selectedDateLabel">Choose a date first</span>
                        </div>

                        <div id="timeSlotGrid" class="time-slot-grid"></div>
                    </div>

                    <div class="schedule-card calendar-shell">
                        <div class="schedule-card-head">
                            <span class="schedule-card-icon">&#128197;</span>
                            <div>
                                <h3>Select Date</h3>
                                <p>Choose the day you want to book.</p>
                            </div>
                        </div>

                        <div class="calendar-toolbar">
                            <button id="calendarPrevBtn" type="button" class="calendar-nav-btn" aria-label="Previous month">&#8249;</button>
                            <div id="calendarMonthLabel" class="calendar-month-label"></div>
                            <button id="calendarNextBtn" type="button" class="calendar-nav-btn" aria-label="Next month">&#8250;</button>
                        </div>

                        <div class="calendar-weekdays">
                            <span>Su</span>
                            <span>Mo</span>
                            <span>Tu</span>
                            <span>We</span>
                            <span>Th</span>
                            <span>Fr</span>
                            <span>Sa</span>
                        </div>
                        <div id="calendarGrid" class="calendar-grid"></div>
                    </div>
                </div>

                <p id="slotMessage" class="schedule-help"></p>
            </section>

            <section id="step4" class="step-panel">
                <h2 class="panel-title">Review before submitting</h2>
                <p class="panel-subtitle">Check your details below. Once submitted, wait for the counselor's confirmation.</p>

                <div id="formSummaryWrapper" class="summary-card">
                    <div class="summary-card-head">
                        <div>
                            <h3>Check Information</h3>
                            <p>Review the appointment details and your completed form before sending it for approval. You can also save a PDF copy from this final step.</p>
                        </div>
                        <span class="summary-badge">Final Review</span>
                    </div>
                    <div id="formSummaryContent" class="empty-state">Form details will appear here after you fill up the form.</div>
                    <div class="summary-actions">
                        <button id="savePdfBtn" type="button" class="btn btn-outline" style="display:none;">Save PDF</button>
                    </div>
                </div>
            </section>
        </div>

        <footer class="footer-bar">
            <div class="footer-actions">
                <button id="prevBtn" type="button" class="btn btn-secondary" style="display:none;">&#8592; Back</button>
                <button id="reviewToggleBtn" type="button" class="btn btn-secondary" style="display:none;">Edit</button>
            </div>

            <div class="footer-actions">
                <button id="nextBtn" type="button" class="btn btn-primary">Next &#8594;</button>
            </div>
        </footer>
    </div>

    <div id="submitConfirmModal" class="confirm-modal" aria-hidden="true">
        <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="submitConfirmTitle" aria-describedby="submitConfirmMessage">
            <div class="confirm-illustration" aria-hidden="true">
                <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 12.25C18.4101 12.25 15.5 15.1601 15.5 18.75C15.5 21.179 16.8358 23.2991 18.8135 24.4185C19.4461 24.7765 19.8333 25.4455 19.8333 26.1724V27.5H24.1667V26.1724C24.1667 25.4455 24.5539 24.7765 25.1865 24.4185C27.1642 23.2991 28.5 21.179 28.5 18.75C28.5 15.1601 25.5899 12.25 22 12.25Z" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19.25 31.1667H24.75" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <path d="M18.3333 35.75H25.6667" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
            <h3 id="submitConfirmTitle">Confirm Appointment Submission?</h3>
            <p id="submitConfirmMessage">Please review your information before submitting your appointment request. Once submitted, your form will be sent for approval and review by the team.</p>
            <div class="confirm-actions">
                <button id="confirmSubmitBtn" type="button" class="btn btn-primary">Submit Appointment</button>
                <button id="cancelSubmitBtn" type="button" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const userId = <?php echo json_encode($userId); ?>;
        const preselectedService = <?php echo json_encode($preselectedService); ?>;
        const canUseReferralForm = <?php echo $canUseReferralForm ? "true" : "false"; ?>;
        let currentStep = 1;
        let selectedService = "";
        let embeddedFormSaved = false;
        let embeddedFormSummary = null;
        let embeddedFormPreviewUrl = "";
        let isSubmittingAppointment = false;
        let isEditMode = false;
        let currentFormSrc = "";
        let currentFormMode = "edit";
        let reviewEditActive = false;
        let returnStepAfterSave = 3;
        let pendingRestoreStep = null;
        const bookingStateKey = `campuscare-booking-state-${userId}`;

        const serviceCards = Array.from(document.querySelectorAll("[data-service-card]"));
        const stepPanels = Array.from(document.querySelectorAll(".step-panel"));
        const prevBtn = document.getElementById("prevBtn");
        const reviewToggleBtn = document.getElementById("reviewToggleBtn");
        const nextBtn = document.getElementById("nextBtn");
        const embeddedFrame = document.getElementById("embeddedFormFrame");
        const embedMessage = document.getElementById("embedMessage");
        const formSaveStatus = document.getElementById("formSaveStatus");
        const formEditToolbar = document.getElementById("formEditToolbar");
        const formEditStatus = document.getElementById("formEditStatus");
        const embedEmptyState = document.getElementById("embedEmptyState");
        const appointmentDateInput = document.getElementById("appointmentDate");
        const appointmentTimeInput = document.getElementById("appointmentTime");
        const slotMessage = document.getElementById("slotMessage");
        const appointmentReviewList = document.getElementById("appointmentReviewList");
        const formSummaryContent = document.getElementById("formSummaryContent");
        const savePdfBtn = document.getElementById("savePdfBtn");
        const recentFormCards = Array.from(document.querySelectorAll("[data-recent-form-service]"));
        const selectedDateLabel = document.getElementById("selectedDateLabel");
        const timeSlotGrid = document.getElementById("timeSlotGrid");
        const calendarMonthLabel = document.getElementById("calendarMonthLabel");
        const calendarGrid = document.getElementById("calendarGrid");
        const calendarPrevBtn = document.getElementById("calendarPrevBtn");
        const calendarNextBtn = document.getElementById("calendarNextBtn");
        const submitConfirmModal = document.getElementById("submitConfirmModal");
        const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
        const cancelSubmitBtn = document.getElementById("cancelSubmitBtn");
        const timeSlotOptions = ["09:00", "10:00", "11:00", "13:00", "14:00", "15:00"];
        const today = new Date();
        const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        let visibleCalendarMonth = new Date(today.getFullYear(), today.getMonth(), 1);

        function persistBookingState() {
            try {
                const state = {
                    step: currentStep,
                    service: selectedService,
                    appointmentDate: appointmentDateInput ? appointmentDateInput.value : "",
                    appointmentTime: appointmentTimeInput ? appointmentTimeInput.value : "",
                    currentFormMode,
                    reviewEditActive,
                    embeddedFormSaved
                };
                sessionStorage.setItem(bookingStateKey, JSON.stringify(state));
            } catch (error) {}
        }

        function clearBookingState() {
            try {
                sessionStorage.removeItem(bookingStateKey);
            } catch (error) {}
        }

        function readBookingState() {
            try {
                const raw = sessionStorage.getItem(bookingStateKey);
                if (!raw) {
                    return null;
                }

                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === "object" ? parsed : null;
            } catch (error) {
                return null;
            }
        }

        function prefillSchedule() {
            if (!appointmentDateInput.value) {
                appointmentDateInput.value = formatDateValue(todayDateOnly);
            }
            if (!appointmentTimeInput.value) {
                appointmentTimeInput.value = timeSlotOptions[0];
            }
            syncScheduleUi();
        }

        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/\"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function normalizeServiceLabel(service) {
            if (service === "counseling") return "Counseling";
            if (service === "testing") return "Psychological Testing";
            if (service === "referral") return "Referral";
            return "Not selected";
        }

        function formatDateValue(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        }

        function parseDateValue(value) {
            if (!value) {
                return null;
            }

            const parts = value.split("-");
            if (parts.length !== 3) {
                return null;
            }

            const year = Number(parts[0]);
            const month = Number(parts[1]) - 1;
            const day = Number(parts[2]);
            return new Date(year, month, day);
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

        function formatSelectedDateLabel(value) {
            const parsedDate = parseDateValue(value);
            if (!parsedDate) {
                return "Choose a date first";
            }

            return parsedDate.toLocaleDateString("en-PH", {
                month: "long",
                day: "numeric",
                year: "numeric"
            });
        }

        function isPastDate(date) {
            return new Date(date.getFullYear(), date.getMonth(), date.getDate()) < todayDateOnly;
        }

        function renderTimeSlots() {
            timeSlotGrid.innerHTML = timeSlotOptions.map((slotValue) => `
                <button
                    type="button"
                    class="time-slot-btn ${appointmentTimeInput.value === slotValue ? "selected" : ""}"
                    data-time-slot="${slotValue}"
                >
                    ${escapeHtml(timeValueToLabel(slotValue))}
                </button>
            `).join("");

            timeSlotGrid.querySelectorAll("[data-time-slot]").forEach((button) => {
                button.addEventListener("click", () => {
                    appointmentTimeInput.value = button.getAttribute("data-time-slot") || "";
                    renderTimeSlots();
                    if (slotMessage) {
                        slotMessage.textContent = "";
                    }
                });
            });
        }

        function renderCalendar() {
            const currentMonth = new Date(visibleCalendarMonth.getFullYear(), visibleCalendarMonth.getMonth(), 1);
            const firstDayIndex = currentMonth.getDay();
            const daysInMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0).getDate();
            const selectedValue = appointmentDateInput.value;
            const todayMonthStart = new Date(todayDateOnly.getFullYear(), todayDateOnly.getMonth(), 1).getTime();

            calendarMonthLabel.textContent = currentMonth.toLocaleDateString("en-PH", {
                month: "long",
                year: "numeric"
            });
            calendarPrevBtn.disabled = currentMonth.getTime() <= todayMonthStart;

            const cells = [];

            for (let i = 0; i < firstDayIndex; i += 1) {
                cells.push('<div class="calendar-filler"></div>');
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const currentDate = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                const value = formatDateValue(currentDate);
                const isToday = value === formatDateValue(todayDateOnly);
                const isSelected = value === selectedValue;
                const disabledClass = isPastDate(currentDate) ? "disabled" : "";

                cells.push(`
                    <button
                        type="button"
                        class="calendar-day ${isToday ? "today" : ""} ${isSelected ? "selected" : ""} ${disabledClass}"
                        data-calendar-date="${value}"
                        ${disabledClass ? "disabled" : ""}
                    >
                        ${day}
                    </button>
                `);
            }

            calendarGrid.innerHTML = cells.join("");

            calendarGrid.querySelectorAll("[data-calendar-date]").forEach((button) => {
                button.addEventListener("click", () => {
                    appointmentDateInput.value = button.getAttribute("data-calendar-date") || "";
                    selectedDateLabel.textContent = formatSelectedDateLabel(appointmentDateInput.value);
                    renderCalendar();
                    if (slotMessage) {
                        slotMessage.textContent = "";
                    }
                });
            });
        }

        function syncScheduleUi() {
            const selectedDate = parseDateValue(appointmentDateInput.value);
            if (selectedDate) {
                visibleCalendarMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
            }

            selectedDateLabel.textContent = formatSelectedDateLabel(appointmentDateInput.value);
            renderCalendar();
            renderTimeSlots();
        }

        function resetEmbeddedFormState() {
            embeddedFormSaved = false;
            embeddedFormSummary = null;
            embeddedFormPreviewUrl = "";
            isEditMode = false;
            currentFormMode = "edit";
            reviewEditActive = false;
            returnStepAfterSave = 3;
            formSaveStatus.textContent = "Waiting for form";
            formSaveStatus.className = "status-badge";
            formSummaryContent.innerHTML = '<div class="empty-state">Form details will appear here after you fill up the form.</div>';
            embeddedFrame.style.display = "block";
            formEditToolbar.classList.remove("visible");
            persistBookingState();
        }

        function buildEmbeddedFormUrl(mode = "") {
            if (!currentFormSrc) {
                return "about:blank";
            }

            const separator = currentFormSrc.includes("?") ? "&" : "?";
            return mode ? `${currentFormSrc}${separator}mode=${encodeURIComponent(mode)}` : currentFormSrc;
        }

        function setEmbeddedFormMode(mode, options = {}) {
            const { reload = true } = options;
            currentFormMode = mode;
            isEditMode = mode === "edit";
            formEditStatus.textContent = isEditMode
                ? "Edit mode is on. Update your information, then save to lock it again."
                : "Form saved. Your information is currently read-only.";

            if (reload) {
                embeddedFrame.src = buildEmbeddedFormUrl(mode);
            }

            persistBookingState();
        }

        function setSelectedService(service) {
            selectedService = service;
            resetEmbeddedFormState();

            serviceCards.forEach((card) => {
                card.classList.toggle("selected", card.getAttribute("data-service") === service);
            });

            embedEmptyState.style.display = "none";

            if (service === "counseling") {
                embedMessage.textContent = "Complete the counseling intake form, then submit it to continue.";
                currentFormSrc = "/campuscare-api/php-frontend/pages/appointments/counseling_intake_form.php?iframe=1";
            } else if (service === "referral") {
                embedMessage.textContent = "Complete the referral slip below, then submit it to continue.";
                currentFormSrc = "/campuscare-api/php-frontend/pages/forms/referral_form.php?iframe=1";
            } else if (service === "testing") {
                embedMessage.textContent = "Complete the testing request form below, then submit it to continue.";
                currentFormSrc = "/campuscare-api/php-frontend/pages/forms/testing_request_form.php?iframe=1";
            }

            setEmbeddedFormMode("view");
            currentStep = 2;
            persistBookingState();
            updateStepUi();
        }

        function updateStepUi() {
            stepPanels.forEach((panel, index) => {
                panel.classList.toggle("active", index === currentStep - 1);
            });

            prevBtn.style.display = currentStep > 1 ? "inline-flex" : "none";
            const canToggleOnFormStep = currentStep === 2 && embeddedFormSaved;
            const canToggleOnReviewStep = currentStep === 4 && embeddedFormSaved;
            const isEditingOnFormStep = currentStep === 2 && currentFormMode === "edit";

            reviewToggleBtn.style.display = (canToggleOnFormStep || canToggleOnReviewStep) ? "inline-flex" : "none";
            reviewToggleBtn.textContent = isEditingOnFormStep ? "Save" : "Edit";
            reviewToggleBtn.className = isEditingOnFormStep ? "btn btn-primary" : "btn btn-secondary";
            nextBtn.style.display = currentStep === 1 || isEditingOnFormStep ? "none" : "inline-flex";

            if (currentStep === 3) {
                nextBtn.innerHTML = "Review &#8594;";
                prefillSchedule();
            } else if (currentStep === 4) {
                nextBtn.innerHTML = isSubmittingAppointment ? "Submitting..." : "Submit Appointment";
                renderReview();
            } else {
                nextBtn.innerHTML = "Next &#8594;";
            }

            if (savePdfBtn) {
                savePdfBtn.style.display = currentStep === 4 ? "inline-flex" : "none";
                savePdfBtn.disabled = currentStep !== 4 || isSubmittingAppointment;
            }

            persistBookingState();
        }

        function validateCurrentStep() {
            if (currentStep === 1 && !selectedService) {
                CampusCareAlerts.warning("Please select a service first.", "Service Required");
                return false;
            }

            if (currentStep === 2 && !embeddedFormSaved) {
                saveCurrentEmbeddedForm();
                return false;
            }

            if (currentStep === 3) {
                if (!appointmentDateInput.value) {
                    CampusCareAlerts.warning("Please choose a preferred date.", "Date Required");
                    return false;
                }

                if (!appointmentTimeInput.value) {
                    CampusCareAlerts.warning("Please choose a preferred time.", "Time Required");
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
                persistBookingState();
                updateStepUi();
            } else {
                openSubmitConfirmation();
            }
        }

        function previousStep() {
            if (currentStep === 2 && reviewEditActive) {
                reviewEditActive = false;
                currentStep = 4;
                setEmbeddedFormMode("view");
                persistBookingState();
                updateStepUi();
                return;
            }

            if (currentStep > 1) {
                currentStep -= 1;
                persistBookingState();
                updateStepUi();
            }
        }

        function saveCurrentEmbeddedForm() {
            if (currentStep !== 2) {
                return;
            }

            if (!embeddedFrame || !embeddedFrame.contentWindow) {
                CampusCareAlerts.warning("The form is still loading. Please try again.", "Form Loading");
                return;
            }

            try {
                const iframeDocument = embeddedFrame.contentWindow.document;
                const form = iframeDocument.querySelector("form");

                if (!form) {
                    CampusCareAlerts.warning("No form was found to save in this step.", "Form Not Found");
                    return;
                }

                form.requestSubmit();
            } catch (error) {
                CampusCareAlerts.error("Unable to save the form from this page right now.", "Save Failed");
            }
        }

        function renderReview() {
            const appointmentDate = appointmentDateInput.value
                ? new Date(appointmentDateInput.value).toLocaleDateString("en-PH", { weekday: "long", year: "numeric", month: "long", day: "numeric" })
                : "Not selected";
            const appointmentTime = timeValueToLabel(appointmentTimeInput.value) || "Not selected";
            const reviewRows = [
                ["Service", normalizeServiceLabel(selectedService)],
                ["Preferred Date", appointmentDate],
                ["Preferred Time", appointmentTime]
            ];

            if (!embeddedFormSummary || !Array.isArray(embeddedFormSummary.sections)) {
                formSummaryContent.innerHTML = '<div class="empty-state">Form details will appear here after you fill up the form.</div>';
                return;
            }

            const documentTitle = String(embeddedFormSummary.title || "Counseling Intake Form").toUpperCase();
            formSummaryContent.innerHTML = `<div class="review-sheet">
                <div class="review-sheet review-document">
                    <div class="review-document-header">
                        <img class="review-document-seal" src="/campuscare-api/php-frontend/assets/images/buksulogo.png" alt="BukSU seal">
                        <div class="review-document-heading">
                            <h4>BUKIDNON STATE UNIVERSITY</h4>
                            <p>Fortich Street, Malaybalay City, Bukidnon</p>
                            <p>Tel/Fax: (088) 813-5661 | www.buksu.edu.ph</p>
                            <div class="office-title">Student Welfare and Engagement Unit</div>
                            <div class="office-subtitle">Guidance and Counseling Services</div>
                            <div class="document-title">${escapeHtml(documentTitle)}</div>
                        </div>
                    </div>

                    <div class="review-document-meta">
                        ${reviewRows.map(([label, value]) => `
                            <div class="review-meta-box">
                                <strong>${escapeHtml(label)}</strong>
                                <div>${escapeHtml(value)}</div>
                            </div>
                        `).join("")}
                    </div>

                <div class="summary-sheet">
                    <section class="summary-section">
                        <h4>Submission Summary</h4>
                        <div class="review-document-fields three-col">
                            <div class="review-document-field">
                                <strong>Service</strong>
                                <div>${escapeHtml(normalizeServiceLabel(selectedService))}</div>
                            </div>
                            <div class="review-document-field">
                                <strong>Preferred Date</strong>
                                <div>${escapeHtml(appointmentDate)}</div>
                            </div>
                            <div class="review-document-field">
                                <strong>Preferred Time</strong>
                                <div>${escapeHtml(appointmentTime)}</div>
                            </div>
                        </div>
                    </section>
                    ${embeddedFormSummary.sections.map((section) => {
                const entries = Array.isArray(section.entries) ? section.entries : [];
                return `
                    <section class="summary-section">
                        <h4>${escapeHtml(section.title || "")}</h4>
                        <div class="review-document-fields">
                            ${entries.map((entry) => `
                                <div class="review-document-field ${entry.full ? "full" : ""}">
                                    <strong>${escapeHtml(entry.label || "")}</strong>
                                    <div>${escapeHtml(entry.value || "-")}</div>
                                    ${entry.signature ? `<img class="review-document-signature" src="${escapeHtml(entry.signature)}" alt="${escapeHtml((entry.label || "Signature") + " signature")}">` : ""}
                                </div>
                            `).join("")}
                        </div>
                    </section>
                `;
            }).join("")}
                    <div class="review-document-footer">
                        <div>Document Code: GCS-F-016</div>
                        <div>Revision No: 0</div>
                        <div>Issue Date: February 18, 2025</div>
                        <div>Page 1 of 3</div>
                    </div>
                </div>
            </div>`;
        }

        function resetSubmitConfirmationState() {
            if (!submitConfirmModal) {
                return;
            }

            const dialog = submitConfirmModal.querySelector(".confirm-dialog");
            const illustration = submitConfirmModal.querySelector(".confirm-illustration");
            const title = document.getElementById("submitConfirmTitle");
            const message = document.getElementById("submitConfirmMessage");

            if (dialog) {
                dialog.classList.remove("is-loading");
            }

            if (illustration) {
                illustration.innerHTML = `
                    <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 12.25C18.4101 12.25 15.5 15.1601 15.5 18.75C15.5 21.179 16.8358 23.2991 18.8135 24.4185C19.4461 24.7765 19.8333 25.4455 19.8333 26.1724V27.5H24.1667V26.1724C24.1667 25.4455 24.5539 24.7765 25.1865 24.4185C27.1642 23.2991 28.5 21.179 28.5 18.75C28.5 15.1601 25.5899 12.25 22 12.25Z" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M19.25 31.1667H24.75" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path>
                        <path d="M18.3333 35.75H25.6667" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"></path>
                    </svg>
                `;
            }

            if (title) {
                title.textContent = "Confirm Appointment Submission?";
            }

            if (message) {
                message.textContent = "Please review your information before submitting your appointment request. Once submitted, your form will be sent for approval and review by the team.";
            }

            if (confirmSubmitBtn) {
                confirmSubmitBtn.disabled = false;
                confirmSubmitBtn.textContent = "Submit Appointment";
            }

            if (cancelSubmitBtn) {
                cancelSubmitBtn.disabled = false;
            }
        }

        function openSubmitConfirmation() {
            if (isSubmittingAppointment || !submitConfirmModal) {
                return;
            }

            resetSubmitConfirmationState();

            submitConfirmModal.classList.add("open");
            submitConfirmModal.setAttribute("aria-hidden", "false");
        }

        function closeSubmitConfirmation() {
            if (!submitConfirmModal || isSubmittingAppointment) {
                return;
            }

            submitConfirmModal.classList.remove("open");
            submitConfirmModal.setAttribute("aria-hidden", "true");
        }

        function setSubmitLoadingState() {
            if (!submitConfirmModal) {
                return;
            }

            submitConfirmModal.classList.add("open");
            submitConfirmModal.setAttribute("aria-hidden", "false");

            const dialog = submitConfirmModal.querySelector(".confirm-dialog");
            const illustration = submitConfirmModal.querySelector(".confirm-illustration");
            const title = document.getElementById("submitConfirmTitle");
            const message = document.getElementById("submitConfirmMessage");

            if (dialog) {
                dialog.classList.add("is-loading");
            }

            if (illustration) {
                illustration.innerHTML = `
                    <svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="22" cy="22" r="16" stroke="currentColor" stroke-width="3" opacity="0.25"></circle>
                        <path d="M38 22C38 13.1634 30.8366 6 22 6" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                            <animateTransform attributeName="transform" type="rotate" from="0 22 22" to="360 22 22" dur="0.9s" repeatCount="indefinite"></animateTransform>
                        </path>
                    </svg>
                `;
            }

            if (title) {
                title.textContent = "Submitting Appointment...";
            }

            if (message) {
                message.textContent = "Please wait while we save your appointment request and prepare your confirmation page.";
            }

            if (confirmSubmitBtn) {
                confirmSubmitBtn.disabled = true;
                confirmSubmitBtn.textContent = "Submitting...";
            }

            if (cancelSubmitBtn) {
                cancelSubmitBtn.disabled = true;
            }
        }

        function saveReviewAsPdf() {
            if (currentStep !== 4) {
                return;
            }

            window.print();
        }

        async function submitAppointment() {
            if (isSubmittingAppointment) {
                return;
            }

            if (!validateCurrentStep()) {
                return;
            }

            isSubmittingAppointment = true;
            setSubmitLoadingState();
            updateStepUi();

            const payload = {
                user_id: userId,
                counselor_id: 0,
                counselor: "",
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

                const params = new URLSearchParams({
                    service: payload.service,
                    counselor: payload.counselor,
                    date: payload.appointment_date,
                    time: payload.appointment_time,
                    email: data.email_sent ? "sent" : "not-sent",
                    auto_pdf: "1"
                });

                clearBookingState();
                window.location.href = `/campuscare-api/php-frontend/pages/appointments/confirmation.php?${params.toString()}`;
            } catch (error) {
                resetSubmitConfirmationState();
                if (submitConfirmModal) {
                    submitConfirmModal.classList.remove("open");
                    submitConfirmModal.setAttribute("aria-hidden", "true");
                }
                CampusCareAlerts.error(error.message || "Unable to submit appointment right now.", "Submission Failed");
                isSubmittingAppointment = false;
                updateStepUi();
            }
        }

        function resizeIframe() {
            try {
                const doc = embeddedFrame.contentDocument || embeddedFrame.contentWindow.document;
                if (doc && doc.body && doc.documentElement) {
                    const shell = doc.querySelector(".page-shell");
                    const form = doc.querySelector("form");
                    const bodyHeight = doc.body.scrollHeight;
                    const docHeight = doc.documentElement.scrollHeight;
                    const shellHeight = shell ? Math.ceil(shell.getBoundingClientRect().height) : 0;
                    const formHeight = form ? Math.ceil(form.getBoundingClientRect().height) : 0;
                    const nextHeight = Math.max(shellHeight, formHeight, bodyHeight, docHeight, 420);
                    embeddedFrame.style.height = nextHeight + "px";
                }
            } catch (e) {}
        }

        embeddedFrame.addEventListener("load", resizeIframe);
        setInterval(resizeIframe, 600);

        if (savePdfBtn) {
            savePdfBtn.addEventListener("click", saveReviewAsPdf);
        }

        if (cancelSubmitBtn) {
            cancelSubmitBtn.addEventListener("click", closeSubmitConfirmation);
        }

        if (confirmSubmitBtn) {
            confirmSubmitBtn.addEventListener("click", submitAppointment);
        }

        if (submitConfirmModal) {
            submitConfirmModal.addEventListener("click", (event) => {
                if (event.target === submitConfirmModal) {
                    closeSubmitConfirmation();
                }
            });
        }

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeSubmitConfirmation();
            }
        });

        window.addEventListener("message", (event) => {
            if (event.data && event.data.type === "campuscare-form-loaded") {
                currentFormMode = event.data.isViewMode ? "view" : "edit";
                isEditMode = currentFormMode === "edit";

                if (event.data.hasSavedData) {
                    embeddedFormSaved = true;
                    embeddedFormSummary = event.data.summary || embeddedFormSummary;
                    embeddedFormPreviewUrl = event.data.previewUrl || embeddedFormPreviewUrl;
                    formSaveStatus.textContent = "Form saved";
                    formSaveStatus.className = "status-badge saved";
                    formEditToolbar.classList.add("visible");
                } else {
                    embeddedFormSaved = false;
                    formSaveStatus.textContent = "Waiting for form";
                    formSaveStatus.className = "status-badge";
                    formEditToolbar.classList.remove("visible");
                }

                if (event.data.message) {
                    embedMessage.textContent = event.data.message;
                }

                formEditStatus.textContent = event.data.isViewMode
                    ? "Form saved. Your information is currently read-only."
                    : "Edit mode is on. Update your information, then save to lock it again.";

                if (currentStep === 4) {
                    renderReview();
                }
                if (pendingRestoreStep !== null && event.data.hasSavedData) {
                    currentStep = pendingRestoreStep;
                    pendingRestoreStep = null;
                }
                resizeIframe();
                updateStepUi();
                return;
            }

            if (event.data && event.data.type === "campuscare-form-height") {
                const nextHeight = Number(event.data.height || 0);
                if (embeddedFrame && Number.isFinite(nextHeight) && nextHeight > 0) {
                    embeddedFrame.style.height = Math.max(nextHeight, 420) + "px";
                }
                return;
            }

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

            formEditToolbar.classList.add("visible");
            setEmbeddedFormMode("view", { reload: false });
            currentStep = returnStepAfterSave;
            reviewEditActive = false;
            returnStepAfterSave = 3;
            resizeIframe();
            updateStepUi();
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
                if (service) {
                    setSelectedService(service);
                }
            });
        });

        appointmentDateInput.addEventListener("change", () => {
            syncScheduleUi();
            if (slotMessage) {
                slotMessage.textContent = "";
            }
        });
        appointmentTimeInput.addEventListener("change", () => {
            renderTimeSlots();
            if (slotMessage) {
                slotMessage.textContent = "";
            }
        });
        calendarPrevBtn.addEventListener("click", () => {
            visibleCalendarMonth = new Date(visibleCalendarMonth.getFullYear(), visibleCalendarMonth.getMonth() - 1, 1);
            renderCalendar();
        });
        calendarNextBtn.addEventListener("click", () => {
            visibleCalendarMonth = new Date(visibleCalendarMonth.getFullYear(), visibleCalendarMonth.getMonth() + 1, 1);
            renderCalendar();
        });

        prevBtn.addEventListener("click", previousStep);
        reviewToggleBtn.addEventListener("click", () => {
            if (currentStep === 4) {
                reviewEditActive = true;
                returnStepAfterSave = 4;
                currentStep = 2;
                setEmbeddedFormMode("edit");
                persistBookingState();
                updateStepUi();
                return;
            }

            if (currentStep === 2 && currentFormMode === "view") {
                reviewEditActive = false;
                returnStepAfterSave = 3;
                setEmbeddedFormMode("edit");
                persistBookingState();
                updateStepUi();
                return;
            }

            if (currentStep === 2 && currentFormMode === "edit") {
                saveCurrentEmbeddedForm();
            }
        });
        nextBtn.addEventListener("click", nextStep);

        const savedBookingState = readBookingState();
        if (savedBookingState && savedBookingState.service) {
            if (appointmentDateInput && savedBookingState.appointmentDate) {
                appointmentDateInput.value = savedBookingState.appointmentDate;
            }
            if (appointmentTimeInput && savedBookingState.appointmentTime) {
                appointmentTimeInput.value = savedBookingState.appointmentTime;
            }
            pendingRestoreStep = Number(savedBookingState.step || 2);
            reviewEditActive = !!savedBookingState.reviewEditActive;
            returnStepAfterSave = pendingRestoreStep === 4 ? 4 : 3;
            setSelectedService(String(savedBookingState.service));
            if (savedBookingState.currentFormMode === "edit") {
                setEmbeddedFormMode("edit");
            }
        } else if (preselectedService) {
            setSelectedService(preselectedService);
        }

        syncScheduleUi();
        updateStepUi();
    </script>
</body>
</html>


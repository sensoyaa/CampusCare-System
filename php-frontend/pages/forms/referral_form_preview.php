<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Printable Referral Slip";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$canManageForms = campuscare_forms_can_manage($role);

$allowedRoles = ["Student", "Instructor", "Facilitator", "Administrator", "Counselor"];
if (!in_array($role, $allowedRoles, true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$id = intval($_GET["id"] ?? 0);
$record = null;

if ($id > 0 && campuscare_ensure_referral_forms_table($conn)) {
    $stmt = $conn->prepare("SELECT * FROM referral_forms WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

function referral_can_view(array $row, string $role, int $userId, bool $canManageForms): bool
{
    if ($canManageForms && $role === "Administrator") {
        return true;
    }

    if ($canManageForms && $role === "Counselor") {
        return intval($row["referred_to_counselor_id"] ?? 0) === $userId || intval($row["submitted_by_user_id"] ?? 0) === $userId;
    }

    return intval($row["submitted_by_user_id"] ?? 0) === $userId;
}

if (!is_array($record) || !referral_can_view($record, $role, $userId, $canManageForms)) {
    $record = null;
}

$reasons = [];
if (is_array($record)) {
    $decodedReasons = json_decode((string) ($record["reasons_json"] ?? "[]"), true);
    $reasons = is_array($decodedReasons) ? $decodedReasons : [];
}

function safe_value(array $row, string $key): string
{
    return trim((string) ($row[$key] ?? ""));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | CampusCare</title>
    <style>
    :root {
        --ink: #13293d;
        --muted: #4b6377;
        --line: #cbd7e3;
        --paper: #ffffff;
        --bg: #edf3f8;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 24px;
        background: var(--bg);
        color: var(--ink);
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .toolbar {
        max-width: 980px;
        margin: 0 auto 14px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn {
        border: 1px solid #2f6d9f;
        background: #2f6d9f;
        color: #fff;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-outline {
        background: #fff;
        color: #2f6d9f;
    }

    .sheet {
        max-width: 980px;
        margin: 0 auto;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 12px;
        overflow: hidden;
    }

    .sheet-head {
        padding: 16px 20px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(120deg, #e8f2fb, #f9fbff);
    }

    .sheet-head h1 {
        margin: 0;
        font-size: 24px;
    }

    .sheet-body {
        padding: 18px;
    }

    .meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .meta-item,
    .field,
    .reason-box,
    .sig-box {
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #fff;
    }

    .meta-item { padding: 10px; }

    .meta-item small,
    .field strong,
    .sig-box strong {
        display: block;
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 4px;
    }

    .section {
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 10px;
    }

    .section h2 {
        margin: 0 0 8px;
        font-size: 16px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .field {
        padding: 10px;
        min-height: 56px;
    }

    .full { grid-column: 1 / -1; }

    .reason-box {
        padding: 10px;
        min-height: 60px;
        line-height: 1.5;
    }

    .sig-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .sig-box {
        padding: 10px;
        min-height: 130px;
    }

    .sig-img {
        width: 100%;
        max-height: 110px;
        object-fit: contain;
        border: 1px dashed var(--line);
        border-radius: 8px;
    }

    .muted { color: var(--muted); }

    @media (max-width: 760px) {
        body { padding: 12px; }
        .meta,
        .grid,
        .sig-grid { grid-template-columns: 1fr; }
    }

    @media print {
        body { padding: 0; background: #fff; }
        .toolbar { display: none; }
        .sheet { max-width: 100%; border: none; border-radius: 0; }
    }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" type="button" onclick="window.print()">Print Preview / Save as PDF</button>
        <button class="btn btn-outline" type="button" onclick="window.print()">Download PDF</button>
        <a class="btn btn-outline" href="/campuscare-api/php-frontend/pages/forms/referral_form.php">Back to Referral Form</a>
    </div>

    <article class="sheet">
        <header class="sheet-head">
            <h1>Referral Slip</h1>
            <p class="muted" style="margin:6px 0 0;">Guidance and Counseling Office - Official Layout</p>
        </header>
        <div class="sheet-body">
            <?php if (!is_array($record)): ?>
                <p class="muted">No referral form found or you do not have permission to view this record.</p>
            <?php else: ?>
                <div class="meta">
                    <div class="meta-item"><small>Reference No.</small>#<?php echo intval($record["id"]); ?></div>
                    <div class="meta-item"><small>Status</small><?php echo htmlspecialchars(safe_value($record, "status")); ?></div>
                    <div class="meta-item"><small>Submitted</small><?php echo htmlspecialchars(safe_value($record, "created_at")); ?></div>
                </div>

                <section class="section">
                    <h2>Referral Information</h2>
                    <div class="grid">
                        <div class="field"><strong>To Guidance Counselor</strong><?php echo htmlspecialchars(safe_value($record, "referred_to_counselor_name")); ?></div>
                        <div class="field"><strong>Date and Time</strong><?php echo htmlspecialchars(safe_value($record, "referral_datetime")); ?></div>
                        <div class="field"><strong>Student Name</strong><?php echo htmlspecialchars(safe_value($record, "student_name")); ?></div>
                        <div class="field"><strong>Course/Year/Section</strong><?php echo htmlspecialchars(safe_value($record, "course_year_section")); ?></div>
                        <div class="field"><strong>Date Received</strong><?php echo htmlspecialchars(safe_value($record, "date_received")); ?></div>
                        <div class="field"><strong>Received By</strong><?php echo htmlspecialchars(safe_value($record, "received_by")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Reasons for Referral</h2>
                    <div class="reason-box">
                        <?php echo !empty($reasons) ? htmlspecialchars(implode(", ", $reasons)) : "-"; ?>
                    </div>
                    <div class="field full" style="margin-top:10px;"><strong>Other Reason</strong><?php echo nl2br(htmlspecialchars(safe_value($record, "other_reason"))); ?></div>
                </section>

                <section class="section">
                    <h2>Actions Taken</h2>
                    <div class="grid">
                        <div class="field full"><strong>Actions / Intervention</strong><?php echo nl2br(htmlspecialchars(safe_value($record, "actions_taken"))); ?></div>
                        <div class="field"><strong>Action Date and Time</strong><?php echo htmlspecialchars(safe_value($record, "actions_datetime")); ?></div>
                        <div class="field"><strong>Handled By</strong><?php echo htmlspecialchars(safe_value($record, "counselor_signature_typed")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Signatures</h2>
                    <div class="sig-grid">
                        <div class="sig-box">
                            <strong>Faculty/Staff Signature</strong>
                            <?php if (safe_value($record, "faculty_signature_drawn") !== ""): ?>
                                <img class="sig-img" src="<?php echo htmlspecialchars(safe_value($record, "faculty_signature_drawn")); ?>" alt="Faculty signature">
                            <?php else: ?>
                                <p class="muted">Typed: <?php echo htmlspecialchars(safe_value($record, "faculty_signature_typed")); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </article>
</body>
</html>

<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Printable Request for Testing";
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

if ($id > 0 && campuscare_ensure_testing_requests_table($conn)) {
    $stmt = $conn->prepare("SELECT * FROM testing_requests WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (is_array($record)) {
    $ownerId = intval($record["requester_user_id"] ?? 0);
    if (!$canManageForms && $ownerId !== $userId) {
        $record = null;
    }
}

function test_value(array $row, string $key): string
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
        --ink: #0f2f45;
        --muted: #5f7286;
        --line: #d1dde8;
        --paper: #ffffff;
        --bg: #eef4f9;
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

    .btn-outline { background: #fff; color: #2f6d9f; }

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
        background: linear-gradient(120deg, #e7f1fa, #f9fbff);
    }

    .sheet-head h1 {
        margin: 0;
        font-size: 24px;
    }

    .sheet-body { padding: 18px; }

    .meta,
    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .meta { margin-bottom: 12px; }

    .meta-item,
    .field,
    .sig-box {
        border: 1px solid var(--line);
        border-radius: 10px;
        background: #fff;
        padding: 10px;
    }

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

    .full { grid-column: 1 / -1; }

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
        .grid { grid-template-columns: 1fr; }
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
        <a class="btn btn-outline" href="/campuscare-api/php-frontend/pages/forms/testing_request_form.php">Back to Testing Form</a>
    </div>

    <article class="sheet">
        <header class="sheet-head">
            <h1>Request for Testing Form</h1>
            <p class="muted" style="margin:6px 0 0;">Guidance and Counseling Office - Official Layout</p>
        </header>

        <div class="sheet-body">
            <?php if (!is_array($record)): ?>
                <p class="muted">No testing request found or you do not have permission to view this record.</p>
            <?php else: ?>
                <div class="meta">
                    <div class="meta-item"><small>Reference No.</small>#<?php echo intval($record["id"]); ?></div>
                    <div class="meta-item"><small>Status</small><?php echo htmlspecialchars(test_value($record, "status")); ?></div>
                    <div class="meta-item"><small>Request Date</small><?php echo htmlspecialchars(test_value($record, "request_date")); ?></div>
                    <div class="meta-item"><small>Submitted</small><?php echo htmlspecialchars(test_value($record, "created_at")); ?></div>
                </div>

                <section class="section">
                    <h2>Applicant Section</h2>
                    <div class="grid">
                        <div class="field"><strong>Student Name</strong><?php echo htmlspecialchars(test_value($record, "target_student_name")); ?></div>
                        <div class="field"><strong>Organization/Office</strong><?php echo htmlspecialchars(test_value($record, "organization_office")); ?></div>
                        <div class="field full"><strong>Address</strong><?php echo htmlspecialchars(test_value($record, "address")); ?></div>
                        <div class="field full"><strong>Purpose</strong><?php echo nl2br(htmlspecialchars(test_value($record, "purpose"))); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Counselor/Psychometrician Section</h2>
                    <div class="grid">
                        <div class="field full"><strong>Type of Tests</strong><?php echo nl2br(htmlspecialchars(test_value($record, "counselor_type_of_tests"))); ?></div>
                        <div class="field full"><strong>Counselor Notes</strong><?php echo nl2br(htmlspecialchars(test_value($record, "counselor_notes"))); ?></div>
                        <div class="field"><strong>Reviewed By</strong><?php echo htmlspecialchars(test_value($record, "reviewed_by_name")); ?></div>
                        <div class="field"><strong>Updated</strong><?php echo htmlspecialchars(test_value($record, "updated_at")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Applicant Signature</h2>
                    <div class="sig-box">
                        <strong>Name and Signature</strong>
                        <?php if (test_value($record, "applicant_name_signature_drawn") !== ""): ?>
                            <img class="sig-img" src="<?php echo htmlspecialchars(test_value($record, "applicant_name_signature_drawn")); ?>" alt="Applicant signature">
                        <?php else: ?>
                            <p class="muted">Typed: <?php echo htmlspecialchars(test_value($record, "applicant_name_signature_typed")); ?></p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </article>
</body>
</html>

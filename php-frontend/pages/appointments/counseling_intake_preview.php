<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Printable Counseling Intake";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

if (!in_array($role, ["Student", "Administrator", "Counselor", "Facilitator", "Instructor"], true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$targetUserId = $userId;
if ($role !== "Student") {
    $targetUserId = intval($_GET["user_id"] ?? $userId);
}

$payload = [];
$formSubmittedAt = "";

$tableCheck = $conn->query("SHOW TABLES LIKE 'counseling_intake_forms'");
$tableExists = $tableCheck !== false && $tableCheck->num_rows > 0;

if ($tableExists) {
    $stmt = $conn->prepare("SELECT payload_json, created_at FROM counseling_intake_forms WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $decoded = json_decode((string) ($row["payload_json"] ?? ""), true);
            $payload = is_array($decoded) ? $decoded : [];
            $submittedRaw = trim((string) ($row["created_at"] ?? ""));
            $formSubmittedAt = $submittedRaw !== "" ? date("F j, Y g:i A", strtotime($submittedRaw)) : "";
        }
    }
}

function preview_value(array $payload, string $key): string
{
    return trim((string) ($payload[$key] ?? ""));
}

function preview_yes_no(array $payload, string $key): string
{
    $value = preview_value($payload, $key);
    return $value !== "" ? $value : "-";
}

$medicalHistory = $payload["medical_history"] ?? [];
if (!is_array($medicalHistory)) {
    $medicalHistory = [];
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

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 24px;
        background: var(--bg);
        color: var(--ink);
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .print-toolbar {
        max-width: 980px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn {
        border: 1px solid #2f6d9f;
        background: #2f6d9f;
        color: #fff;
        border-radius: 8px;
        padding: 8px 12px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-outline {
        border: 1px solid #2f6d9f;
        background: #fff;
        color: #2f6d9f;
    }

    .sheet {
        max-width: 980px;
        margin: 0 auto;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 14px;
        overflow: hidden;
    }

    .sheet-head {
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(130deg, #e7f1fa, #f9fbff);
    }

    .sheet-head h1 {
        margin: 0;
        font-size: 26px;
    }

    .sheet-head p {
        margin: 6px 0 0;
        color: var(--muted);
    }

    .sheet-body {
        padding: 20px;
    }

    .meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    .meta-item {
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 10px;
    }

    .meta-item small {
        display: block;
        color: var(--muted);
        margin-bottom: 4px;
    }

    .section {
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
    }

    .section h2 {
        margin: 0 0 10px;
        font-size: 17px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .field {
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 8px;
        min-height: 56px;
    }

    .field strong {
        display: block;
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 3px;
    }

    .full {
        grid-column: 1 / -1;
    }

    .sig-wrap {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .sig-box {
        border: 1px solid var(--line);
        border-radius: 10px;
        padding: 10px;
        min-height: 120px;
    }

    .sig-img {
        width: 100%;
        max-height: 110px;
        object-fit: contain;
        border: 1px dashed var(--line);
        border-radius: 8px;
        background: #fff;
    }

    .muted {
        color: var(--muted);
    }

    @media (max-width: 760px) {
        body {
            padding: 12px;
        }

        .meta,
        .grid,
        .sig-wrap {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        body {
            background: #fff;
            padding: 0;
        }

        .print-toolbar {
            display: none;
        }

        .sheet {
            border: none;
            border-radius: 0;
            max-width: 100%;
        }
    }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button class="btn" type="button" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn btn-outline" href="/campuscare-api/php-frontend/pages/appointments/counseling_intake_form.php">Back to Intake Form</a>
    </div>

    <article class="sheet">
        <header class="sheet-head">
            <h1>Counseling Intake Form</h1>
            <p>Student Welfare and Engagement Unit - Guidance and Counseling Services</p>
        </header>

        <div class="sheet-body">
            <?php if (empty($payload)): ?>
                <p class="muted">No submitted intake form found for this student yet.</p>
            <?php else: ?>
                <div class="meta">
                    <div class="meta-item">
                        <small>Client Name</small>
                        <?php echo htmlspecialchars(trim(preview_value($payload, "client_first_name") . " " . preview_value($payload, "client_last_name"))); ?>
                    </div>
                    <div class="meta-item">
                        <small>Course and Year</small>
                        <?php echo htmlspecialchars(preview_value($payload, "course_year")); ?>
                    </div>
                    <div class="meta-item">
                        <small>Submitted At</small>
                        <?php echo htmlspecialchars($formSubmittedAt !== "" ? $formSubmittedAt : "-"); ?>
                    </div>
                </div>

                <section class="section">
                    <h2>Client Information</h2>
                    <div class="grid">
                        <div class="field"><strong>Email</strong><?php echo htmlspecialchars(preview_value($payload, "email")); ?></div>
                        <div class="field"><strong>Cell Phone</strong><?php echo htmlspecialchars(preview_value($payload, "cell_phone")); ?></div>
                        <div class="field"><strong>Date of Birth</strong><?php echo htmlspecialchars(preview_value($payload, "date_of_birth")); ?></div>
                        <div class="field"><strong>Marital Status</strong><?php echo htmlspecialchars(preview_value($payload, "marital_status")); ?></div>
                        <div class="field"><strong>Intake Type</strong><?php echo htmlspecialchars(preview_value($payload, "intake_mode")); ?></div>
                        <div class="field"><strong>Referred By</strong><?php echo htmlspecialchars(preview_value($payload, "referred_by")); ?></div>
                        <div class="field full"><strong>Address</strong><?php echo htmlspecialchars(trim(preview_value($payload, "address_street") . ", " . preview_value($payload, "address_city") . ", " . preview_value($payload, "address_state") . " " . preview_value($payload, "address_postal"), " ,")); ?></div>
                        <div class="field"><strong>Religious Affiliation</strong><?php echo htmlspecialchars(preview_value($payload, "religious_affiliation")); ?></div>
                        <div class="field"><strong>Facebook/Messenger Username</strong><?php echo htmlspecialchars(preview_value($payload, "messenger_username")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Emergency Contact</h2>
                    <div class="grid">
                        <div class="field"><strong>Name</strong><?php echo htmlspecialchars(preview_value($payload, "emergency_contact_name")); ?></div>
                        <div class="field"><strong>Relationship</strong><?php echo htmlspecialchars(preview_value($payload, "emergency_contact_relationship")); ?></div>
                        <div class="field"><strong>Contact Number</strong><?php echo htmlspecialchars(preview_value($payload, "emergency_contact_number")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Medical and Lifestyle</h2>
                    <div class="grid">
                        <div class="field full"><strong>Medical History</strong><?php echo htmlspecialchars(!empty($medicalHistory) ? implode(", ", $medicalHistory) : "-"); ?></div>
                        <div class="field full"><strong>Other Medical Details</strong><?php echo htmlspecialchars(preview_value($payload, "medical_history_other")); ?></div>
                        <div class="field full"><strong>Family Medical History</strong><?php echo nl2br(htmlspecialchars(preview_value($payload, "family_medical_history"))); ?></div>
                        <div class="field"><strong>Tobacco</strong><?php echo htmlspecialchars(preview_yes_no($payload, "tobacco_use")); ?></div>
                        <div class="field"><strong>Alcohol</strong><?php echo htmlspecialchars(preview_yes_no($payload, "alcohol_use")); ?></div>
                        <div class="field"><strong>Caffeine</strong><?php echo htmlspecialchars(preview_yes_no($payload, "caffeine_use")); ?></div>
                        <div class="field"><strong>Drugs</strong><?php echo htmlspecialchars(preview_yes_no($payload, "drug_use")); ?></div>
                        <div class="field"><strong>Taking Prescription Medication</strong><?php echo htmlspecialchars(preview_yes_no($payload, "takes_prescription_medication")); ?></div>
                        <div class="field"><strong>Prescription Details</strong><?php echo htmlspecialchars(preview_value($payload, "prescription_details")); ?></div>
                        <div class="field"><strong>Surgeries in Past 5 Years</strong><?php echo htmlspecialchars(preview_yes_no($payload, "surgeries_past_5_years")); ?></div>
                        <div class="field"><strong>Surgery Details</strong><?php echo htmlspecialchars(preview_value($payload, "surgeries_details")); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Mental Health Information</h2>
                    <div class="grid">
                        <div class="field full"><strong>Reason for Initial Visit</strong><?php echo nl2br(htmlspecialchars(preview_value($payload, "initial_visit_reason"))); ?></div>
                        <div class="field full"><strong>Expected Outcome</strong><?php echo nl2br(htmlspecialchars(preview_value($payload, "session_expectation"))); ?></div>
                        <div class="field"><strong>Average Sleep Hours</strong><?php echo htmlspecialchars(preview_value($payload, "average_sleep_hours")); ?></div>
                        <div class="field"><strong>Seen Mental Health Professional Before</strong><?php echo htmlspecialchars(preview_yes_no($payload, "seen_mental_health_professional")); ?></div>
                        <div class="field full"><strong>If Yes, Reason</strong><?php echo htmlspecialchars(preview_value($payload, "seen_professional_reason")); ?></div>
                        <div class="field full"><strong>Additional Comments</strong><?php echo nl2br(htmlspecialchars(preview_value($payload, "additional_comments"))); ?></div>
                    </div>
                </section>

                <section class="section">
                    <h2>Agreement and Signatures</h2>
                    <div class="grid" style="margin-bottom: 10px;">
                        <div class="field"><strong>Agreement Accepted</strong><?php echo htmlspecialchars(preview_yes_no($payload, "agreement_accepted")); ?></div>
                        <div class="field"><strong>Client Date</strong><?php echo htmlspecialchars(preview_value($payload, "agreement_client_date")); ?></div>
                        <div class="field"><strong>Counselor Date</strong><?php echo htmlspecialchars(preview_value($payload, "agreement_counselor_date")); ?></div>
                    </div>

                    <div class="sig-wrap">
                        <div class="sig-box">
                            <strong>Client Signature</strong>
                            <?php if (preview_value($payload, "agreement_signature_client_drawn") !== ""): ?>
                                <img class="sig-img" src="<?php echo htmlspecialchars(preview_value($payload, "agreement_signature_client_drawn")); ?>" alt="Client signature">
                            <?php else: ?>
                                <p class="muted">Typed: <?php echo htmlspecialchars(preview_value($payload, "agreement_signature_client")); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="sig-box">
                            <strong>Counselor Signature</strong>
                            <?php if (preview_value($payload, "agreement_signature_counselor_drawn") !== ""): ?>
                                <img class="sig-img" src="<?php echo htmlspecialchars(preview_value($payload, "agreement_signature_counselor_drawn")); ?>" alt="Counselor signature">
                            <?php else: ?>
                                <p class="muted">Typed: <?php echo htmlspecialchars(preview_value($payload, "agreement_signature_counselor")); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </article>
</body>
</html>

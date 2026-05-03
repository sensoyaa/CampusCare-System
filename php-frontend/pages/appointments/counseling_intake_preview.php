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

function preview_display(array $payload, string $key): string
{
    $value = preview_value($payload, $key);
    return $value !== "" ? $value : "-";
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

$addressParts = array_filter([
    preview_value($payload, "address_street"),
    preview_value($payload, "address_city"),
    preview_value($payload, "address_state"),
    preview_value($payload, "address_postal"),
], static fn ($value) => trim((string) $value) !== "");

$addressLabel = !empty($addressParts) ? implode(", ", $addressParts) : "-";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | CampusCare</title>
    <style>
    :root {
        --ink: #1c2f43;
        --muted: #63778c;
        --line: #d6e0e8;
        --paper: #ffffff;
        --bg: #edf4fa;
        --soft: #f8fbfe;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 24px;
        background: var(--bg);
        color: var(--ink);
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .print-toolbar {
        max-width: 1120px;
        margin: 0 auto 16px;
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
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-outline {
        background: #fff;
        color: #2f6d9f;
    }

    .sheet {
        max-width: 1120px;
        margin: 0 auto;
        background: var(--paper);
        border: 1px solid var(--line);
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 18px 48px rgba(20, 45, 68, 0.08);
    }

    .sheet-body {
        padding: 22px 24px 26px;
    }

    .official-head {
        display: grid;
        grid-template-columns: 94px minmax(0, 1fr);
        gap: 14px;
        align-items: start;
        padding-bottom: 14px;
        border-bottom: 2px solid #1f2f3c;
    }

    .official-head img {
        width: 82px;
        height: 82px;
        object-fit: contain;
    }

    .official-head-copy {
        text-align: center;
        color: #223647;
        font-family: Georgia, "Times New Roman", serif;
    }

    .official-head-copy h1,
    .official-head-copy h2,
    .official-head-copy h3,
    .official-head-copy p {
        margin: 0;
    }

    .official-head-copy h1 {
        font-size: 17px;
        letter-spacing: 0.6px;
    }

    .official-head-copy p {
        font-size: 12px;
        line-height: 1.35;
    }

    .official-head-copy h2 {
        margin-top: 8px;
        font-size: 14px;
        letter-spacing: 0.6px;
    }

    .official-head-copy .script-line {
        font-size: 12px;
        font-style: italic;
        letter-spacing: 1.4px;
    }

    .official-head-copy h3 {
        margin-top: 8px;
        font-size: 15px;
    }

    .preview-shell {
        margin-top: 16px;
        border: 1px dashed #cfe0ee;
        border-radius: 20px;
        padding: 16px;
        background: linear-gradient(180deg, #fcfeff 0%, #f7fbff 100%);
    }

    .panel {
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fff;
        padding: 14px 16px;
    }

    .panel {
        margin-bottom: 12px;
    }

    .panel h2 {
        margin: 0 0 10px;
        text-align: center;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #6d839a;
    }

    .field-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px 16px;
    }

    .field-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px 16px;
    }

    .summary-appointment {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px 16px;
    }

    .summary-appointment-card {
        min-height: 34px;
        text-align: center;
    }

    .summary-appointment-card strong {
        display: block;
        font-size: 10px;
        color: #748aa1;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }

    .summary-appointment-card div {
        font-size: 12px;
        line-height: 1.45;
        color: #415a73;
        word-break: break-word;
    }

    .field {
        min-height: 34px;
        text-align: center;
    }

    .field strong {
        display: block;
        font-size: 10px;
        color: #748aa1;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }

    .field div {
        font-size: 12px;
        line-height: 1.45;
        color: #415a73;
        word-break: break-word;
    }

    .full {
        grid-column: 1 / -1;
    }

    .agreement-copy {
        font-family: Georgia, "Times New Roman", serif;
        color: #283848;
        font-size: 12px;
        line-height: 1.55;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 14px 16px;
        background: #fcfdff;
    }

    .agreement-copy p {
        margin: 0 0 10px;
    }

    .agreement-copy p:last-child {
        margin-bottom: 0;
    }

    .agreement-copy strong {
        font-size: 13px;
    }

    .signature-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 12px;
    }

    .signature-box {
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 12px;
        min-height: 132px;
    }

    .signature-box strong {
        display: block;
        margin-bottom: 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: #6e8298;
    }

    .signature-box .sig-img {
        width: 100%;
        max-height: 96px;
        object-fit: contain;
        border: 1px dashed var(--line);
        border-radius: 8px;
        background: #fff;
    }

    .signature-box p {
        margin: 0;
        font-size: 12px;
        color: #445d76;
    }

    .muted {
        color: var(--muted);
    }

    .footer-line {
        margin-top: 16px;
        padding-top: 8px;
        border-top: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        color: #6f8296;
        font-size: 11px;
    }

    @media (max-width: 760px) {
        body { padding: 12px; }
        .official-head,
        .summary-appointment,
        .field-grid,
        .field-grid-3,
        .signature-grid,
        .footer-line {
            grid-template-columns: 1fr;
            display: grid;
        }
        .official-head-copy {
            text-align: left;
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
            box-shadow: none;
        }

        .sheet-body {
            padding: 14mm 12mm 12mm;
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
        <div class="sheet-body">
            <?php if (empty($payload)): ?>
                <p class="muted">No submitted intake form found for this student yet.</p>
            <?php else: ?>
                <header class="official-head">
                    <img src="/campuscare-api/php-frontend/assets/images/buksulogo.png" alt="BukSU Logo">
                    <div class="official-head-copy">
                        <h1>BUKIDNON STATE UNIVERSITY</h1>
                        <p>Fortich Street, Malaybalay City, Bukidnon 8700</p>
                        <p>Tel (088) 813-5661 to 5663; Telefax (088) 813-2717, www.buksu.edu.ph</p>
                        <h2>STUDENT WELFARE AND ENGAGEMENT UNIT</h2>
                        <p class="script-line">GUIDANCE AND COUNSELING SERVICES</p>
                        <h3>COUNSELING INTAKE FORM</h3>
                    </div>
                </header>

                <div class="preview-shell">
                    <section class="panel">
                        <h2>Appointment Details</h2>
                        <div class="summary-appointment">
                            <div class="summary-appointment-card">
                                <strong>Client Name</strong>
                                <div><?php echo htmlspecialchars(trim(preview_value($payload, "client_first_name") . " " . preview_value($payload, "client_last_name"))); ?></div>
                            </div>
                            <div class="summary-appointment-card">
                                <strong>Course and Year</strong>
                                <div><?php echo htmlspecialchars(preview_value($payload, "course_year")); ?></div>
                            </div>
                            <div class="summary-appointment-card">
                                <strong>Submitted</strong>
                                <div><?php echo htmlspecialchars($formSubmittedAt !== "" ? $formSubmittedAt : "-"); ?></div>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <h2>Client Information</h2>
                        <div class="field-grid">
                            <div class="field"><strong>Client Name</strong><div><?php echo htmlspecialchars(trim(preview_value($payload, "client_first_name") . " " . preview_value($payload, "client_last_name"))); ?></div></div>
                            <div class="field"><strong>Course and Year</strong><div><?php echo htmlspecialchars(preview_value($payload, "course_year")); ?></div></div>
                            <div class="field"><strong>Intake Type</strong><div><?php echo htmlspecialchars(preview_value($payload, "intake_mode")); ?></div></div>
                            <div class="field"><strong>Email</strong><div><?php echo htmlspecialchars(preview_value($payload, "email")); ?></div></div>
                            <div class="field"><strong>Cell Phone</strong><div><?php echo htmlspecialchars(preview_value($payload, "cell_phone")); ?></div></div>
                            <div class="field"><strong>Date of Birth</strong><div><?php echo htmlspecialchars(preview_value($payload, "date_of_birth")); ?></div></div>
                            <div class="field full"><strong>Address</strong><div><?php echo htmlspecialchars($addressLabel); ?></div></div>
                        </div>
                    </section>

                    <section class="panel">
                        <h2>Emergency and Medical</h2>
                        <div class="field-grid">
                            <div class="field"><strong>Emergency Contact</strong><div><?php echo htmlspecialchars(preview_display($payload, "emergency_contact_name")); ?></div></div>
                            <div class="field"><strong>Relationship</strong><div><?php echo htmlspecialchars(preview_display($payload, "emergency_contact_relationship")); ?></div></div>
                            <div class="field full"><strong>Contact Number</strong><div><?php echo htmlspecialchars(preview_display($payload, "emergency_contact_number")); ?></div></div>
                            <div class="field full"><strong>Medical History</strong><div><?php echo htmlspecialchars(!empty($medicalHistory) ? implode(", ", $medicalHistory) : "-"); ?></div></div>
                            <div class="field full"><strong>Other Medical Details</strong><div><?php echo nl2br(htmlspecialchars(preview_display($payload, "medical_history_other"))); ?></div></div>
                            <div class="field full"><strong>Family Medical History</strong><div><?php echo nl2br(htmlspecialchars(preview_display($payload, "family_medical_history"))); ?></div></div>
                            <div class="field"><strong>Tobacco Use</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "tobacco_use")); ?></div></div>
                            <div class="field"><strong>Alcohol Use</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "alcohol_use")); ?></div></div>
                            <div class="field"><strong>Caffeine Use</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "caffeine_use")); ?></div></div>
                            <div class="field"><strong>Drug Use</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "drug_use")); ?></div></div>
                            <div class="field"><strong>Prescription Medication</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "takes_prescription_medication")); ?></div></div>
                            <div class="field full"><strong>Prescription Details</strong><div><?php echo nl2br(htmlspecialchars(preview_display($payload, "prescription_details"))); ?></div></div>
                            <div class="field"><strong>Surgeries in Past 5 Years</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "surgeries_past_5_years")); ?></div></div>
                            <div class="field full"><strong>Surgery Details</strong><div><?php echo nl2br(htmlspecialchars(preview_display($payload, "surgeries_details"))); ?></div></div>
                        </div>
                    </section>

                    <section class="panel">
                        <h2>Mental Health Information</h2>
                        <div class="field-grid">
                            <div class="field full"><strong>Reason for Visit</strong><div><?php echo nl2br(htmlspecialchars(preview_value($payload, "initial_visit_reason"))); ?></div></div>
                            <div class="field full"><strong>Session Expectation</strong><div><?php echo nl2br(htmlspecialchars(preview_value($payload, "session_expectation"))); ?></div></div>
                            <div class="field"><strong>Average Sleep Hours</strong><div><?php echo htmlspecialchars(preview_value($payload, "average_sleep_hours")); ?></div></div>
                            <div class="field"><strong>Seen a Mental Health Professional Before</strong><div><?php echo htmlspecialchars(preview_yes_no($payload, "seen_mental_health_professional")); ?></div></div>
                        </div>
                    </section>

                    <section class="panel">
                        <h2>Counseling Confidentiality Agreement</h2>
                        <div class="agreement-copy">
                            <p><strong>Purpose</strong></p>
                            <p>This confidentiality agreement is intended to protect the privacy of the client and promote a secure counseling environment that supports trust, openness, and effective processing.</p>
                            <p><strong>Extent of Confidentiality</strong></p>
                            <p>All communication between the client and the counselor, whether verbal or written, will be treated with confidentiality, including counseling sessions, notes, reports, and related records.</p>
                            <p><strong>Limits of Confidentiality</strong></p>
                            <p>Disclosure may happen when there is risk of harm, when the client is a minor and lawful guardians have access rights, when abuse is reasonably suspected, or when disclosure is required by a lawful court order.</p>
                        </div>

                        <div class="signature-grid">
                            <div class="signature-box">
                                <strong>Client Signature</strong>
                                <?php if (preview_value($payload, "agreement_signature_client_drawn") !== ""): ?>
                                    <img class="sig-img" src="<?php echo htmlspecialchars(preview_value($payload, "agreement_signature_client_drawn")); ?>" alt="Client signature">
                                <?php else: ?>
                                    <p><?php echo htmlspecialchars(preview_value($payload, "agreement_signature_client")); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="signature-box">
                                <strong>Client Date</strong>
                                <p><?php echo htmlspecialchars(preview_value($payload, "agreement_client_date")); ?></p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="footer-line">
                    <span>Document Code: GCS-F-016</span>
                    <span>Revision No: 0</span>
                    <span>Issue Date: February 18, 2025</span>
                </div>
            <?php endif; ?>
        </div>
    </article>
</body>
</html>

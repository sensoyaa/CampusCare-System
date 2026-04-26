<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Counseling Intake Reviews";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));

if (!in_array($role, ["Administrator", "Counselor"], true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$selectedStudentId = intval($_GET["user_id"] ?? 0);

function review_value(array $payload, string $key): string
{
    return trim((string) ($payload[$key] ?? ""));
}

function review_yes_no(array $payload, string $key): string
{
    $value = review_value($payload, $key);
    return $value !== "" ? $value : "-";
}

function review_signature_html(array $payload, string $typedKey, string $drawnKey): string
{
    $drawn = review_value($payload, $drawnKey);
    $typed = review_value($payload, $typedKey);

    if ($drawn !== "") {
        return '<img class="sig-img" src="' . htmlspecialchars($drawn) . '" alt="Signature">';
    }

    return '<p class="muted">Typed: ' . htmlspecialchars($typed !== "" ? $typed : "-") . '</p>';
}

$tableExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'counseling_intake_forms'");
if ($tableCheck !== false && $tableCheck->num_rows > 0) {
    $tableExists = true;
}

$students = [];
if ($tableExists) {
    $sql = "SELECT u.id, u.full_name, u.student_id, latest.created_at AS latest_submitted_at, latest.payload_json AS latest_payload_json
            FROM users u
            LEFT JOIN (
                SELECT c1.user_id, c1.payload_json, c1.created_at
                FROM counseling_intake_forms c1
                INNER JOIN (
                    SELECT user_id, MAX(created_at) AS max_created_at
                    FROM counseling_intake_forms
                    GROUP BY user_id
                ) latest ON latest.user_id = c1.user_id AND latest.max_created_at = c1.created_at
            ) latest ON latest.user_id = u.id
            WHERE u.role = 'Student' AND u.status = 'Active'
            ORDER BY u.full_name ASC";

    $result = $conn->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $payload = [];
        $payloadJson = trim((string) ($row["latest_payload_json"] ?? ""));
        if ($payloadJson !== "") {
            $decoded = json_decode($payloadJson, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $row["payload"] = $payload;
        $students[] = $row;
    }
}

if ($selectedStudentId <= 0) {
    foreach ($students as $studentRow) {
        if (trim((string) ($studentRow["latest_submitted_at"] ?? "")) !== "") {
            $selectedStudentId = intval($studentRow["id"]);
            break;
        }
    }

    if ($selectedStudentId <= 0 && !empty($students)) {
        $selectedStudentId = intval($students[0]["id"]);
    }
}

$selectedStudent = null;
foreach ($students as $studentRow) {
    if (intval($studentRow["id"]) === $selectedStudentId) {
        $selectedStudent = $studentRow;
        break;
    }
}

$selectedPayload = is_array($selectedStudent) ? ($selectedStudent["payload"] ?? []) : [];
if (!is_array($selectedPayload)) {
    $selectedPayload = [];
}

$selectedSubmittedAt = trim((string) ($selectedStudent["latest_submitted_at"] ?? ""));
$selectedSubmittedAtDisplay = $selectedSubmittedAt !== "" ? date("F j, Y g:i A", strtotime($selectedSubmittedAt)) : "";
$medicalHistory = $selectedPayload["medical_history"] ?? [];
if (!is_array($medicalHistory)) {
    $medicalHistory = [];
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
        <div class="page-shell" style="max-width:1200px;">
            <h1 class="page-title">Counseling Intake Reviews</h1>
            <p class="page-subtitle">View the latest submitted intake form for each student before sessions.</p>

            <?php if (!$tableExists): ?>
                <div class="alert alert-error">Counseling intake storage is not available yet.</div>
            <?php else: ?>
                <div class="card" style="padding:14px; margin-bottom:12px;">
                    <form method="GET" class="form-group" style="margin:0; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
                        <div class="form-group" style="margin:0; min-width:320px; flex:1;">
                            <label for="user_id">Select Student</label>
                            <select id="user_id" name="user_id">
                                <?php foreach ($students as $studentRow): ?>
                                    <option value="<?php echo intval($studentRow["id"]); ?>" <?php echo intval($studentRow["id"]) === $selectedStudentId ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars((string) ($studentRow["full_name"] ?? "")); ?><?php echo trim((string) ($studentRow["student_id"] ?? "")) !== "" ? " (" . htmlspecialchars((string) $studentRow["student_id"]) . ")" : ""; ?>
                                        <?php echo trim((string) ($studentRow["latest_submitted_at"] ?? "")) !== "" ? " - submitted" : " - no intake yet"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">View Intake</button>
                        <?php if ($selectedStudentId > 0): ?>
                            <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/appointments/counseling_intake_preview.php?user_id=<?php echo intval($selectedStudentId); ?>">Open Printable Preview</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="padding:14px; margin-bottom:12px; overflow:auto;">
                    <table class="table" style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Latest Submission</th>
                                <th>Course/Year</th>
                                <th>Intake Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:14px; color:var(--text-muted);">No active students found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $studentRow): ?>
                                    <?php
                                        $studentPayload = $studentRow["payload"] ?? [];
                                        if (!is_array($studentPayload)) {
                                            $studentPayload = [];
                                        }
                                        $submittedRaw = trim((string) ($studentRow["latest_submitted_at"] ?? ""));
                                        $courseYear = review_value($studentPayload, "course_year");
                                        $intakeMode = review_value($studentPayload, "intake_mode");
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) ($studentRow["full_name"] ?? "")); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($studentRow["student_id"] ?? "")); ?></td>
                                        <td><?php echo htmlspecialchars($submittedRaw !== "" ? date("M j, Y", strtotime($submittedRaw)) : "No intake yet"); ?></td>
                                        <td><?php echo htmlspecialchars($courseYear !== "" ? $courseYear : "-"); ?></td>
                                        <td><?php echo htmlspecialchars($intakeMode !== "" ? $intakeMode : "-"); ?></td>
                                        <td>
                                            <a class="btn-outline" href="?user_id=<?php echo intval($studentRow["id"]); ?>">View</a>
                                            <a class="btn-outline" target="_blank" rel="noopener" href="/campuscare-api/php-frontend/pages/appointments/counseling_intake_preview.php?user_id=<?php echo intval($studentRow["id"]); ?>">Preview</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (is_array($selectedStudent)): ?>
                    <div class="card" style="padding:18px;">
                        <style>
                        .review-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
                        .review-section { border:1px solid var(--border); border-radius:12px; padding:14px; background:var(--card-bg); margin-bottom:12px; }
                        .review-field { border:1px solid var(--border); border-radius:8px; padding:10px; min-height:56px; }
                        .review-field strong { display:block; font-size:12px; color:var(--text-muted); margin-bottom:3px; }
                        .sig-wrap { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
                        .sig-box { border:1px solid var(--border); border-radius:10px; padding:10px; min-height:120px; }
                        .sig-img { width:100%; max-height:110px; object-fit:contain; border:1px dashed var(--border); border-radius:8px; background:#fff; }
                        .muted { color:var(--text-muted); }
                        @media (max-width: 760px) { .review-grid, .sig-wrap { grid-template-columns: 1fr; } }
                        </style>

                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:start; margin-bottom:12px;">
                            <div>
                                <h2 class="card-title" style="margin-bottom:4px;"><?php echo htmlspecialchars((string) ($selectedStudent["full_name"] ?? "")); ?></h2>
                                <p class="page-subtitle" style="margin:0;">Student ID: <?php echo htmlspecialchars((string) ($selectedStudent["student_id"] ?? "-")); ?></p>
                            </div>
                            <div>
                                <p class="page-subtitle" style="margin:0; text-align:right;">Last Submitted: <strong><?php echo htmlspecialchars($selectedSubmittedAtDisplay !== "" ? $selectedSubmittedAtDisplay : "No intake yet"); ?></strong></p>
                            </div>
                        </div>

                        <?php if (empty($selectedPayload)): ?>
                            <p class="muted">This student does not have a submitted counseling intake form yet.</p>
                        <?php else: ?>
                            <section class="review-section">
                                <h3 class="card-title" style="margin-bottom:10px;">Client Information</h3>
                                <div class="review-grid">
                                    <div class="review-field"><strong>Full Name</strong><?php echo htmlspecialchars(trim(review_value($selectedPayload, "client_first_name") . " " . review_value($selectedPayload, "client_last_name"))); ?></div>
                                    <div class="review-field"><strong>Course and Year</strong><?php echo htmlspecialchars(review_value($selectedPayload, "course_year")); ?></div>
                                    <div class="review-field"><strong>Email</strong><?php echo htmlspecialchars(review_value($selectedPayload, "email")); ?></div>
                                    <div class="review-field"><strong>Cell Phone</strong><?php echo htmlspecialchars(review_value($selectedPayload, "cell_phone")); ?></div>
                                    <div class="review-field"><strong>Intake Type</strong><?php echo htmlspecialchars(review_value($selectedPayload, "intake_mode")); ?></div>
                                    <div class="review-field"><strong>Referred By</strong><?php echo htmlspecialchars(review_value($selectedPayload, "referred_by")); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Address</strong><?php echo htmlspecialchars(trim(review_value($selectedPayload, "address_street") . ", " . review_value($selectedPayload, "address_city") . ", " . review_value($selectedPayload, "address_state") . " " . review_value($selectedPayload, "address_postal"), " ,")); ?></div>
                                    <div class="review-field"><strong>Religious Affiliation</strong><?php echo htmlspecialchars(review_value($selectedPayload, "religious_affiliation")); ?></div>
                                    <div class="review-field"><strong>Messenger Username</strong><?php echo htmlspecialchars(review_value($selectedPayload, "messenger_username")); ?></div>
                                </div>
                            </section>

                            <section class="review-section">
                                <h3 class="card-title" style="margin-bottom:10px;">Emergency Contact</h3>
                                <div class="review-grid">
                                    <div class="review-field"><strong>Name</strong><?php echo htmlspecialchars(review_value($selectedPayload, "emergency_contact_name")); ?></div>
                                    <div class="review-field"><strong>Relationship</strong><?php echo htmlspecialchars(review_value($selectedPayload, "emergency_contact_relationship")); ?></div>
                                    <div class="review-field"><strong>Contact Number</strong><?php echo htmlspecialchars(review_value($selectedPayload, "emergency_contact_number")); ?></div>
                                </div>
                            </section>

                            <section class="review-section">
                                <h3 class="card-title" style="margin-bottom:10px;">Medical and Lifestyle</h3>
                                <div class="review-grid">
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Medical History</strong><?php echo htmlspecialchars(!empty($medicalHistory) ? implode(", ", $medicalHistory) : "-"); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Other Medical Details</strong><?php echo htmlspecialchars(review_value($selectedPayload, "medical_history_other")); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Family Medical History</strong><?php echo nl2br(htmlspecialchars(review_value($selectedPayload, "family_medical_history"))); ?></div>
                                    <div class="review-field"><strong>Tobacco</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "tobacco_use")); ?></div>
                                    <div class="review-field"><strong>Alcohol</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "alcohol_use")); ?></div>
                                    <div class="review-field"><strong>Caffeine</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "caffeine_use")); ?></div>
                                    <div class="review-field"><strong>Drugs</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "drug_use")); ?></div>
                                    <div class="review-field"><strong>Prescription Medication</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "takes_prescription_medication")); ?></div>
                                    <div class="review-field"><strong>Prescription Details</strong><?php echo htmlspecialchars(review_value($selectedPayload, "prescription_details")); ?></div>
                                    <div class="review-field"><strong>Surgeries in Past 5 Years</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "surgeries_past_5_years")); ?></div>
                                    <div class="review-field"><strong>Surgery Details</strong><?php echo htmlspecialchars(review_value($selectedPayload, "surgeries_details")); ?></div>
                                </div>
                            </section>

                            <section class="review-section">
                                <h3 class="card-title" style="margin-bottom:10px;">Mental Health Information</h3>
                                <div class="review-grid">
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Initial Visit Reason</strong><?php echo nl2br(htmlspecialchars(review_value($selectedPayload, "initial_visit_reason"))); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Session Expectation</strong><?php echo nl2br(htmlspecialchars(review_value($selectedPayload, "session_expectation"))); ?></div>
                                    <div class="review-field"><strong>Average Sleep Hours</strong><?php echo htmlspecialchars(review_value($selectedPayload, "average_sleep_hours")); ?></div>
                                    <div class="review-field"><strong>Seen Mental Health Professional</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "seen_mental_health_professional")); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>If Yes, Reason</strong><?php echo htmlspecialchars(review_value($selectedPayload, "seen_professional_reason")); ?></div>
                                    <div class="review-field" style="grid-column:1 / -1;"><strong>Additional Comments</strong><?php echo nl2br(htmlspecialchars(review_value($selectedPayload, "additional_comments"))); ?></div>
                                </div>
                            </section>

                            <section class="review-section">
                                <h3 class="card-title" style="margin-bottom:10px;">Agreement and Signatures</h3>
                                <div class="review-grid" style="margin-bottom:12px;">
                                    <div class="review-field"><strong>Agreement Accepted</strong><?php echo htmlspecialchars(review_yes_no($selectedPayload, "agreement_accepted")); ?></div>
                                    <div class="review-field"><strong>Client Date</strong><?php echo htmlspecialchars(review_value($selectedPayload, "agreement_client_date")); ?></div>
                                    <div class="review-field"><strong>Counselor Date</strong><?php echo htmlspecialchars(review_value($selectedPayload, "agreement_counselor_date")); ?></div>
                                </div>

                                <div class="sig-wrap">
                                    <div class="sig-box">
                                        <strong>Client Signature</strong>
                                        <?php echo review_signature_html($selectedPayload, "agreement_signature_client", "agreement_signature_client_drawn"); ?>
                                    </div>
                                    <div class="sig-box">
                                        <strong>Counselor Signature</strong>
                                        <?php echo review_signature_html($selectedPayload, "agreement_signature_counselor", "agreement_signature_counselor_drawn"); ?>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    const profileMenuToggle = document.querySelector(".profile-menu-toggle");
    const profileDropdown = document.querySelector(".profile-dropdown");

    if (!profileMenuToggle || !profileDropdown) {
        return;
    }

    profileMenuToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        const parent = profileMenuToggle.closest(".topbar-user");
        const isOpen = parent.classList.toggle("is-open");
        profileMenuToggle.setAttribute("aria-expanded", isOpen);
    });

    document.addEventListener("click", function () {
        const parent = profileMenuToggle.closest(".topbar-user");
        if (parent) {
            parent.classList.remove("is-open");
        }
        profileMenuToggle.setAttribute("aria-expanded", "false");
    });

    profileDropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });
})();
</script>
</body>
</html>

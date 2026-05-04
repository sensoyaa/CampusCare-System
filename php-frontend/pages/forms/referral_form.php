<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Referral Slip";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));
$canManageForms = campuscare_forms_can_manage($role);
$isIframe = isset($_GET["iframe"]) && $_GET["iframe"] === "1";
$requestedMode = strtolower(trim((string) ($_GET["mode"] ?? "")));

$allowedRoles = ["Student", "Instructor", "Facilitator", "Administrator", "Counselor"];
if (!in_array($role, $allowedRoles, true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$lastSubmittedId = 0;
$hasExistingSubmission = false;

$reasonOptions = [
    "Absenteeism",
    "Anxious/Nervous",
    "Bullying",
    "Chronic Sadness",
    "Cheating",
    "Disrespectful",
    "Excessive Worrying",
    "Failing Grade/s",
    "Family Concerns",
    "Fears",
    "Grief/Loss",
    "Hostility",
    "Inattentive",
    "Influence of Alcohol/Drugs",
    "Mauling/Maltreating",
    "Need of Motivation",
    "References of Suicide",
    "Social/Relationship Concerns",
    "Stealing",
    "Stress",
    "Vandalism",
    "Withdrawn",
];

function referral_summary(array $formState): array
{
    return [
        "title" => "Referral Slip",
        "sections" => [
            [
                "title" => "Referral Information",
                "entries" => [
                    ["label" => "Guidance Counselor", "value" => (string) ($formState["referred_to_counselor_name"] ?? "")],
                    ["label" => "Referral Date and Time", "value" => (string) ($formState["referral_datetime"] ?? "")],
                    ["label" => "Student Name", "value" => (string) ($formState["student_name"] ?? "")],
                    ["label" => "Course/Year/Section", "value" => (string) ($formState["course_year_section"] ?? "")],
                    ["label" => "Date Received", "value" => (string) ($formState["date_received"] ?? "")],
                    ["label" => "Received By", "value" => (string) ($formState["received_by"] ?? "")],
                ],
            ],
            [
                "title" => "Reasons for Referral",
                "entries" => [
                    ["label" => "Reasons", "value" => is_array($formState["reasons"] ?? null) ? implode(", ", $formState["reasons"]) : "", "full" => true],
                    ["label" => "Other Reason", "value" => (string) ($formState["other_reason"] ?? ""), "full" => true],
                ],
            ],
            [
                "title" => "Actions Taken",
                "entries" => [
                    ["label" => "Actions / Intervention", "value" => (string) ($formState["actions_taken"] ?? ""), "full" => true],
                    ["label" => "Action Date and Time", "value" => (string) ($formState["actions_datetime"] ?? "")],
                ],
            ],
            [
                "title" => "Signatures",
                "entries" => [
                    ["label" => "Faculty/Staff Signature", "value" => (string) ($formState["faculty_signature"] ?? ""), "signature" => (string) ($formState["faculty_signature_drawn"] ?? "")],
                    ["label" => "Counselor Signature", "value" => (string) ($formState["counselor_signature"] ?? ""), "signature" => (string) ($formState["counselor_signature_drawn"] ?? "")],
                ],
            ],
        ],
    ];
}

$formState = [
    "referred_to_counselor_id" => "",
    "referred_to_counselor_name" => "",
    "referral_datetime" => date("Y-m-d\\TH:i"),
    "student_user_id" => "",
    "student_name" => "",
    "course_year_section" => "",
    "date_received" => "",
    "received_by" => "",
    "reasons" => [],
    "other_reason" => "",
    "faculty_signature" => $fullName,
    "faculty_signature_drawn" => "",
    "actions_taken" => "",
    "actions_datetime" => "",
    "counselor_signature" => "",
    "counselor_signature_drawn" => "",
];

$counselors = [];
$students = [];

if (!campuscare_ensure_referral_forms_table($conn)) {
    $error = "Unable to initialize referral form storage.";
}

if ($error === "") {
    $latestStmt = $conn->prepare("
        SELECT id, referred_to_counselor_id, referred_to_counselor_name, referral_datetime, student_user_id, student_name,
               course_year_section, date_received, received_by, reasons_json, other_reason, faculty_signature_typed,
               faculty_signature_drawn, actions_taken, actions_datetime, counselor_signature_typed, counselor_signature_drawn
        FROM referral_forms
        WHERE submitted_by_user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");

    if ($latestStmt) {
        $latestStmt->bind_param("i", $userId);
        $latestStmt->execute();
        $latestRow = $latestStmt->get_result()->fetch_assoc();
        $latestStmt->close();

        if (is_array($latestRow)) {
            $hasExistingSubmission = true;
            $lastSubmittedId = intval($latestRow["id"] ?? 0);
            $formState["referred_to_counselor_id"] = trim((string) ($latestRow["referred_to_counselor_id"] ?? ""));
            $formState["referred_to_counselor_name"] = trim((string) ($latestRow["referred_to_counselor_name"] ?? ""));
            $formState["referral_datetime"] = trim((string) ($latestRow["referral_datetime"] ?? ""));
            $formState["student_user_id"] = trim((string) ($latestRow["student_user_id"] ?? ""));
            $formState["student_name"] = trim((string) ($latestRow["student_name"] ?? ""));
            $formState["course_year_section"] = trim((string) ($latestRow["course_year_section"] ?? ""));
            $formState["date_received"] = trim((string) ($latestRow["date_received"] ?? ""));
            $formState["received_by"] = trim((string) ($latestRow["received_by"] ?? ""));
            $formState["reasons"] = json_decode((string) ($latestRow["reasons_json"] ?? "[]"), true);
            if (!is_array($formState["reasons"])) {
                $formState["reasons"] = [];
            }
            $formState["other_reason"] = trim((string) ($latestRow["other_reason"] ?? ""));
            $formState["faculty_signature"] = trim((string) ($latestRow["faculty_signature_typed"] ?? ""));
            $formState["faculty_signature_drawn"] = trim((string) ($latestRow["faculty_signature_drawn"] ?? ""));
            $formState["actions_taken"] = trim((string) ($latestRow["actions_taken"] ?? ""));
            $formState["actions_datetime"] = trim((string) ($latestRow["actions_datetime"] ?? ""));
            $formState["counselor_signature"] = trim((string) ($latestRow["counselor_signature_typed"] ?? ""));
            $formState["counselor_signature_drawn"] = trim((string) ($latestRow["counselor_signature_drawn"] ?? ""));
        }
    }
}

$counselorResult = $conn->query("SELECT id, full_name FROM users WHERE role IN ('Counselor', 'Counsellor', 'Counselors') AND status = 'Active' ORDER BY full_name ASC");
while ($counselorResult && ($row = $counselorResult->fetch_assoc())) {
    $counselors[] = $row;
}

$studentResult = $conn->query("SELECT id, full_name, student_id FROM users WHERE role = 'Student' AND status = 'Active' ORDER BY full_name ASC");
while ($studentResult && ($row = $studentResult->fetch_assoc())) {
    $students[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $formState["referred_to_counselor_id"] = trim((string) ($_POST["referred_to_counselor_id"] ?? ""));
    $formState["referred_to_counselor_name"] = trim((string) ($_POST["referred_to_counselor_name"] ?? ""));
    $formState["referral_datetime"] = trim((string) ($_POST["referral_datetime"] ?? ""));
    $formState["student_user_id"] = trim((string) ($_POST["student_user_id"] ?? ""));
    $formState["student_name"] = trim((string) ($_POST["student_name"] ?? ""));
    $formState["course_year_section"] = trim((string) ($_POST["course_year_section"] ?? ""));
    $formState["other_reason"] = trim((string) ($_POST["other_reason"] ?? ""));
    $formState["faculty_signature"] = trim((string) ($_POST["faculty_signature"] ?? ""));
    $formState["faculty_signature_drawn"] = trim((string) ($_POST["faculty_signature_drawn"] ?? ""));
    if ($canManageForms) {
        $formState["date_received"] = trim((string) ($_POST["date_received"] ?? ""));
        $formState["received_by"] = trim((string) ($_POST["received_by"] ?? ""));
        $formState["actions_taken"] = trim((string) ($_POST["actions_taken"] ?? ""));
        $formState["actions_datetime"] = trim((string) ($_POST["actions_datetime"] ?? ""));
        $formState["counselor_signature"] = trim((string) ($_POST["counselor_signature"] ?? ""));
        $formState["counselor_signature_drawn"] = trim((string) ($_POST["counselor_signature_drawn"] ?? ""));
    } else {
        $formState["date_received"] = "";
        $formState["received_by"] = "";
        $formState["actions_taken"] = "";
        $formState["actions_datetime"] = "";
        $formState["counselor_signature"] = "";
        $formState["counselor_signature_drawn"] = "";
    }

    $selectedReasons = $_POST["reasons"] ?? [];
    $formState["reasons"] = is_array($selectedReasons) ? array_values(array_map("trim", $selectedReasons)) : [];

    $counselorId = intval($formState["referred_to_counselor_id"]);
    $counselorName = $formState["referred_to_counselor_name"];

    foreach ($counselors as $c) {
        if (intval($c["id"]) === $counselorId) {
            $counselorName = trim((string) $c["full_name"]);
            break;
        }
    }

    $studentId = intval($formState["student_user_id"]);

    if ($counselorName === "") {
        $error = "Please specify the guidance counselor.";
    } elseif ($formState["referral_datetime"] === "") {
        $error = "Please provide the referral date and time.";
    } elseif ($formState["student_name"] === "") {
        $error = "Please provide the student name.";
    } elseif ($formState["course_year_section"] === "") {
        $error = "Please provide course/year/section.";
    } elseif (empty($formState["reasons"]) && $formState["other_reason"] === "") {
        $error = "Please check at least one reason for referral or specify other reason.";
    } elseif (!campuscare_signature_present($formState["faculty_signature"], $formState["faculty_signature_drawn"])) {
        $error = "Please provide faculty/staff signature (typed or drawn).";
    } else {
        $reasonsJson = json_encode($formState["reasons"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($reasonsJson === false) {
            $error = "Unable to save referral reasons.";
        } else {
            $status = "Pending";
            if ($canManageForms && (
                $formState["actions_taken"] !== "" ||
                $formState["date_received"] !== "" ||
                campuscare_signature_present($formState["counselor_signature"], $formState["counselor_signature_drawn"])
            )) {
                $status = "In Review";
            }
            $dateReceived = $formState["date_received"] !== "" ? $formState["date_received"] : null;
            $receivedBy = $formState["received_by"] !== "" ? $formState["received_by"] : null;
            $actionsTaken = $formState["actions_taken"] !== "" ? $formState["actions_taken"] : null;
            $actionsDatetime = $formState["actions_datetime"] !== "" ? $formState["actions_datetime"] : null;
            $counselorSignature = $formState["counselor_signature"] !== "" ? $formState["counselor_signature"] : null;

            $insert = $conn->prepare("INSERT INTO referral_forms (submitted_by_user_id, submitted_by_name, submitted_by_role, referred_to_counselor_id, referred_to_counselor_name, referral_datetime, student_user_id, student_name, course_year_section, date_received, received_by, reasons_json, other_reason, faculty_signature_typed, faculty_signature_drawn, actions_taken, actions_datetime, counselor_signature_typed, counselor_signature_drawn, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if (!$insert) {
                $error = "Unable to save referral slip.";
            } else {
                $insert->bind_param(
                    "issississsssssssssss",
                    $userId,
                    $fullName,
                    $role,
                    $counselorId,
                    $counselorName,
                    $formState["referral_datetime"],
                    $studentId,
                    $formState["student_name"],
                    $formState["course_year_section"],
                    $dateReceived,
                    $receivedBy,
                    $reasonsJson,
                    $formState["other_reason"],
                    $formState["faculty_signature"],
                    $formState["faculty_signature_drawn"],
                    $actionsTaken,
                    $actionsDatetime,
                    $counselorSignature,
                    $formState["counselor_signature_drawn"],
                    $status
                );

                if ($insert->execute()) {
                    $lastSubmittedId = intval($insert->insert_id);
                    $success = "Referral slip submitted successfully.";
                    $hasExistingSubmission = true;
                    $requestedMode = "view";
                    $isViewMode = $isIframe;
                } else {
                    $error = "Failed to submit referral slip.";
                }

                $insert->close();
            }
        }
    }
}

$isViewMode = $isIframe && $hasExistingSubmission && $requestedMode !== "edit";

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
        <div class="page-shell" style="max-width:980px;">
            <div class="dashboard-head">
                <div>
                    <h1 class="page-title">Referral Slip</h1>
                    <p class="page-subtitle">Fill up all required details from the paper referral slip.</p>
                </div>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" class="<?php echo $isIframe ? "" : "card"; ?>" style="padding:20px;">
                <style>
                <?php if ($isIframe): ?>
                body {
                    background: #f6fbff;
                }

                html,
                body {
                    height: auto !important;
                    min-height: 0 !important;
                    overflow-x: hidden;
                }

                .sidebar,
                .topbar,
                .page-title,
                .page-subtitle,
                .menu-toggle,
                .topbar-user,
                .chat-fab {
                    display: none !important;
                }

                .app,
                .main,
                .content,
                .page-shell {
                    max-width: none !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                form {
                    background: transparent !important;
                    border: 0 !important;
                    box-shadow: none !important;
                    border-radius: 0 !important;
                }

                .content,
                .page-shell {
                    min-height: 0 !important;
                }
                <?php endif; ?>

                .ref-two-col { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
                .ref-reasons { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:8px 12px; border:1px solid var(--border); border-radius:10px; padding:12px; }
                .ref-check { display:inline-flex; align-items:center; gap:8px; margin:0; font-weight:600; font-size:14px; }
                .ref-check input[type="checkbox"] { width:16px; height:16px; margin:0; }
                .sig-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; margin-top:8px; }
                .sig-card { border:1px solid var(--border); border-radius:10px; padding:10px; }
                .sig-pad { width:100%; height:140px; border:1px dashed #96abc0; border-radius:8px; background:#fff; display:block; touch-action:none; cursor:crosshair; }
                .sig-actions { margin-top:8px; display:flex; gap:8px; justify-content:flex-end; }
                .sig-help { margin-top:6px; color:var(--text-muted); font-size:12px; }
                @media (max-width: 880px) { .ref-reasons { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
                @media (max-width: 700px) { .ref-two-col, .ref-reasons, .sig-grid { grid-template-columns: 1fr; } }
                </style>
                <fieldset id="embeddedFormFieldset" style="border:0; padding:0; margin:0;">

                <div class="ref-two-col">
                    <div class="form-group">
                        <label for="referred_to_counselor_id">To the Guidance Counselor</label>
                        <select id="referred_to_counselor_id" name="referred_to_counselor_id">
                            <option value="">Select counselor</option>
                            <?php foreach ($counselors as $c): ?>
                                <option value="<?php echo intval($c["id"]); ?>" <?php echo intval($formState["referred_to_counselor_id"]) === intval($c["id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars((string) $c["full_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="referral_datetime">Date and Time</label>
                        <input id="referral_datetime" name="referral_datetime" type="datetime-local" value="<?php echo htmlspecialchars($formState["referral_datetime"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="student_user_id">Student (optional lookup)</label>
                        <select id="student_user_id" name="student_user_id">
                            <option value="">Select student</option>
                            <?php foreach ($students as $s): ?>
                                <option value="<?php echo intval($s["id"]); ?>" data-name="<?php echo htmlspecialchars((string) $s["full_name"]); ?>" <?php echo intval($formState["student_user_id"]) === intval($s["id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars((string) $s["full_name"] . " (" . (string) ($s["student_id"] ?? "N/A") . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student_name">Student Name</label>
                        <input id="student_name" name="student_name" type="text" value="<?php echo htmlspecialchars($formState["student_name"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="course_year_section">Course/Year/Section</label>
                        <input id="course_year_section" name="course_year_section" type="text" value="<?php echo htmlspecialchars($formState["course_year_section"]); ?>" required>
                    </div>
                    <?php if ($canManageForms): ?>
                        <div class="form-group"></div>
                        <div class="form-group">
                            <label for="date_received">Date Received</label>
                            <input id="date_received" name="date_received" type="date" value="<?php echo htmlspecialchars($formState["date_received"]); ?>">
                        </div>
                        <div class="form-group">
                            <label for="received_by">Received By</label>
                            <input id="received_by" name="received_by" type="text" value="<?php echo htmlspecialchars($formState["received_by"]); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Chief reason for referral / problems / concerns related to (please check all that apply)</label>
                    <div class="ref-reasons">
                        <?php foreach ($reasonOptions as $reason): ?>
                            <label class="ref-check">
                                <input type="checkbox" name="reasons[]" value="<?php echo htmlspecialchars($reason); ?>" <?php echo in_array($reason, $formState["reasons"], true) ? "checked" : ""; ?>>
                                <span><?php echo htmlspecialchars($reason); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="other_reason">Others (please specify)</label>
                    <textarea id="other_reason" name="other_reason" rows="2"><?php echo htmlspecialchars($formState["other_reason"]); ?></textarea>
                </div>

                <div class="ref-two-col">
                    <div class="form-group">
                        <label for="faculty_signature">Name and signature of faculty/staff</label>
                        <input id="faculty_signature" name="faculty_signature" type="text" value="<?php echo htmlspecialchars($formState["faculty_signature"]); ?>" required>
                    </div>
                    <?php if ($canManageForms): ?>
                        <div class="form-group"></div>
                        <div class="form-group">
                            <label for="actions_taken">Actions Taken</label>
                            <textarea id="actions_taken" name="actions_taken" rows="3"><?php echo htmlspecialchars($formState["actions_taken"]); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="actions_datetime">Date and Time</label>
                            <input id="actions_datetime" name="actions_datetime" type="datetime-local" value="<?php echo htmlspecialchars($formState["actions_datetime"]); ?>">
                        </div>
                        <div class="form-group">
                            <label for="counselor_signature">Name and Signature (Guidance Designate / Counselor)</label>
                            <input id="counselor_signature" name="counselor_signature" type="text" value="<?php echo htmlspecialchars($formState["counselor_signature"]); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sig-grid">
                    <div class="sig-card">
                        <label>Faculty/Staff Drawn Signature</label>
                        <canvas id="facultySignaturePad" class="sig-pad"></canvas>
                        <input type="hidden" id="faculty_signature_drawn" name="faculty_signature_drawn" value="<?php echo htmlspecialchars($formState["faculty_signature_drawn"]); ?>">
                        <p class="sig-help">Draw using mouse/touch/stylus.</p>
                        <div class="sig-actions">
                            <button type="button" class="btn-outline" id="undoFacultySignature">Undo</button>
                            <button type="button" class="btn-outline" id="clearFacultySignature">Clear</button>
                        </div>
                    </div>

                    <?php if ($canManageForms): ?>
                        <div class="sig-card">
                            <label>Counselor Drawn Signature</label>
                            <canvas id="counselorSignaturePad" class="sig-pad"></canvas>
                            <input type="hidden" id="counselor_signature_drawn" name="counselor_signature_drawn" value="<?php echo htmlspecialchars($formState["counselor_signature_drawn"]); ?>">
                            <p class="sig-help">Counselor/admin completion block.</p>
                            <div class="sig-actions">
                                <button type="button" class="btn-outline" id="undoCounselorSignature">Undo</button>
                                <button type="button" class="btn-outline" id="clearCounselorSignature">Clear</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$isIframe): ?>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn">Submit Referral Slip</button>
                    <?php if ($lastSubmittedId > 0): ?>
                        <a href="/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=<?php echo intval($lastSubmittedId); ?>" class="btn-outline" target="_blank" rel="noopener">Preview Printable Slip</a>
                    <?php endif; ?>
                    <?php if ($canManageForms): ?>
                        <a href="/campuscare-api/php-frontend/pages/forms/referral_inbox.php" class="btn-outline">Open Referral Inbox</a>
                    <?php endif; ?>
                    <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="btn-outline">Back to Dashboard</a>
                </div>
                <?php endif; ?>
                </fieldset>
            </form>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    var embeddedFormFieldset = document.getElementById("embeddedFormFieldset");
    var embeddedFormIsViewMode = <?php echo $isViewMode ? "true" : "false"; ?>;

    function setupSignaturePad(canvasId, hiddenInputId, clearButtonId, undoButtonId) {
        var canvas = document.getElementById(canvasId);
        var hiddenInput = document.getElementById(hiddenInputId);
        var clearButton = document.getElementById(clearButtonId);
        var undoButton = document.getElementById(undoButtonId);

        if (!canvas || !hiddenInput || !clearButton || !undoButton) {
            return;
        }

        var ctx = canvas.getContext("2d");
        var drawing = false;
        var hasStroke = false;
        var history = [];

        function pushHistory(snapshot) {
            if (history.length === 0 || history[history.length - 1] !== snapshot) {
                history.push(snapshot);
            }

            if (history.length > 50) {
                history.shift();
            }
        }

        function restoreSnapshot(snapshot) {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var displayWidth = canvas.clientWidth;
            var displayHeight = canvas.clientHeight;

            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (snapshot === "") {
                hiddenInput.value = "";
                hasStroke = false;
                return;
            }

            var image = new Image();
            image.onload = function () {
                ctx.drawImage(image, 0, 0, displayWidth, displayHeight);
            };
            image.src = snapshot;
            hiddenInput.value = snapshot;
            hasStroke = true;
        }

        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            var displayWidth = canvas.clientWidth;
            var displayHeight = canvas.clientHeight;
            var snapshot = hiddenInput.value;

            canvas.width = Math.floor(displayWidth * ratio);
            canvas.height = Math.floor(displayHeight * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineCap = "round";
            ctx.lineJoin = "round";
            ctx.lineWidth = 2;
            ctx.strokeStyle = "#102a43";

            restoreSnapshot(snapshot);
        }

        function pointFromEvent(event) {
            var rect = canvas.getBoundingClientRect();
            if (event.touches && event.touches.length > 0) {
                return {
                    x: event.touches[0].clientX - rect.left,
                    y: event.touches[0].clientY - rect.top,
                };
            }

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        }

        function beginDraw(event) {
            if (embeddedFormIsViewMode) {
                return;
            }

            drawing = true;
            var point = pointFromEvent(event);
            ctx.beginPath();
            ctx.moveTo(point.x, point.y);
            event.preventDefault();
        }

        function draw(event) {
            if (!drawing) {
                return;
            }

            var point = pointFromEvent(event);
            ctx.lineTo(point.x, point.y);
            ctx.stroke();
            hasStroke = true;
            event.preventDefault();
        }

        function endDraw() {
            if (!drawing) {
                return;
            }

            drawing = false;
            ctx.closePath();

            if (hasStroke) {
                hiddenInput.value = canvas.toDataURL("image/png");
                pushHistory(hiddenInput.value);
            }
        }

        undoButton.addEventListener("click", function () {
            if (history.length <= 1) {
                restoreSnapshot("");
                history = [""];
                return;
            }

            history.pop();
            restoreSnapshot(history[history.length - 1] || "");
        });

        clearButton.addEventListener("click", function () {
            restoreSnapshot("");
            history = [""];
        });

        canvas.addEventListener("mousedown", beginDraw);
        canvas.addEventListener("mousemove", draw);
        canvas.addEventListener("mouseup", endDraw);
        canvas.addEventListener("mouseleave", endDraw);
        canvas.addEventListener("touchstart", beginDraw, { passive: false });
        canvas.addEventListener("touchmove", draw, { passive: false });
        canvas.addEventListener("touchend", endDraw);
        canvas.addEventListener("touchcancel", endDraw);

        window.addEventListener("resize", resizeCanvas);
        history = [hiddenInput.value || ""];
        resizeCanvas();
    }

    function applyEmbeddedFormMode() {
        if (embeddedFormFieldset) {
            embeddedFormFieldset.disabled = embeddedFormIsViewMode;
        }

        document.querySelectorAll(".sig-pad").forEach(function (canvas) {
            canvas.style.pointerEvents = embeddedFormIsViewMode ? "none" : "auto";
            canvas.style.opacity = embeddedFormIsViewMode ? "0.72" : "1";
        });
    }

    function notifyParentHeight() {
        if (!(window.parent && window.parent !== window)) {
            return;
        }

        var form = document.querySelector("form");
        var shell = document.querySelector(".page-shell");
        var height = Math.max(
            document.body ? document.body.scrollHeight : 0,
            document.documentElement ? document.documentElement.scrollHeight : 0,
            form ? Math.ceil(form.getBoundingClientRect().height) : 0,
            shell ? Math.ceil(shell.getBoundingClientRect().height) : 0
        );

        window.parent.postMessage({
            type: "campuscare-form-height",
            formType: "referral",
            height: height
        }, "*");
    }

    var embeddedFormElement = document.querySelector("form");
    var embeddedFormDraftKey = "campuscare_form_draft_referral_<?php echo intval($userId); ?>";

    function normalizeDraftFieldName(name) {
        return name.slice(-2) === "[]" ? name.slice(0, -2) : name;
    }

    function readDraftState() {
        try {
            var raw = sessionStorage.getItem(embeddedFormDraftKey);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function hasMeaningfulDraftValue(value) {
        if (Array.isArray(value)) {
            return value.some(hasMeaningfulDraftValue);
        }

        return String(value || "").trim() !== "";
    }

    function applyDraftState(draft) {
        if (!draft || typeof draft !== "object" || !embeddedFormElement || embeddedFormIsViewMode) {
            return false;
        }

        Array.prototype.forEach.call(embeddedFormElement.elements, function (field) {
            if (!field || !field.name) {
                return;
            }

            var normalizedName = normalizeDraftFieldName(field.name);
            if (!Object.prototype.hasOwnProperty.call(draft, normalizedName)) {
                return;
            }

            var savedValue = draft[normalizedName];

            if (field.type === "checkbox") {
                if (Array.isArray(savedValue)) {
                    field.checked = savedValue.indexOf(field.value) !== -1;
                } else {
                    field.checked = !!savedValue;
                }
                return;
            }

            if (field.type === "radio") {
                field.checked = String(savedValue) === String(field.value);
                return;
            }

            if (field.type !== "file") {
                field.value = Array.isArray(savedValue) ? (savedValue[0] || "") : savedValue;
            }
        });

        return true;
    }

    function collectDraftState() {
        if (!embeddedFormElement) {
            return {};
        }

        var state = {};

        Array.prototype.forEach.call(embeddedFormElement.elements, function (field) {
            if (!field || !field.name || field.disabled || field.type === "file") {
                return;
            }

            var normalizedName = normalizeDraftFieldName(field.name);

            if (field.type === "checkbox") {
                if (field.name.slice(-2) === "[]") {
                    if (!Array.isArray(state[normalizedName])) {
                        state[normalizedName] = [];
                    }
                    if (field.checked) {
                        state[normalizedName].push(field.value);
                    }
                } else {
                    state[normalizedName] = field.checked;
                }
                return;
            }

            if (field.type === "radio") {
                if (field.checked) {
                    state[normalizedName] = field.value;
                }
                return;
            }

            state[normalizedName] = field.value;
        });

        return state;
    }

    function persistDraftState() {
        if (embeddedFormIsViewMode) {
            return;
        }

        var state = collectDraftState();
        var hasDraftData = Object.keys(state).some(function (key) {
            return hasMeaningfulDraftValue(state[key]);
        });

        try {
            if (hasDraftData) {
                sessionStorage.setItem(embeddedFormDraftKey, JSON.stringify(state));
            } else {
                sessionStorage.removeItem(embeddedFormDraftKey);
            }
        } catch (error) {}
    }

    function clearDraftState() {
        try {
            sessionStorage.removeItem(embeddedFormDraftKey);
        } catch (error) {}
    }

    var restoredDraftState = readDraftState();
    var hasRestoredDraft = applyDraftState(restoredDraftState);

    setupSignaturePad("facultySignaturePad", "faculty_signature_drawn", "clearFacultySignature", "undoFacultySignature");
    setupSignaturePad("counselorSignaturePad", "counselor_signature_drawn", "clearCounselorSignature", "undoCounselorSignature");
    applyEmbeddedFormMode();

    if (embeddedFormElement && !embeddedFormIsViewMode) {
        embeddedFormElement.addEventListener("input", persistDraftState);
        embeddedFormElement.addEventListener("change", persistDraftState);
    }
    notifyParentHeight();

    var studentSelect = document.getElementById("student_user_id");
    var studentName = document.getElementById("student_name");

    if (studentSelect && studentName) {
        studentSelect.addEventListener("change", function () {
            var selected = studentSelect.options[studentSelect.selectedIndex];
            var name = selected ? (selected.getAttribute("data-name") || "") : "";
            if (name !== "") {
                studentName.value = name;
            }
            notifyParentHeight();
        });
    }

    window.addEventListener("load", notifyParentHeight);
    window.addEventListener("resize", notifyParentHeight);
    setTimeout(notifyParentHeight, 150);
    setTimeout(notifyParentHeight, 500);

    <?php if ($isIframe): ?>
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: "campuscare-form-loaded",
            formType: "referral",
            isViewMode: <?php echo $isViewMode ? "true" : "false"; ?>,
            hasSavedData: <?php echo $hasExistingSubmission ? "true" : "false"; ?>,
            hasDraftData: hasRestoredDraft,
            message: <?php echo json_encode($hasExistingSubmission ? "Your latest referral form is loaded." : "Fill out the referral form to continue."); ?>,
            previewUrl: <?php echo json_encode($lastSubmittedId > 0 ? "/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=" . intval($lastSubmittedId) : ""); ?>,
            summary: <?php echo json_encode($hasExistingSubmission ? referral_summary($formState) : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }, "*");
        notifyParentHeight();
    }
    <?php endif; ?>

    <?php if ($success !== ""): ?>
    clearDraftState();
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: "campuscare-form-saved",
            formType: "referral",
            message: "Referral form saved. You can now continue to schedule and review.",
            previewUrl: <?php echo json_encode($lastSubmittedId > 0 ? "/campuscare-api/php-frontend/pages/forms/referral_form_preview.php?id=" . intval($lastSubmittedId) : ""); ?>,
            summary: <?php echo json_encode(referral_summary($formState), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }, "*");
        notifyParentHeight();
    }
    <?php endif; ?>


})();
</script>
</body>
</html>

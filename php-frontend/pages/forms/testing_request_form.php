<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Request for Testing Form";
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

function testing_request_summary(array $formState): array
{
    return [
        "title" => "Testing Request",
        "sections" => [
            [
                "title" => "Applicant Section",
                "entries" => [
                    ["label" => "Target Student", "value" => (string) ($formState["target_student_name"] ?? "")],
                    ["label" => "Date", "value" => (string) ($formState["request_date"] ?? "")],
                    ["label" => "Organization/Office", "value" => (string) ($formState["organization_office"] ?? "")],
                    ["label" => "Address", "value" => (string) ($formState["address"] ?? ""), "full" => true],
                    ["label" => "Purpose", "value" => (string) ($formState["purpose"] ?? ""), "full" => true],
                    ["label" => "Applicant Signature", "value" => (string) ($formState["applicant_name_signature_typed"] ?? ""), "signature" => (string) ($formState["applicant_name_signature_drawn"] ?? "")],
                ],
            ],
            [
                "title" => "Counselor/Psychometrician Section",
                "entries" => [
                    ["label" => "Type of Tests", "value" => (string) ($formState["counselor_type_of_tests"] ?? ""), "full" => true],
                    ["label" => "Counselor Notes", "value" => (string) ($formState["counselor_notes"] ?? ""), "full" => true],
                ],
            ],
        ],
    ];
}

$formState = [
    "request_date" => date("Y-m-d"),
    "target_student_user_id" => "",
    "target_student_name" => $fullName,
    "applicant_name_signature_typed" => $fullName,
    "applicant_name_signature_drawn" => "",
    "address" => "",
    "organization_office" => "",
    "purpose" => "",
    "counselor_type_of_tests" => "",
    "counselor_notes" => "",
];

if (!campuscare_ensure_testing_requests_table($conn)) {
    $error = "Unable to initialize testing request storage.";
}

if ($error === "") {
    $latestStmt = $conn->prepare("
        SELECT id, request_date, target_student_user_id, target_student_name, applicant_name_signature_typed,
               applicant_name_signature_drawn, address, organization_office, purpose, counselor_type_of_tests, counselor_notes
        FROM testing_requests
        WHERE requester_user_id = ?
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
            $formState["request_date"] = trim((string) ($latestRow["request_date"] ?? ""));
            $formState["target_student_user_id"] = trim((string) ($latestRow["target_student_user_id"] ?? ""));
            $formState["target_student_name"] = trim((string) ($latestRow["target_student_name"] ?? ""));
            $formState["applicant_name_signature_typed"] = trim((string) ($latestRow["applicant_name_signature_typed"] ?? ""));
            $formState["applicant_name_signature_drawn"] = trim((string) ($latestRow["applicant_name_signature_drawn"] ?? ""));
            $formState["address"] = trim((string) ($latestRow["address"] ?? ""));
            $formState["organization_office"] = trim((string) ($latestRow["organization_office"] ?? ""));
            $formState["purpose"] = trim((string) ($latestRow["purpose"] ?? ""));
            $formState["counselor_type_of_tests"] = trim((string) ($latestRow["counselor_type_of_tests"] ?? ""));
            $formState["counselor_notes"] = trim((string) ($latestRow["counselor_notes"] ?? ""));
        }
    }
}

$students = [];
$studentResult = $conn->query("SELECT id, full_name, student_id FROM users WHERE role = 'Student' AND status = 'Active' ORDER BY full_name ASC");
while ($studentResult && ($row = $studentResult->fetch_assoc())) {
    $students[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $formState["request_date"] = trim((string) ($_POST["request_date"] ?? ""));
    $formState["target_student_user_id"] = trim((string) ($_POST["target_student_user_id"] ?? ""));
    $formState["target_student_name"] = trim((string) ($_POST["target_student_name"] ?? ""));
    $formState["applicant_name_signature_typed"] = trim((string) ($_POST["applicant_name_signature_typed"] ?? ""));
    $formState["applicant_name_signature_drawn"] = trim((string) ($_POST["applicant_name_signature_drawn"] ?? ""));
    $formState["address"] = trim((string) ($_POST["address"] ?? ""));
    $formState["organization_office"] = trim((string) ($_POST["organization_office"] ?? ""));
    $formState["purpose"] = trim((string) ($_POST["purpose"] ?? ""));
    if ($canManageForms) {
        $formState["counselor_type_of_tests"] = trim((string) ($_POST["counselor_type_of_tests"] ?? ""));
        $formState["counselor_notes"] = trim((string) ($_POST["counselor_notes"] ?? ""));
    } else {
        $formState["counselor_type_of_tests"] = "";
        $formState["counselor_notes"] = "";
    }

    $targetStudentId = intval($formState["target_student_user_id"]);
    $targetStudentName = $formState["target_student_name"];

    if ($role === "Student") {
        $targetStudentId = $userId;
        $targetStudentName = $fullName;
    } else {
        foreach ($students as $s) {
            if (intval($s["id"]) === $targetStudentId) {
                $targetStudentName = trim((string) $s["full_name"]);
                break;
            }
        }
    }

    $status = "Pending";

    if ($formState["request_date"] === "") {
        $error = "Please provide the date.";
    } elseif (!campuscare_signature_present($formState["applicant_name_signature_typed"], $formState["applicant_name_signature_drawn"])) {
        $error = "Please provide applicant signature (typed or drawn).";
    } elseif ($targetStudentName === "") {
        $error = "Please provide the target student name.";
    } elseif ($formState["address"] === "") {
        $error = "Please provide the address.";
    } elseif ($formState["organization_office"] === "") {
        $error = "Please provide the organization/office.";
    } elseif ($formState["purpose"] === "") {
        $error = "Please provide the purpose.";
    } else {
        $reviewedByUserId = null;
        $reviewedByName = null;

        if ($canManageForms && $formState["counselor_type_of_tests"] !== "") {
            $status = "In Review";
            $reviewedByUserId = $userId;
            $reviewedByName = $fullName;
        }

        $insert = $conn->prepare("INSERT INTO testing_requests (requester_user_id, requester_role, applicant_name_signature_typed, applicant_name_signature_drawn, request_date, target_student_user_id, target_student_name, address, organization_office, purpose, counselor_type_of_tests, counselor_notes, reviewed_by_user_id, reviewed_by_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$insert) {
            $error = "Unable to save testing request form.";
        } else {
            $insert->bind_param(
                "issssissssssiss",
                $userId,
                $role,
                $formState["applicant_name_signature_typed"],
                $formState["applicant_name_signature_drawn"],
                $formState["request_date"],
                $targetStudentId,
                $targetStudentName,
                $formState["address"],
                $formState["organization_office"],
                $formState["purpose"],
                $formState["counselor_type_of_tests"],
                $formState["counselor_notes"],
                $reviewedByUserId,
                $reviewedByName,
                $status
            );

            if ($insert->execute()) {
                $lastSubmittedId = intval($insert->insert_id);
                $success = "Request for testing form submitted successfully.";
                $hasExistingSubmission = true;
                $requestedMode = "view";
                $isViewMode = $isIframe;
            } else {
                $error = "Failed to submit testing request form.";
            }

            $insert->close();
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
        <div class="page-shell" style="max-width:920px;">
            <h1 class="page-title">Request for Testing Form</h1>
            <p class="page-subtitle">Matches the paper form fields and sections.</p>

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

                .form-submit-container {
                    display: none !important;
                }
                <?php endif; ?>

                .test-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
                .test-full { grid-column: 1 / -1; }
                .sig-card { border:1px solid var(--border); border-radius:10px; padding:10px; }
                .sig-pad { width:100%; height:140px; border:1px dashed #96abc0; border-radius:8px; background:#fff; display:block; touch-action:none; cursor:crosshair; }
                .sig-actions { margin-top:8px; display:flex; gap:8px; justify-content:flex-end; }
                .sig-help { margin-top:6px; color:var(--text-muted); font-size:12px; }
                @media (max-width: 700px) { .test-grid { grid-template-columns: 1fr; } }
                </style>
                <fieldset id="embeddedFormFieldset" style="border:0; padding:0; margin:0;">

                <p class="card-subtitle" style="margin-bottom:10px;"><strong>(To be filled up by the applicant)</strong></p>
                <div class="test-grid">
                    <div class="form-group">
                        <label for="request_date">Date</label>
                        <input id="request_date" name="request_date" type="date" value="<?php echo htmlspecialchars($formState["request_date"]); ?>" required>
                    </div>

                    <?php if ($role !== "Student"): ?>
                        <div class="form-group">
                            <label for="target_student_user_id">Target Student (optional lookup)</label>
                            <select id="target_student_user_id" name="target_student_user_id">
                                <option value="">Select student</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo intval($s["id"]); ?>" data-name="<?php echo htmlspecialchars((string) $s["full_name"]); ?>" <?php echo intval($formState["target_student_user_id"]) === intval($s["id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars((string) $s["full_name"] . " (" . (string) ($s["student_id"] ?? "N/A") . ")"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group test-full">
                        <label for="applicant_name_signature_typed">Name and Signature (over printed name)</label>
                        <input id="applicant_name_signature_typed" name="applicant_name_signature_typed" type="text" value="<?php echo htmlspecialchars($formState["applicant_name_signature_typed"]); ?>" required>
                    </div>

                    <div class="sig-card test-full">
                        <label>Applicant Drawn Signature</label>
                        <canvas id="applicantSignaturePad" class="sig-pad"></canvas>
                        <input type="hidden" id="applicant_name_signature_drawn" name="applicant_name_signature_drawn" value="<?php echo htmlspecialchars($formState["applicant_name_signature_drawn"]); ?>">
                        <p class="sig-help">Draw with mouse/touch/stylus, similar to the counseling intake form.</p>
                        <div class="sig-actions">
                            <button type="button" class="btn-outline" id="undoApplicantSignature">Undo</button>
                            <button type="button" class="btn-outline" id="clearApplicantSignature">Clear</button>
                        </div>
                    </div>

                    <div class="form-group test-full">
                        <label for="target_student_name">Student Name</label>
                        <input id="target_student_name" name="target_student_name" type="text" value="<?php echo htmlspecialchars($formState["target_student_name"]); ?>" required>
                    </div>

                    <div class="form-group test-full">
                        <label for="address">Address</label>
                        <input id="address" name="address" type="text" value="<?php echo htmlspecialchars($formState["address"]); ?>" required>
                    </div>

                    <div class="form-group test-full">
                        <label for="organization_office">Organization/Office</label>
                        <input id="organization_office" name="organization_office" type="text" value="<?php echo htmlspecialchars($formState["organization_office"]); ?>" required>
                    </div>

                    <div class="form-group test-full">
                        <label for="purpose">Purpose</label>
                        <textarea id="purpose" name="purpose" rows="3" required><?php echo htmlspecialchars($formState["purpose"]); ?></textarea>
                    </div>
                </div>

                <p class="card-subtitle" style="margin:16px 0 10px;"><strong>(To be filled by the Guidance Counselor/Psychometrician)</strong></p>
                <?php if ($canManageForms): ?>
                    <div class="test-grid">
                        <div class="form-group test-full">
                            <label for="counselor_type_of_tests">Type of Tests</label>
                            <textarea id="counselor_type_of_tests" name="counselor_type_of_tests" rows="2" placeholder="Counselor/psychometrician entry"><?php echo htmlspecialchars($formState["counselor_type_of_tests"]); ?></textarea>
                        </div>
                        <div class="form-group test-full">
                            <label for="counselor_notes">Counselor Notes (optional)</label>
                            <textarea id="counselor_notes" name="counselor_notes" rows="3"><?php echo htmlspecialchars($formState["counselor_notes"]); ?></textarea>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="page-subtitle" style="margin-bottom: 12px;">Counselor/Psychometrician fields are completed in the counselor/admin inbox.</p>
                <?php endif; ?>

                <div class="form-submit-container" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn">Submit Testing Request</button>
                    <?php if ($lastSubmittedId > 0): ?>
                        <a href="/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=<?php echo intval($lastSubmittedId); ?>" class="btn-outline" target="_blank" rel="noopener">Preview Printable Form</a>
                    <?php endif; ?>
                    <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="btn-outline">Back to Dashboard</a>
                </div>
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

    var embeddedFormElement = document.querySelector("form");
    var embeddedFormDraftKey = "campuscare_form_draft_testing_<?php echo intval($userId); ?>";

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

    setupSignaturePad("applicantSignaturePad", "applicant_name_signature_drawn", "clearApplicantSignature", "undoApplicantSignature");
    applyEmbeddedFormMode();

    if (embeddedFormElement && !embeddedFormIsViewMode) {
        embeddedFormElement.addEventListener("input", persistDraftState);
        embeddedFormElement.addEventListener("change", persistDraftState);
    }

    var studentSelect = document.getElementById("target_student_user_id");
    var studentName = document.getElementById("target_student_name");

    if (studentSelect && studentName) {
        studentSelect.addEventListener("change", function () {
            var selected = studentSelect.options[studentSelect.selectedIndex];
            var name = selected ? (selected.getAttribute("data-name") || "") : "";
            if (name !== "") {
                studentName.value = name;
            }
        });
    }

    <?php if ($isIframe): ?>
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: "campuscare-form-loaded",
            formType: "testing",
            isViewMode: <?php echo $isViewMode ? "true" : "false"; ?>,
            hasSavedData: <?php echo $hasExistingSubmission ? "true" : "false"; ?>,
            hasDraftData: hasRestoredDraft,
            message: <?php echo json_encode($hasExistingSubmission ? "Your latest testing request form is loaded." : "Fill out the testing request form to continue."); ?>,
            previewUrl: <?php echo json_encode($lastSubmittedId > 0 ? "/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=" . intval($lastSubmittedId) : ""); ?>,
            summary: <?php echo json_encode($hasExistingSubmission ? testing_request_summary($formState) : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }, "*");
    }
    <?php endif; ?>

    <?php if ($success !== ""): ?>
    clearDraftState();
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: "campuscare-form-saved",
            formType: "testing",
            message: "Testing request form saved. You can now continue to schedule and review.",
            previewUrl: <?php echo json_encode($lastSubmittedId > 0 ? "/campuscare-api/php-frontend/pages/forms/testing_request_preview.php?id=" . intval($lastSubmittedId) : ""); ?>,
            summary: <?php echo json_encode(testing_request_summary($formState), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        }, "*");
    }
    <?php endif; ?>

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

<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Refer a Student";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));

// Allow Students, Instructors, Facilitators, Counselors, Administrators to refer
$allowedRoles = ["Student", "Instructor", "Facilitator", "Administrator", "Counselor"];
if (!in_array($role, $allowedRoles, true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";

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

$formState = [
    "referral_type" => "internal", // internal or external
    "student_type" => "in_system", // in_system or not_in_system
    "student_user_id" => "",
    "student_name" => "",
    "student_email" => "",
    "course_year_section" => "",
    "reasons" => [],
    "other_reason" => "",
    "description" => "",
    "is_anonymous" => false,
];

// Ensure tables exist
$initError = "";
if (!campuscare_ensure_referral_forms_table($conn)) {
    $initError = "Unable to initialize referral form storage.";
}

// Only set error if this is not a form submission and init failed
if ($initError !== "" && $_SERVER["REQUEST_METHOD"] !== "POST") {
    $error = $initError;
}

// Get list of counselors
$counselors = [];
$counselorResult = $conn->query("SELECT id, full_name FROM users WHERE role IN ('Counselor', 'Counsellor') AND status = 'Active' ORDER BY full_name ASC");
while ($counselorResult && ($row = $counselorResult->fetch_assoc())) {
    $counselors[] = $row;
}

// Get list of active students
$students = [];
$studentResult = $conn->query("SELECT id, full_name, student_id FROM users WHERE role = 'Student' AND status = 'Active' ORDER BY full_name ASC");
while ($studentResult && ($row = $studentResult->fetch_assoc())) {
    $students[] = $row;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    error_log("DEBUG: Form submitted. POST data: " . json_encode($_POST));
    $formState["student_type"] = trim((string) ($_POST["student_type"] ?? "in_system"));
    $formState["reasons"] = $_POST["reasons"] ?? [];
    $formState["other_reason"] = trim((string) ($_POST["other_reason"] ?? ""));
    $formState["description"] = trim((string) ($_POST["description"] ?? ""));
    $formState["is_anonymous"] = isset($_POST["is_anonymous"]) && $_POST["is_anonymous"] === "1";

    // Normalize reasons
    $formState["reasons"] = is_array($formState["reasons"]) ? array_values(array_map("trim", $formState["reasons"])) : [];

    // Get student info based on type
    if ($formState["student_type"] === "in_system") {
        $formState["student_user_id"] = trim((string) ($_POST["student_user_id"] ?? ""));
        $studentId = intval($formState["student_user_id"]);
        $studentName = "";

        if ($studentId <= 0) {
            $error = "Please select a student from the system.";
        } else {
            // Find student name
            foreach ($students as $s) {
                if (intval($s["id"]) === $studentId) {
                    $studentName = trim((string) $s["full_name"]);
                    $formState["student_name"] = $studentName;
                    $formState["student_email"] = null;
                    break;
                }
            }

            if ($studentName === "") {
                $error = "Selected student not found.";
            }
        }
    } else {
        // External student
        $studentEmail = trim((string) ($_POST["student_email"] ?? ""));
        $studentName = trim((string) ($_POST["student_name"] ?? ""));

        if ($studentEmail === "") {
            $error = "Please provide the student's email address.";
        } elseif (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid email address.";
        } elseif ($studentName === "") {
            $error = "Please provide the student's full name.";
        } else {
            $formState["student_name"] = $studentName;
            $formState["student_email"] = $studentEmail;
            $formState["student_user_id"] = null;
        }
    }

    // Validate reason
    if ($error === "" && empty($formState["reasons"]) && $formState["other_reason"] === "") {
        $error = "Please select at least one reason for the referral or specify other concerns.";
    }

    // Build reason string
    $reasonsArray = $formState["reasons"];
    if ($formState["other_reason"] !== "") {
        $reasonsArray[] = "Other: " . $formState["other_reason"];
    }
    $reasonString = implode(", ", $reasonsArray);

    // Save referral
    if ($error === "") {
        $counselorId = intval($_POST["referred_to_counselor_id"] ?? 0);
        if ($counselorId <= 0) {
            $counselorId = null;
        }

        $isExternal = $formState["student_type"] === "not_in_system" ? 1 : 0;
        $studentUserId = intval($formState["student_user_id"]) ?: null;
        $studentEmail = $formState["student_email"] ?? null;

        $insert = $conn->prepare("
            INSERT INTO referral_forms (
                submitted_by_user_id,
                submitted_by_name,
                submitted_by_role,
                referred_to_counselor_id,
                referred_to_counselor_name,
                referral_datetime,
                student_user_id,
                student_name,
                student_email,
                course_year_section,
                is_external_student,
                description,
                reasons_json,
                other_reason,
                is_anonymous,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insert) {
            $error = "Unable to save referral: " . $conn->error;
        } else {
            $counselorName = "";
            if ($counselorId) {
                foreach ($counselors as $c) {
                    if (intval($c["id"]) === $counselorId) {
                        $counselorName = trim((string) $c["full_name"]);
                        break;
                    }
                }
            }

            $reasonsJson = json_encode($formState["reasons"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $refDateTime = date("Y-m-d H:i:s");
            $status = "Pending";

            $insert->bind_param(
                "issississsisssis",
                $userId,
                $fullName,
                $role,
                $counselorId,
                $counselorName,
                $refDateTime,
                $studentUserId,
                $formState["student_name"],
                $studentEmail,
                $formState["course_year_section"],
                $isExternal,
                $formState["description"],
                $reasonsJson,
                $formState["other_reason"],
                $formState["is_anonymous"],
                $status
            );

            if ($insert->execute()) {
                $referralId = $insert->insert_id;
                $insert->close();

                // Send email notification if external student
                $emailSent = false;
                if ($isExternal && $studentEmail) {
                    $emailSent = campuscare_send_referral_notification(
                        $studentEmail,
                        $formState["student_name"],
                        $fullName,
                        $reasonString
                    );

                    if ($emailSent) {
                        $updateEmail = $conn->prepare("UPDATE referral_forms SET email_notification_sent = TRUE, email_notification_date = NOW() WHERE id = ?");
                        if ($updateEmail) {
                            $updateEmail->bind_param("i", $referralId);
                            $updateEmail->execute();
                            $updateEmail->close();
                        }
                    }
                }

                $studentType = $isExternal ? "External" : "System";
                $success = "✓ Referral submitted successfully for <strong>" . htmlspecialchars($formState["student_name"]) . "</strong> (" . $studentType . ")";
                if ($isExternal && !$emailSent) {
                    $success .= "<br><strong>⚠️ Note:</strong> Email notification could not be sent to " . htmlspecialchars($studentEmail) . ". Please notify them manually.";
                } elseif ($isExternal && $emailSent) {
                    $success .= "<br>Email notification has been sent.";
                }
                $formState = [
                    "referral_type" => "internal",
                    "student_type" => "in_system",
                    "student_user_id" => "",
                    "student_name" => "",
                    "student_email" => "",
                    "course_year_section" => "",
                    "reasons" => [],
                    "other_reason" => "",
                    "description" => "",
                    "is_anonymous" => false,
                ];
            } else {
                $error = "Unable to save referral: " . $conn->error;
            }
        }
    }
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
            <div class="dashboard-head">
                <div>
                    <h1 class="page-title">Refer a Student</h1>
                    <p class="page-subtitle">Help us support students who may be struggling</p>
                </div>
            </div>
            <?php if ($error): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #c33; background: #fee; color: #c33; font-size: 13px;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; border-left: 3px solid #080; background: #efe; color: #080; font-size: 13px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <style>
                .booking-head {
                    padding: 1.75rem 1.75rem 1.6rem;
                    background: linear-gradient(135deg, var(--primary) 0%, #5f99ca 100%);
                    border-radius: 22px 22px 0 0;
                    padding: 30px 34px 20px;
                    background: var(--primary);
                    color: #fff;
                    border-radius: 22px;
                    margin-top: 15px;
                    margin-bottom: 1rem;
                    box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
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

                .referral-form {
                    margin-top: 20px;
                    background: #fff;
                    padding: 35px 35px;
                    border-radius: 10px;
                    border: 1px solid #e0e0e0;
                }

                body.theme-dark .referral-form {
                    background: #121d2b;
                    border-color: #2b3b4f;
                }

                .choice-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                    margin-bottom: 16px;
                }

                .choice-input {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }

                .choice-btn {
                
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 42px;
                    padding: 10px 12px;
                    border-radius: 16px;
                    border: 1px solid #cdd7e1;
                    background: #fff;
                    color: #0066cc;
                    font-size: 13px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-align: center;
                }

                body.theme-dark .choice-btn {
                    background: #162534;
                    border-color: #2f4458;
                    color: #9dc6ee;
                }

                .choice-btn:hover {
                    background: #f0f7ff;
                    border-color: #0066cc;
                }

                body.theme-dark .choice-btn:hover {
                    background: #1a3248;
                    border-color: #68a8dc;
                    color: #68a8dc;
                }

                .choice-input:checked + .choice-btn {
                    background: #0066cc;
                    border-color: #0066cc;
                    color: #fff;
                }

                body.theme-dark .choice-input:checked + .choice-btn {
                    background: #68a8dc;
                    border-color: #68a8dc;
                    color: #0e1622;
                }

                .student-type-grid .choice-btn {
                    background: #ffffff;
                    border-color: #e0c36b;
                    color: var(--gold-text, #e0c36b);
                    
                }

                body.theme-dark .student-type-grid .choice-btn {
                    background: #1a1a0f;
                    border-color: #5a4a1f;
                    color: #f0cb74;
                }

                .student-type-grid .choice-btn:hover {
                    border-color: #e0c36b;
                    background: var(--gold-soft, #c5bdab);
                    color: var(--gold-text, #d29818);
                }

                body.theme-dark .student-type-grid .choice-btn:hover {
                    border-color: #f0cb74;
                    background: #2a2415;
                    color: #f0cb74;
                }

                .student-type-grid .choice-input:checked + .choice-btn {
                    background: #e0c36b;
                    border-color: #e0c36b;
                    color: #ffffff;
                }

                body.theme-dark .student-type-grid .choice-input:checked + .choice-btn {
                    background: #5a4a1f;
                    border-color: #f0cb74;
                    color: #f0cb74;
                }

                .reasons-grid {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 10px;
                    margin-bottom: 14px;
                }

                .reason-item {
                    position: relative;
                    min-height: 44px;
                }

                .reason-item .choice-btn {
                    width: 100%;
                    height: 100%;
                    border-radius: 10px;
                    justify-content: flex-start;
                    text-align: left;
                    line-height: 1.25;
                }

                .anonymous-wrap {
                    max-width: 280px;
                }

                @media (max-width: 900px) {
                    .reasons-grid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                }

                @media (max-width: 640px) {
                    .choice-grid,
                    #not-in-system-section {
                        grid-template-columns: 1fr !important;
                    }

                    .reasons-grid {
                        grid-template-columns: 1fr;
                    }

                    .anonymous-wrap {
                        max-width: 100%;
                    }
                }
            </style>

            <form method="POST" action="" class="referral-form" id="referral-form" onsubmit="validateForm(event)">
                
                <!-- Student Selection -->
                <fieldset style="border: none; padding: 0; margin: 0 0 20px 0;">
                    <legend style="display: block; margin-bottom: 12px; color: #333; font-weight: 600; font-size: 14px;">Who are you referring? *</legend>
                    
                    <div class="choice-grid student-type-grid">
                        <label>
                            <input class="choice-input" type="radio" name="student_type" value="in_system" <?php echo $formState["student_type"] === "in_system" ? "checked" : ""; ?> onchange="updateStudentType()">
                            <span class="choice-btn">In System</span>
                        </label>
                        <label>
                            <input class="choice-input" type="radio" name="student_type" value="not_in_system" <?php echo $formState["student_type"] === "not_in_system" ? "checked" : ""; ?> onchange="updateStudentType()">
                            <span class="choice-btn">External (Email)</span>
                        </label>
                    </div>

                    <!-- In System -->
                    <div id="in-system-section" style="display: <?php echo $formState["student_type"] === "in_system" ? "block" : "none"; ?>;">
                        <select id="student_user_id" name="student_user_id" style="width: 100%; padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                            <option value="">Select a student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo htmlspecialchars($student["id"]); ?>" <?php echo intval($formState["student_user_id"]) === intval($student["id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($student["full_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- External -->
                    <div id="not-in-system-section" style="display: <?php echo $formState["student_type"] === "not_in_system" ? "block" : "none"; ?>; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <input type="text" id="student_name" name="student_name" placeholder="Full name" value="<?php echo htmlspecialchars($formState["student_name"]); ?>" style="padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                        <input type="email" id="student_email" name="student_email" placeholder="Email address" value="<?php echo htmlspecialchars($formState["student_email"] ?? ""); ?>" style="padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                    </div>
                </fieldset>

                <!-- Referral Reasons -->
                <fieldset style="border: none; padding: 0; margin: 0 0 20px 0;">
                    <legend style="display: block; margin-bottom: 12px; color: #333; font-weight: 600; font-size: 14px;">Reasons for referral * (select all that apply)</legend>
                    
                    <div class="reasons-grid">
                        <?php foreach ($reasonOptions as $reason): ?>
                            <label class="reason-item">
                                <input class="choice-input" type="checkbox" name="reasons[]" value="<?php echo htmlspecialchars($reason); ?>" <?php echo in_array($reason, $formState["reasons"]) ? "checked" : ""; ?>>
                                <span class="choice-btn"><?php echo htmlspecialchars($reason); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <input type="text" id="other_reason" name="other_reason" placeholder="Other concerns (if not listed)" value="<?php echo htmlspecialchars($formState["other_reason"]); ?>" style="width: 100%; padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box;">
                </fieldset>

                <!-- Details -->
                <fieldset style="border: none; padding: 0; margin: 0 0 20px 0;">
                    <legend style="display: block; margin-bottom: 12px; color: #333; font-weight: 600; font-size: 14px;">Additional details</legend>
                    
                    <textarea id="description" name="description" placeholder="What observations or behaviors have you noticed?" style="width: 100%; padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; box-sizing: border-box; resize: vertical; min-height: 80px; font-family: inherit; margin-bottom: 12px;"><?php echo htmlspecialchars($formState["description"]); ?></textarea>

                    <label class="anonymous-wrap" style="display: block;">
                        <input class="choice-input" type="checkbox" id="is_anonymous" name="is_anonymous" value="1" <?php echo $formState["is_anonymous"] ? "checked" : ""; ?>>
                        <span class="choice-btn">Submit anonymously</span>
                    </label>
                </fieldset>

                <!-- Counselor Assignment -->
                <fieldset style="border: none; padding: 0; margin: 0 0 20px 0;">
                    <legend style="display: block; margin-bottom: 12px; color: #333; font-weight: 600; font-size: 14px;">Assign to counselor (optional)</legend>
                    
                    <select id="referred_to_counselor_id" name="referred_to_counselor_id" style="width: 100%; padding: 9px 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
                        <option value="">Auto-assign to available counselor</option>
                        <?php foreach ($counselors as $counselor): ?>
                            <option value="<?php echo htmlspecialchars($counselor["id"]); ?>">
                                <?php echo htmlspecialchars($counselor["full_name"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>

                <!-- Actions -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn event-join-btn">Submit Referral</button>
                    <button type="button" class="btn event-join-btn btn-outline" onclick="history.back();">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    function validateForm(event) {
        const form = document.getElementById('referral-form');
        const studentType = document.querySelector('input[name="student_type"]:checked')?.value || 'in_system';
        const reasons = document.querySelectorAll('input[name="reasons[]"]:checked');
        const otherReason = document.getElementById('other_reason').value.trim();
        
        let errorMsg = [];
        
        if (studentType === 'in_system') {
            const studentId = document.getElementById('student_user_id').value.trim();
            if (!studentId) {
                errorMsg.push('Please select a student from the system.');
            }
        } else {
            const studentName = document.getElementById('student_name').value.trim();
            const studentEmail = document.getElementById('student_email').value.trim();
            if (!studentName) {
                errorMsg.push('Please provide the student\'s full name.');
            }
            if (!studentEmail) {
                errorMsg.push('Please provide the student\'s email address.');
            } else if (!studentEmail.includes('@')) {
                errorMsg.push('Please provide a valid email address.');
            }
        }
        
        if (reasons.length === 0 && !otherReason) {
            errorMsg.push('Please select at least one reason for the referral or specify other concerns.');
        }
        
        if (errorMsg.length > 0) {
            event.preventDefault();
            alert('Please fix the following issues:\n\n' + errorMsg.join('\n'));
            return false;
        }
        
        console.log('Form validation passed, submitting...');
        return true;
    }

    function updateStudentType() {
        const checkedStudentType = document.querySelector('input[name="student_type"]:checked');
        const studentType = checkedStudentType ? checkedStudentType.value : 'in_system';
        const inSystemSection = document.getElementById('in-system-section');
        const notInSystemSection = document.getElementById('not-in-system-section');
        const studentDropdown = document.getElementById('student_user_id');
        const studentNameInput = document.getElementById('student_name');
        const studentEmailInput = document.getElementById('student_email');

        if (studentType === 'in_system') {
            inSystemSection.style.display = 'block';
            notInSystemSection.style.display = 'none';
            studentDropdown.required = true;
            studentNameInput.required = false;
            studentEmailInput.required = false;
        } else {
            inSystemSection.style.display = 'none';
            notInSystemSection.style.display = 'grid';
            studentDropdown.required = false;
            studentNameInput.required = true;
            studentEmailInput.required = true;
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', updateStudentType);
</script>


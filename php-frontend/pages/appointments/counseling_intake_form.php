<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Counseling Intake Form";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

if ($role !== "Student") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$lastSubmittedAt = "";

function intake_value(array $state, string $key, string $default = ""): string
{
    return trim((string) ($state[$key] ?? $default));
}

function intake_table_ready(mysqli $conn): bool
{
    $createSql = "
        CREATE TABLE IF NOT EXISTS counseling_intake_forms (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(120) NOT NULL,
            cell_phone VARCHAR(60) NOT NULL,
            course_year VARCHAR(120) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            agreement_accepted TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_counseling_intake_user (user_id),
            KEY idx_counseling_intake_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    return $conn->query($createSql) !== false;
}

$medicalOptions = [
    "None",
    "Anemia",
    "Anxiety",
    "Asthma",
    "Blood Clots",
    "Cerebrovascular Accident",
    "Depression",
    "Hypertension",
    "Thyroid Disease",
    "Allergies",
    "Arthritis",
    "Cancer",
    "Diabetes",
    "Migraine Headaches",
    "Peptic Ulcer Disease",
    "Seizure Disorder",
];

$useChoices = ["No", "Daily", "Weekly", "Less", "Former User"];
$yesNoChoices = ["Yes", "No"];

$formState = [
    "client_first_name" => "",
    "client_last_name" => "",
    "course_year" => "",
    "intake_mode" => "",
    "referred_by" => "",
    "address_street" => "",
    "address_city" => "",
    "address_state" => "",
    "address_postal" => "",
    "date_of_birth" => "",
    "marital_status" => "",
    "email" => trim((string) ($_SESSION["email"] ?? "")),
    "cell_phone" => "",
    "religious_affiliation" => "",
    "messenger_username" => "",
    "emergency_contact_name" => "",
    "emergency_contact_relationship" => "",
    "emergency_contact_number" => "",
    "medical_history" => [],
    "medical_history_other" => "",
    "family_medical_history" => "",
    "tobacco_use" => "",
    "alcohol_use" => "",
    "caffeine_use" => "",
    "drug_use" => "",
    "takes_prescription_medication" => "",
    "prescription_details" => "",
    "surgeries_past_5_years" => "",
    "surgeries_details" => "",
    "initial_visit_reason" => "",
    "session_expectation" => "",
    "average_sleep_hours" => "",
    "seen_mental_health_professional" => "",
    "seen_professional_reason" => "",
    "additional_comments" => "",
    "agreement_accepted" => "",
    "agreement_signature_client" => "",
    "agreement_signature_client_drawn" => "",
    "agreement_client_date" => "",
    "agreement_signature_counselor" => "",
    "agreement_signature_counselor_drawn" => "",
    "agreement_counselor_date" => "",
];

if (!intake_table_ready($conn)) {
    $error = "Unable to initialize intake form storage. Please contact the administrator.";
} else {
    $latestStmt = $conn->prepare("SELECT payload_json, created_at FROM counseling_intake_forms WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");

    if ($latestStmt) {
        $latestStmt->bind_param("i", $userId);
        $latestStmt->execute();
        $latestRow = $latestStmt->get_result()->fetch_assoc();
        $latestStmt->close();

        if (is_array($latestRow)) {
            $payload = json_decode((string) ($latestRow["payload_json"] ?? ""), true);

            if (is_array($payload)) {
                foreach ($formState as $key => $value) {
                    if (array_key_exists($key, $payload)) {
                        $formState[$key] = $payload[$key];
                    }
                }
            }

            $lastSubmittedAtRaw = trim((string) ($latestRow["created_at"] ?? ""));
            if ($lastSubmittedAtRaw !== "") {
                $lastSubmittedAt = date("F j, Y g:i A", strtotime($lastSubmittedAtRaw));
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    foreach ($formState as $key => $value) {
        if ($key === "medical_history") {
            $selected = $_POST["medical_history"] ?? [];
            $formState[$key] = is_array($selected) ? array_values(array_map("trim", $selected)) : [];
            continue;
        }

        if ($key === "agreement_accepted") {
            $formState[$key] = isset($_POST["agreement_accepted"]) ? "Yes" : "";
            continue;
        }

        $formState[$key] = trim((string) ($_POST[$key] ?? ""));
    }

    $fullName = trim($formState["client_first_name"] . " " . $formState["client_last_name"]);

    if ($formState["client_first_name"] === "" || $formState["client_last_name"] === "") {
        $error = "Please provide the client's first name and last name.";
    } elseif ($formState["course_year"] === "") {
        $error = "Please enter your course and year.";
    } elseif ($formState["email"] === "" || !filter_var($formState["email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($formState["cell_phone"] === "") {
        $error = "Please enter your cell phone number.";
    } elseif ($formState["initial_visit_reason"] === "") {
        $error = "Please describe the reason for your initial visit.";
    } elseif ($formState["session_expectation"] === "") {
        $error = "Please describe what you expect from this session.";
    } elseif ($formState["agreement_accepted"] !== "Yes") {
        $error = "You must accept the counseling confidentiality agreement before submitting.";
    } elseif (
        $formState["agreement_signature_client"] === "" &&
        $formState["agreement_signature_client_drawn"] === ""
    ) {
        $error = "Please provide your client signature by typing your full name or drawing your signature.";
    } elseif ($formState["agreement_client_date"] === "") {
        $error = "Please provide the date for your agreement signature.";
    } else {
        $payloadJson = json_encode($formState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payloadJson === false) {
            $error = "Unable to encode intake form data.";
        } else {
            $insertStmt = $conn->prepare("INSERT INTO counseling_intake_forms (user_id, full_name, email, cell_phone, course_year, payload_json, agreement_accepted) VALUES (?, ?, ?, ?, ?, ?, 1)");

            if (!$insertStmt) {
                $error = "Unable to save intake form right now.";
            } else {
                $insertStmt->bind_param(
                    "isssss",
                    $userId,
                    $fullName,
                    $formState["email"],
                    $formState["cell_phone"],
                    $formState["course_year"],
                    $payloadJson
                );

                if ($insertStmt->execute()) {
                    $success = "Counseling intake form submitted successfully. You can now book a Counseling appointment online.";
                    $lastSubmittedAt = date("F j, Y g:i A");
                } else {
                    $error = "Failed to submit counseling intake form.";
                }

                $insertStmt->close();
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
        <div class="page-shell" style="max-width: 980px;">
            <h1 class="page-title">Counseling Intake Form</h1>
            <p class="page-subtitle">Student Welfare and Engagement Unit - Guidance and Counseling Services</p>

            <?php if ($lastSubmittedAt !== ""): ?>
                <p class="page-subtitle" style="margin-bottom: 14px;">Last submitted: <strong><?php echo htmlspecialchars($lastSubmittedAt); ?></strong></p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" class="card">
                <style>
                .intake-form-shell {
                    
                }

                .intake-section {
                    border: 1px solid var(--border);
                    border-radius: 16px;
                    padding: 16px;
                    background: var(--card-bg);
                    margin-bottom: 14px;
                }

                .intake-choice-row {
                    display: flex;
                    gap: 14px;
                    flex-wrap: wrap;
                }

                .intake-choice-row label {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 14px;
                    padding: 8px 10px;
                    border: 1px solid var(--border);
                    border-radius: 999px;
                    background: var(--page-bg);
                }

                .signature-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 14px;
                    margin-bottom: 14px;
                }

                .signature-card {
                    border: 1px solid var(--border);
                    border-radius: 14px;
                    padding: 12px;
                    background: var(--card-bg);
                }

                .signature-pad {
                    width: 100%;
                    height: 170px;
                    border: 1px dashed #8ba3bb;
                    border-radius: 10px;
                    background: #ffffff;
                    touch-action: none;
                    cursor: crosshair;
                    display: block;
                }

                .signature-actions {
                    margin-top: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 8px;
                }

                .signature-hint {
                    font-size: 12px;
                    color: var(--text-muted);
                }

                .intake-form-shell input[type="checkbox"],
                .intake-form-shell input[type="radio"] {
                    width: 16px;
                    height: 16px;
                    min-width: 16px;
                    padding: 0;
                    margin: 0;
                    border-radius: 4px;
                    border: 1px solid #9fb4c8;
                    box-shadow: none;
                    accent-color: #a64ccf;
                }

                .intake-form-shell input[type="checkbox"]:focus,
                .intake-form-shell input[type="radio"]:focus {
                    box-shadow: 0 0 0 2px rgba(166, 76, 207, 0.2);
                }

                .medical-option {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    margin: 0;
                    font-weight: 600;
                    font-size: 14px;
                    min-height: 28px;
                }

                .agreement-check {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 14px;
                    font-weight: 600;
                }

                .agreement-check input[type="checkbox"] {
                    margin-top: 0;
                }

                body.theme-dark .signature-pad {
                    background: #0f1b29;
                    border-color: #4f6881;
                }

                body.theme-dark .signature-card {
                    background: #132132;
                }

                @media (max-width: 760px) {
                    .intake-two-col {
                        grid-template-columns: 1fr !important;
                    }

                    .signature-grid {
                        grid-template-columns: 1fr;
                    }
                }
                </style>
                <div class="intake-form-shell">
                <h2 class="card-title" style="margin-bottom: 14px;">1. Client Information</h2>

                <div class="intake-section grid intake-two-col" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px;">
                    <div class="form-group">
                        <label for="client_first_name">First name</label>
                        <input id="client_first_name" name="client_first_name" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "client_first_name")); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="client_last_name">Last name</label>
                        <input id="client_last_name" name="client_last_name" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "client_last_name")); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="course_year">Course and Year</label>
                        <input id="course_year" name="course_year" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "course_year")); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Intake Type</label>
                        <div class="intake-choice-row" style="margin-top: 8px;">
                            <label><input type="radio" name="intake_mode" value="Walk-In" <?php echo intake_value($formState, "intake_mode") === "Walk-In" ? "checked" : ""; ?>> Walk-In</label>
                            <label><input type="radio" name="intake_mode" value="Referred" <?php echo intake_value($formState, "intake_mode") === "Referred" ? "checked" : ""; ?>> Referred</label>
                        </div>
                        <input style="margin-top: 8px;" name="referred_by" type="text" placeholder="If referred, who referred you?" value="<?php echo htmlspecialchars(intake_value($formState, "referred_by")); ?>">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="address_street">Street Address</label>
                        <input id="address_street" name="address_street" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "address_street")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address_city">City</label>
                        <input id="address_city" name="address_city" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "address_city")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address_state">State / Province</label>
                        <input id="address_state" name="address_state" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "address_state")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address_postal">Postal / Zipcode</label>
                        <input id="address_postal" name="address_postal" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "address_postal")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input id="date_of_birth" name="date_of_birth" type="date" value="<?php echo htmlspecialchars(intake_value($formState, "date_of_birth")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="marital_status">Marital Status</label>
                        <input id="marital_status" name="marital_status" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "marital_status")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars(intake_value($formState, "email")); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="cell_phone">Cell Phone Number</label>
                        <input id="cell_phone" name="cell_phone" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "cell_phone")); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="religious_affiliation">Religious Affiliation</label>
                        <input id="religious_affiliation" name="religious_affiliation" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "religious_affiliation")); ?>">
                    </div>

                    <div class="form-group">
                        <label for="messenger_username">Username on Facebook / Messenger</label>
                        <input id="messenger_username" name="messenger_username" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "messenger_username")); ?>">
                    </div>
                </div>

                <h2 class="card-title" style="margin: 18px 0 14px;">2. Emergency Contact Information</h2>
                <div class="intake-section grid intake-two-col" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px;">
                    <div class="form-group">
                        <label for="emergency_contact_name">Name of Contact</label>
                        <input id="emergency_contact_name" name="emergency_contact_name" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "emergency_contact_name")); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_relationship">Relationship to the Client</label>
                        <input id="emergency_contact_relationship" name="emergency_contact_relationship" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "emergency_contact_relationship")); ?>">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_number">Contact Number</label>
                        <input id="emergency_contact_number" name="emergency_contact_number" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "emergency_contact_number")); ?>">
                    </div>
                </div>

                <h2 class="card-title" style="margin: 18px 0 14px;">3. Medical History</h2>
                <p class="page-subtitle" style="margin-bottom:10px;">Please check all that apply:</p>
                <div class="intake-section" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 14px; margin-bottom: 10px;">
                    <?php foreach ($medicalOptions as $option): ?>
                        <?php $selectedMedical = is_array($formState["medical_history"]) && in_array($option, $formState["medical_history"], true); ?>
                        <label class="medical-option"><input type="checkbox" name="medical_history[]" value="<?php echo htmlspecialchars($option); ?>" <?php echo $selectedMedical ? "checked" : ""; ?>> <?php echo htmlspecialchars($option); ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="form-group">
                    <label for="medical_history_other">Other (please specify)</label>
                    <input id="medical_history_other" name="medical_history_other" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "medical_history_other")); ?>">
                </div>

                <h2 class="card-title" style="margin: 18px 0 14px;">4. Family and Lifestyle Information</h2>
                <div class="intake-section">
                <div class="form-group">
                    <label for="family_medical_history">Family Medical History</label>
                    <textarea id="family_medical_history" name="family_medical_history" rows="3"><?php echo htmlspecialchars(intake_value($formState, "family_medical_history")); ?></textarea>
                </div>

                <?php
                $usageFields = [
                    "tobacco_use" => "Do you use tobacco, cigarettes, or vape?",
                    "alcohol_use" => "Do you use alcohol?",
                    "caffeine_use" => "Do you use caffeine?",
                    "drug_use" => "Do you use drugs?",
                ];
                ?>

                <?php foreach ($usageFields as $field => $label): ?>
                    <div style="margin-bottom: 12px;">
                        <p style="font-weight:600; margin-bottom:6px;"><?php echo htmlspecialchars($label); ?></p>
                        <div class="intake-choice-row">
                            <?php foreach ($useChoices as $choice): ?>
                                <label>
                                    <input type="radio" name="<?php echo htmlspecialchars($field); ?>" value="<?php echo htmlspecialchars($choice); ?>" <?php echo intake_value($formState, $field) === $choice ? "checked" : ""; ?>>
                                    <?php echo htmlspecialchars($choice); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-bottom: 12px;">
                    <p style="font-weight:600; margin-bottom:6px;">Are you currently taking prescription medication?</p>
                    <div class="intake-choice-row">
                        <?php foreach ($yesNoChoices as $choice): ?>
                            <label>
                                <input type="radio" name="takes_prescription_medication" value="<?php echo htmlspecialchars($choice); ?>" <?php echo intake_value($formState, "takes_prescription_medication") === $choice ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($choice); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input style="margin-top:8px;" name="prescription_details" type="text" placeholder="If yes, please specify prescribed medications" value="<?php echo htmlspecialchars(intake_value($formState, "prescription_details")); ?>">
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="font-weight:600; margin-bottom:6px;">Have you had any surgeries in the past 5 years?</p>
                    <div class="intake-choice-row">
                        <?php foreach ($yesNoChoices as $choice): ?>
                            <label>
                                <input type="radio" name="surgeries_past_5_years" value="<?php echo htmlspecialchars($choice); ?>" <?php echo intake_value($formState, "surgeries_past_5_years") === $choice ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($choice); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input style="margin-top:8px;" name="surgeries_details" type="text" placeholder="Please specify surgery details if any" value="<?php echo htmlspecialchars(intake_value($formState, "surgeries_details")); ?>">
                </div>
                </div>

                <h2 class="card-title" style="margin: 18px 0 14px;">5. Mental Health Information</h2>
                <div class="intake-section">
                <div class="form-group">
                    <label for="initial_visit_reason">Describe the reason for the initial visit</label>
                    <textarea id="initial_visit_reason" name="initial_visit_reason" rows="3" required><?php echo htmlspecialchars(intake_value($formState, "initial_visit_reason")); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="session_expectation">What do you expect from this session?</label>
                    <textarea id="session_expectation" name="session_expectation" rows="3" required><?php echo htmlspecialchars(intake_value($formState, "session_expectation")); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="average_sleep_hours">Average hours of sleep per night</label>
                    <input id="average_sleep_hours" name="average_sleep_hours" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "average_sleep_hours")); ?>">
                </div>

                <div style="margin-bottom: 12px;">
                    <p style="font-weight:600; margin-bottom:6px;">Have you seen a counselor, psychiatrist, or other mental health professional before?</p>
                    <div class="intake-choice-row">
                        <?php foreach ($yesNoChoices as $choice): ?>
                            <label>
                                <input type="radio" name="seen_mental_health_professional" value="<?php echo htmlspecialchars($choice); ?>" <?php echo intake_value($formState, "seen_mental_health_professional") === $choice ? "checked" : ""; ?>>
                                <?php echo htmlspecialchars($choice); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input style="margin-top:8px;" name="seen_professional_reason" type="text" placeholder="If yes, reason" value="<?php echo htmlspecialchars(intake_value($formState, "seen_professional_reason")); ?>">
                </div>

                <div class="form-group">
                    <label for="additional_comments">Additional comments or concerns</label>
                    <textarea id="additional_comments" name="additional_comments" rows="3"><?php echo htmlspecialchars(intake_value($formState, "additional_comments")); ?></textarea>
                </div>
                </div>

                <h2 class="card-title" style="margin: 18px 0 12px;">6. Counseling Confidentiality Agreement</h2>
                <div class="card" style="background: var(--page-bg); border:1px solid var(--border); padding:14px; margin-bottom:14px;">
                    <p style="margin-bottom:8px;"><strong>Purpose:</strong> This agreement protects the privacy of the client and counselor relationship.</p>
                    <p style="margin-bottom:8px;"><strong>Extent of Confidentiality:</strong> Counseling communications and records are treated with confidentiality.</p>
                    <p style="margin-bottom:8px;"><strong>Limits:</strong> Disclosure may happen when there is risk of harm, legal requirements, or applicable safeguarding situations.</p>
                    <p style="margin-bottom:8px;"><strong>Client Rights:</strong> You may request a summary of your records and understand how your information is protected.</p>
                    <p style="margin-bottom:0;"><strong>Agreement:</strong> By signing below, you confirm your understanding and consent.</p>
                </div>

                <label class="agreement-check">
                    <input type="checkbox" name="agreement_accepted" <?php echo intake_value($formState, "agreement_accepted") === "Yes" ? "checked" : ""; ?>>
                    <span>I have read and agree to the Counseling Confidentiality Agreement.</span>
                </label>

                <div class="signature-grid">
                    <div class="signature-card">
                        <label for="agreement_signature_client">Client Signature (type full name)</label>
                        <input id="agreement_signature_client" name="agreement_signature_client" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_signature_client")); ?>" placeholder="Type your full legal name">
                        <p class="signature-hint" style="margin-top:8px;">Or draw your signature below:</p>
                        <canvas id="clientSignaturePad" class="signature-pad"></canvas>
                        <input id="agreement_signature_client_drawn" name="agreement_signature_client_drawn" type="hidden" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_signature_client_drawn")); ?>">
                        <div class="signature-actions">
                            <span class="signature-hint">Use mouse, touch, or stylus.</span>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn-outline" id="undoClientSignature">Undo</button>
                                <button type="button" class="btn-outline" id="clearClientSignature">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="signature-card">
                        <label for="agreement_signature_counselor">Counselor Name (optional)</label>
                        <input id="agreement_signature_counselor" name="agreement_signature_counselor" type="text" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_signature_counselor")); ?>" placeholder="Counselor typed name">
                        <p class="signature-hint" style="margin-top:8px;">Optional counselor signature:</p>
                        <canvas id="counselorSignaturePad" class="signature-pad"></canvas>
                        <input id="agreement_signature_counselor_drawn" name="agreement_signature_counselor_drawn" type="hidden" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_signature_counselor_drawn")); ?>">
                        <div class="signature-actions">
                            <span class="signature-hint">Optional field.</span>
                            <div style="display:flex; gap:8px;">
                                <button type="button" class="btn-outline" id="undoCounselorSignature">Undo</button>
                                <button type="button" class="btn-outline" id="clearCounselorSignature">Clear</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid intake-two-col" style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 10px;">
                    <div class="form-group">
                        <label for="agreement_client_date">Client Date</label>
                        <input id="agreement_client_date" name="agreement_client_date" type="date" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_client_date")); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="agreement_counselor_date">Counselor Date (optional)</label>
                        <input id="agreement_counselor_date" name="agreement_counselor_date" type="date" value="<?php echo htmlspecialchars(intake_value($formState, "agreement_counselor_date")); ?>">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top: 14px; flex-wrap: wrap;">
                    <button type="submit" class="btn">Submit Intake Form</button>
                    <a href="/campuscare-api/php-frontend/pages/appointments/counseling_intake_preview.php" class="btn-outline" target="_blank" rel="noopener">Preview Printable Form</a>
                    <a href="/campuscare-api/php-frontend/pages/appointments/book_appointment.php?service=counseling" class="btn-outline">Proceed to Book Counseling</a>
                </div>
                </div>
            </form>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    function setupSignaturePad(canvasId, hiddenInputId, clearButtonId, undoButtonId) {
        const canvas = document.getElementById(canvasId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const clearButton = document.getElementById(clearButtonId);
        const undoButton = document.getElementById(undoButtonId);

        if (!canvas || !hiddenInput || !clearButton || !undoButton) {
            return;
        }

        const ctx = canvas.getContext("2d");
        let drawing = false;
        let hasStroke = false;
        let history = [];

        function pushHistory(snapshot) {
            if (history.length === 0 || history[history.length - 1] !== snapshot) {
                history.push(snapshot);
            }

            if (history.length > 50) {
                history.shift();
            }
        }

        function restoreSnapshot(snapshot) {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const displayWidth = canvas.clientWidth;
            const displayHeight = canvas.clientHeight;

            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (snapshot === "") {
                hiddenInput.value = "";
                hasStroke = false;
                return;
            }

            const image = new Image();
            image.onload = function () {
                ctx.drawImage(image, 0, 0, displayWidth, displayHeight);
            };
            image.src = snapshot;
            hiddenInput.value = snapshot;
            hasStroke = true;
        }

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const displayWidth = canvas.clientWidth;
            const displayHeight = canvas.clientHeight;

            const snapshot = hiddenInput.value;

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
            const rect = canvas.getBoundingClientRect();

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
            drawing = true;
            const p = pointFromEvent(event);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            event.preventDefault();
        }

        function draw(event) {
            if (!drawing) {
                return;
            }

            const p = pointFromEvent(event);
            ctx.lineTo(p.x, p.y);
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

    setupSignaturePad("clientSignaturePad", "agreement_signature_client_drawn", "clearClientSignature", "undoClientSignature");
    setupSignaturePad("counselorSignaturePad", "agreement_signature_counselor_drawn", "clearCounselorSignature", "undoCounselorSignature");

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

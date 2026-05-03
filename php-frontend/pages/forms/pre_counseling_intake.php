<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/forms.php";

$pageTitle = "Pre-Counseling Intake";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));

// This is for students to fill when arriving at counselor
if ($role !== "Student") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

campuscare_ensure_referral_forms_table($conn);
campuscare_ensure_referral_intake_forms_table($conn);

$error = "";
$success = "";
$referralId = intval($_GET["referral_id"] ?? 0);
$intakeId = intval($_GET["intake_id"] ?? 0);

$referral = null;
$intake = null;

// Get referral details
if ($referralId > 0) {
    $stmt = $conn->prepare("SELECT id, student_user_id, student_name, student_email, reasons_json FROM referral_forms WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $referralId);
        $stmt->execute();
        $referral = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Get existing intake if editing
if ($intakeId > 0) {
    $stmt = $conn->prepare("SELECT * FROM referral_intake_forms WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $intakeId);
        $stmt->execute();
        $intake = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$intake) {
            $error = "Intake form not found.";
        }
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $refId = intval($_POST["referral_id"] ?? $referralId);
    $whyVisiting = trim((string) ($_POST["why_visiting"] ?? ""));
    $whatConcerns = trim((string) ($_POST["what_concerns"] ?? ""));
    $howLong = trim((string) ($_POST["how_long"] ?? ""));
    $previousCounseling = isset($_POST["previous_counseling"]) && $_POST["previous_counseling"] === "1";
    $emergencyContact = trim((string) ($_POST["emergency_contact"] ?? ""));

    if ($whyVisiting === "") {
        $error = "Please tell us why you're visiting.";
    } elseif ($whatConcerns === "") {
        $error = "Please describe your main concerns.";
    } else {
        if ($intakeId > 0) {
            // Update existing intake
            $update = $conn->prepare("
                UPDATE referral_intake_forms SET
                    why_visiting = ?,
                    what_concerns = ?,
                    how_long = ?,
                    previous_counseling = ?,
                    emergency_contact = ?,
                    completed_by_student = TRUE
                WHERE id = ? LIMIT 1
            ");

            if (!$update) {
                $error = "Unable to update intake form: " . $conn->error;
            } else {
                $update->bind_param("sssisi", $whyVisiting, $whatConcerns, $howLong, $previousCounseling, $emergencyContact, $intakeId);

                if ($update->execute()) {
                    $success = "✓ Your intake form has been updated successfully!";
                    $update->close();
                } else {
                    $error = "Unable to save changes: " . $conn->error;
                }
            }
        } else {
            // Create new intake
            if ($refId <= 0) {
                $error = "No referral associated with this intake. Please contact the counseling office.";
            } else {
                $insert = $conn->prepare("
                    INSERT INTO referral_intake_forms (
                        referral_id,
                        student_user_id,
                        student_name,
                        student_email,
                        intake_datetime,
                        why_visiting,
                        what_concerns,
                        how_long,
                        previous_counseling,
                        emergency_contact,
                        completed_by_student,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$insert) {
                    $error = "Unable to save intake form: " . $conn->error;
                } else {
                    $now = date("Y-m-d H:i:s");
                    $studentUserIdVal = ($role === "Student") ? $userId : null;
                    $studentNameVal = $fullName;
                    $studentEmailVal = $_SESSION["email"] ?? null;
                    $completed = true;
                    $status = "Pending";

                    $insert->bind_param(
                        "iissssssisi",
                        $refId,
                        $studentUserIdVal,
                        $studentNameVal,
                        $studentEmailVal,
                        $now,
                        $whyVisiting,
                        $whatConcerns,
                        $howLong,
                        $previousCounseling,
                        $emergencyContact,
                        $completed,
                        $status
                    );

                    if ($insert->execute()) {
                        $newIntakeId = $insert->insert_id;
                        $insert->close();

                        // Update referral to link to this intake
                        $linkUpdate = $conn->prepare("UPDATE referral_forms SET intake_form_id = ? WHERE id = ?");
                        if ($linkUpdate) {
                            $linkUpdate->bind_param("ii", $newIntakeId, $refId);
                            $linkUpdate->execute();
                            $linkUpdate->close();
                        }

                        $success = "✓ Your intake form has been submitted successfully! The counselor will review it shortly.";
                        // Reset form
                    } else {
                        $error = "Unable to save intake form: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Prepare form data for display
$formData = [
    "why_visiting" => $intake["why_visiting"] ?? "",
    "what_concerns" => $intake["what_concerns"] ?? "",
    "how_long" => $intake["how_long"] ?? "",
    "previous_counseling" => $intake["previous_counseling"] ?? false,
    "emergency_contact" => $intake["emergency_contact"] ?? "",
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - CampusCare</title>
    <link rel="stylesheet" href="/campuscare-api/php-frontend/assets/style.css">
    <style>
        .intake-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .intake-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 20px;
        }

        .intake-header h1 {
            color: #0066cc;
            font-size: 28px;
            margin: 0 0 10px 0;
        }

        .intake-header p {
            color: #666;
            margin: 0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fee;
            border-color: #c33;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            border-color: #3c3;
            color: #3c3;
        }

        .info-box {
            background: #e6f2ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .referral-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }

        .referral-info h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .referral-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #0066cc;
            font-size: 16px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
            accent-color: #0066cc;
        }

        .checkbox-item label {
            flex: 1;
            cursor: pointer;
            margin: 0;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #0066cc;
            color: white;
        }

        .btn-primary:hover {
            background: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
        }

        .btn-secondary {
            background: #ddd;
            color: #333;
        }

        .btn-secondary:hover {
            background: #ccc;
        }

        .required-note {
            font-size: 13px;
            color: #999;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . "/../../includes/header.php"; ?>

    <div class="page-container">
        <?php include __DIR__ . "/../../includes/sidebar.php"; ?>

        <main class="main-content">
            <div class="intake-container">
                <div class="intake-header">
                    <h1>📋 Pre-Counseling Intake Form</h1>
                    <p>Please answer the following questions to help us understand your situation better.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($referral): ?>
                    <div class="referral-info">
                        <h4>📌 Referral Information</h4>
                        <p><strong>Referred by:</strong> <?php echo htmlspecialchars($referral["student_name"] ?? "Staff Member"); ?></p>
                        <?php if (is_string($referral["reasons_json"])): ?>
                            <p><strong>Reasons:</strong> <?php echo htmlspecialchars(implode(", ", json_decode($referral["reasons_json"], true) ?: [])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    💡 <strong>What to expect:</strong> Your responses will help the counselor prepare for your session. Everything you share here is confidential.
                </div>

                <form method="POST">
                    <?php if ($referralId > 0): ?>
                        <input type="hidden" name="referral_id" value="<?php echo htmlspecialchars($referralId); ?>">
                    <?php endif; ?>
                    <?php if ($intakeId > 0): ?>
                        <input type="hidden" name="intake_id" value="<?php echo htmlspecialchars($intakeId); ?>">
                    <?php endif; ?>

                    <!-- Why Visiting -->
                    <div class="form-section">
                        <h3>1. Why Are You Visiting Today?</h3>
                        <div class="form-group">
                            <label for="why_visiting">Tell us briefly what brought you to the counseling office. *</label>
                            <textarea id="why_visiting" name="why_visiting" required placeholder="e.g., I was referred by a staff member, I'm struggling with my studies, I need someone to talk to..."><?php echo htmlspecialchars($formData["why_visiting"]); ?></textarea>
                        </div>
                    </div>

                    <!-- Main Concerns -->
                    <div class="form-section">
                        <h3>2. What Are Your Main Concerns?</h3>
                        <div class="form-group">
                            <label for="what_concerns">Describe the specific concerns or challenges you're facing. *</label>
                            <textarea id="what_concerns" name="what_concerns" required placeholder="Share as much detail as you're comfortable with..."><?php echo htmlspecialchars($formData["what_concerns"]); ?></textarea>
                        </div>
                    </div>

                    <!-- Duration -->
                    <div class="form-section">
                        <h3>3. How Long Has This Been Going On?</h3>
                        <div class="form-group">
                            <label for="how_long">When did you first notice this concern?</label>
                            <input type="text" id="how_long" name="how_long" value="<?php echo htmlspecialchars($formData["how_long"]); ?>" placeholder="e.g., For about 2 weeks, Since last semester, Recently started...">
                        </div>
                    </div>

                    <!-- Previous Counseling -->
                    <div class="form-section">
                        <h3>4. Previous Counseling Experience</h3>
                        <div class="checkbox-item">
                            <input type="checkbox" id="previous_counseling" name="previous_counseling" value="1" <?php echo $formData["previous_counseling"] ? "checked" : ""; ?>>
                            <label for="previous_counseling">I have worked with a counselor or mental health professional before</label>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="form-section">
                        <h3>5. Emergency Contact</h3>
                        <div class="form-group">
                            <label for="emergency_contact">If needed, who should we contact in case of emergency?</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" value="<?php echo htmlspecialchars($formData["emergency_contact"]); ?>" placeholder="Name and phone number">
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Submit Intake Form</button>
                        <button type="button" class="btn btn-secondary" onclick="history.back();">Cancel</button>
                    </div>

                    <p class="required-note">* Required fields</p>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

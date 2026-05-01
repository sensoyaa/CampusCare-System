<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/google_oauth.php";
require_once __DIR__ . "/../../../backend/config/mail.php";

$error = "";
$success = "";
$step = "register";
$frontendBaseUrl = "/campuscare-api/php-frontend";
$projectBaseUrl = "/campuscare-api";
$full_name = "";
$email = "";
$student_id = "";
$role = "Student";
$isGoogleSignup = isset($_SESSION["pending_google_signup"]) && is_array($_SESSION["pending_google_signup"]);
$googleOauthConfig = campuscare_google_oauth_config();
$googleOauthEnabled = (bool) ($googleOauthConfig["is_configured"] ?? false);

function ensureRegistrationVerificationTable(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS registration_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            student_id VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_registration_verifications_email (email),
            INDEX idx_registration_verifications_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    return $conn->query($sql) === true;
}

function dbColumnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function getColumnName(mysqli $conn, string $table, array $choices, string $default): string
{
    foreach ($choices as $column) {
        if (dbColumnExists($conn, $table, $column)) {
            return $column;
        }
    }

    return $default;
}

function resolveRoleId(mysqli $conn, string $role): ?int
{
    $roleKey = strtolower(trim($role));
    $stmt = $conn->prepare("SELECT id FROM roles WHERE LOWER(code) = ? OR LOWER(display_name) = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ss", $roleKey, $roleKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? intval($row["id"]) : null;
}

function resolveAccountStatusId(mysqli $conn, string $status): ?int
{
    $statusKey = strtolower(trim($status));
    $stmt = $conn->prepare("SELECT id FROM account_statuses WHERE LOWER(code) = ? OR LOWER(display_name) = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ss", $statusKey, $statusKey);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? intval($row["id"]) : null;
}

function clearRegistrationVerificationSession(): void
{
    unset(
        $_SESSION["registration_verification_email"],
        $_SESSION["registration_verification_requested_at"]
    );
}

if (($_GET["fresh"] ?? "") === "1") {
    clearRegistrationVerificationSession();
}

$requestedAt = intval($_SESSION["registration_verification_requested_at"] ?? 0);
if ($requestedAt > 0 && (time() - $requestedAt) > 1800) {
    clearRegistrationVerificationSession();
}

$step = isset($_SESSION["registration_verification_email"]) ? "verify" : "register";

function findActiveRegistrationVerificationByEmail(mysqli $conn, string $email): ?array
{
    $studentColumn = getColumnName($conn, "registration_verifications", ["student_id", "student_number"], "student_id");
    $roleColumn = getColumnName($conn, "registration_verifications", ["role", "role_id"], "role");

    $stmt = $conn->prepare(
        "SELECT id, full_name, " . $studentColumn . " AS student_id, email, password_hash, " . $roleColumn . " AS role, token_hash, expires_at
        FROM registration_verifications
        WHERE email = ? AND verified_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function sendRegistrationVerificationEmail(string $email, string $recipientName, string $verificationCode): array
{
    $subject = "CampusCare Email Verification Code";
    $htmlBody = campuscare_email_template(
        "Verify Your Email",
        "Use the verification code below to activate your CampusCare account.",
        '
        <p style="margin:0 0 16px;">Hello ' . htmlspecialchars($recipientName, ENT_QUOTES, "UTF-8") . ',</p>
        <p style="margin:0 0 24px;">Thanks for registering with CampusCare. Enter this verification code to complete your account setup:</p>
        <div style="margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2; text-align:center;">
            <div style="font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:12px;">Verification Code</div>
            <div style="font-size:34px; font-weight:700; letter-spacing:10px; color:#214d70;">' . htmlspecialchars($verificationCode, ENT_QUOTES, "UTF-8") . '</div>
        </div>
        <div style="margin:0 0 24px; padding:18px 20px; border-radius:18px; background:#fff8eb; border:1px solid #f2dfb2; color:#6b5221;">
            This code will expire in <strong>15 minutes</strong>.
        </div>
        <p style="margin:0;">If you did not create this account, you can ignore this message.</p>
        ',
        [
            "preview" => "Your CampusCare verification code is {$verificationCode}",
            "footer" => "For your security, never share this verification code with anyone."
        ]
    );
    $textBody = "Hello {$recipientName},\n\nThanks for registering with CampusCare.\n\nYour verification code is: {$verificationCode}\n\nThis code will expire in 15 minutes.\n\nIf you did not create this account, you can ignore this message.";

    return send_smtp_mail($email, $recipientName, $subject, $htmlBody, $textBody);
}

if (isset($_SESSION["oauth_error"])) {
    $error = trim((string) $_SESSION["oauth_error"]);
    unset($_SESSION["oauth_error"]);
}

if ($isGoogleSignup) {
    $pendingGoogleSignup = $_SESSION["pending_google_signup"];
    $full_name = trim((string) ($pendingGoogleSignup["full_name"] ?? ""));
    $email = trim((string) ($pendingGoogleSignup["email"] ?? ""));
    $role = "Student";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "register"));

    if ($action === "start_over") {
        clearRegistrationVerificationSession();
        $step = "register";
        $error = "";
        $success = "You can now register with a different email.";
        $email = "";
        $full_name = "";
        $student_id = "";
    } elseif ($action === "google_signup") {
        $pendingGoogleSignup = $_SESSION["pending_google_signup"] ?? null;
        $student_id = trim($_POST["student_id"] ?? "");

        if (!is_array($pendingGoogleSignup)) {
            $error = "Your Google sign-up session expired. Please try again.";
        } elseif ($student_id === "") {
            $error = "Student ID is required.";
        } else {
            $full_name = trim((string) ($pendingGoogleSignup["full_name"] ?? ""));
            $email = trim((string) ($pendingGoogleSignup["email"] ?? ""));
            $role = "Student";
            $status = "Active";
            $placeholderPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);

            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

            if (!$checkEmail) {
                $error = "Unable to validate Google account email.";
            } else {
                $checkEmail->bind_param("s", $email);
                $checkEmail->execute();
                $emailExists = $checkEmail->get_result()->num_rows > 0;
                $checkEmail->close();

                if ($emailExists) {
                    $error = "An account with this Google email already exists. Please log in instead.";
                    unset($_SESSION["pending_google_signup"]);
                } else {
                    $userStudentColumn = getColumnName($conn, "users", ["student_id", "student_number"], "student_id");
                $userPasswordColumn = getColumnName($conn, "users", ["password_hash", "password"], "password");
                $userRoleColumn = getColumnName($conn, "users", ["role", "role_id"], "role");
                $userStatusColumn = getColumnName($conn, "users", ["status", "account_status_id"], "status");

                $checkStudent = $conn->prepare("SELECT id FROM users WHERE " . $userStudentColumn . " = ? LIMIT 1");

                    if (!$checkStudent) {
                        $error = "Unable to validate student ID.";
                    } else {
                        $checkStudent->bind_param("s", $student_id);
                        $checkStudent->execute();
                        $studentIdExists = $checkStudent->get_result()->num_rows > 0;
                        $checkStudent->close();

                        if ($studentIdExists) {
                            $error = "Student ID is already in use.";
                        } else {
                            $roleValue = $userRoleColumn === "role_id"
                                ? (resolveRoleId($conn, $role) ?? 4)
                                : $role;
                            $statusValue = $userStatusColumn === "account_status_id"
                                ? (resolveAccountStatusId($conn, $status) ?? 1)
                                : $status;
                            $passwordColumn = $userPasswordColumn;

                            $stmt = $conn->prepare(
                                "INSERT INTO users (full_name, " . $userStudentColumn . ", email, " . $passwordColumn . ", " . $userRoleColumn . ", " . $userStatusColumn . ") VALUES (?, ?, ?, ?, ?, ?)"
                            );

                            if (!$stmt) {
                                $error = "Failed to create account.";
                            } else {
                                $bindTypes = "ssss" . ($userRoleColumn === "role_id" ? "i" : "s") . ($userStatusColumn === "account_status_id" ? "i" : "s");
                                $stmt->bind_param($bindTypes, $full_name, $student_id, $email, $placeholderPassword, $roleValue, $statusValue);

                                if ($stmt->execute()) {
                                    unset($_SESSION["pending_google_signup"]);
                                    $success = "Google account linked successfully. You can now log in.";
                                    $full_name = "";
                                    $email = "";
                                    $student_id = "";
                                    $role = "Student";
                                    $isGoogleSignup = false;
                                } else {
                                    $error = intval($stmt->errno) === 1062
                                        ? "Student ID or email is already in use."
                                        : "Failed to create account.";
                                }

                                $stmt->close();
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === "verify_email") {
        $verificationEmail = trim((string) ($_POST["email"] ?? ($_SESSION["registration_verification_email"] ?? "")));
        $verificationCode = trim((string) ($_POST["verification_code"] ?? ""));

        if ($verificationEmail === "" || !filter_var($verificationEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $step = "register";
        } elseif ($verificationCode === "") {
            $error = "Verification code is required.";
            $step = "verify";
            $email = $verificationEmail;
        } else {
            $verificationRequest = findActiveRegistrationVerificationByEmail($conn, $verificationEmail);
            $expiresAt = $verificationRequest ? strtotime((string) ($verificationRequest["expires_at"] ?? "")) : false;

            if (!$verificationRequest || $expiresAt === false || $expiresAt < time() || !password_verify($verificationCode, (string) $verificationRequest["token_hash"])) {
                $error = "The verification code is invalid or has expired.";
                $step = "verify";
                $email = $verificationEmail;
            } else {
                $userStudentColumn = getColumnName($conn, "users", ["student_id", "student_number"], "student_id");
                $userPasswordColumn = getColumnName($conn, "users", ["password_hash", "password"], "password");
                $userRoleColumn = getColumnName($conn, "users", ["role", "role_id"], "role");
                $userStatusColumn = getColumnName($conn, "users", ["status", "account_status_id"], "status");

                $insertStmt = $conn->prepare(
                    "INSERT INTO users (full_name, " . $userStudentColumn . ", email, " . $userPasswordColumn . ", " . $userRoleColumn . ", " . $userStatusColumn . ") VALUES (?, ?, ?, ?, ?, ?)"
                );

                if (!$insertStmt) {
                    $error = "Failed to create account.";
                    $step = "verify";
                    $email = $verificationEmail;
                } else {
                    $fullName = (string) $verificationRequest["full_name"];
                    $studentId = (string) $verificationRequest["student_id"];
                    $storedEmail = (string) $verificationRequest["email"];
                    $passwordHash = (string) $verificationRequest["password_hash"];
                    $userRole = $userRoleColumn === "role_id"
                        ? (intval((string) $verificationRequest["role"]) ?: (resolveRoleId($conn, "Student") ?? 4))
                        : (string) $verificationRequest["role"];
                    $status = $userStatusColumn === "account_status_id"
                        ? (resolveAccountStatusId($conn, "Active") ?? 1)
                        : "Active";

                    $bindTypes = "ssss" . ($userRoleColumn === "role_id" ? "i" : "s") . ($userStatusColumn === "account_status_id" ? "i" : "s");
                    $insertStmt->bind_param($bindTypes, $fullName, $studentId, $storedEmail, $passwordHash, $userRole, $status);

                    if ($insertStmt->execute()) {
                        $verificationId = intval($verificationRequest["id"]);
                        $markStmt = $conn->prepare("UPDATE registration_verifications SET verified_at = NOW() WHERE id = ?");

                        if ($markStmt) {
                            $markStmt->bind_param("i", $verificationId);
                            $markStmt->execute();
                            $markStmt->close();
                        }

                        clearRegistrationVerificationSession();
                        $success = "Email verified successfully. Your account is now active, and you can log in.";
                        $full_name = "";
                        $email = "";
                        $student_id = "";
                        $role = "Student";
                        $step = "register";
                    } else {
                        $error = intval($insertStmt->errno) === 1062
                            ? "Email or student ID is already in use."
                            : "Failed to create account.";
                        $step = "verify";
                        $email = $verificationEmail;
                    }

                    $insertStmt->close();
                }
            }
        }
    } elseif ($action === "resend_code") {
        $verificationEmail = trim((string) ($_POST["email"] ?? ($_SESSION["registration_verification_email"] ?? "")));

        if ($verificationEmail === "" || !filter_var($verificationEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $step = "register";
        } else {
            $verificationRequest = findActiveRegistrationVerificationByEmail($conn, $verificationEmail);

            if (!$verificationRequest) {
                $error = "No pending verification was found for this email. Please register again.";
                $step = "register";
            } else {
                $verificationCode = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);
                $codeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
                $expiresAt = date("Y-m-d H:i:s", time() + 900);
                $verificationId = intval($verificationRequest["id"]);

                $updateStmt = $conn->prepare("
                    UPDATE registration_verifications
                    SET token_hash = ?, expires_at = ?, verified_at = NULL
                    WHERE id = ?
                ");

                if (!$updateStmt) {
                    $error = "Unable to resend verification code.";
                    $step = "verify";
                    $email = $verificationEmail;
                } else {
                    $updateStmt->bind_param("ssi", $codeHash, $expiresAt, $verificationId);

                    if ($updateStmt->execute()) {
                        $mailResult = sendRegistrationVerificationEmail(
                            $verificationEmail,
                            (string) $verificationRequest["full_name"],
                            $verificationCode
                        );

                        if ($mailResult["success"]) {
                            $_SESSION["registration_verification_email"] = $verificationEmail;
                            $_SESSION["registration_verification_requested_at"] = time();
                            $success = "A new verification code has been sent to your email.";
                            $step = "verify";
                            $email = $verificationEmail;
                        } else {
                            $error = "Verification code could not be sent. " . $mailResult["message"];
                            $step = "verify";
                            $email = $verificationEmail;
                        }
                    } else {
                        $error = "Unable to resend verification code.";
                        $step = "verify";
                        $email = $verificationEmail;
                    }

                    $updateStmt->close();
                }
            }
        }
    } else {
        $full_name = trim($_POST["full_name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $student_id = trim($_POST["student_id"] ?? "");
        $role = trim($_POST["role"] ?? "Student");
        $password = $_POST["password"] ?? "";
        $confirm_password = $_POST["confirm_password"] ?? "";

        if ($full_name === "" || $email === "" || $student_id === "" || $role === "" || $password === "" || $confirm_password === "") {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Password confirmation does not match.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param("s", $email);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                $error = "Email must be unique. Duplicate email is not allowed.";
            } elseif (!ensureRegistrationVerificationTable($conn)) {
                $error = "Unable to prepare email verification requests.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $verificationCode = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);
                $codeHash = password_hash($verificationCode, PASSWORD_DEFAULT);
                $expiresAt = date("Y-m-d H:i:s", time() + 900);

                $deleteStmt = $conn->prepare("DELETE FROM registration_verifications WHERE email = ?");

                if ($deleteStmt) {
                    $deleteStmt->bind_param("s", $email);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }

                $verificationStudentColumn = getColumnName($conn, "registration_verifications", ["student_id", "student_number"], "student_id");
                $verificationRoleColumn = getColumnName($conn, "registration_verifications", ["role", "role_id"], "role");
                $roleValue = $verificationRoleColumn === "role_id"
                    ? (resolveRoleId($conn, $role) ?? 4)
                    : $role;

                $stmt = $conn->prepare(
                    "INSERT INTO registration_verifications (full_name, " . $verificationStudentColumn . ", email, password_hash, " . $verificationRoleColumn . ", token_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $error = "Failed to create verification request.";
                } else {
                    $bindTypes = "ssss" . ($verificationRoleColumn === "role_id" ? "iss" : "sss");
                    $stmt->bind_param($bindTypes, $full_name, $student_id, $email, $hashedPassword, $roleValue, $codeHash, $expiresAt);

                    if ($stmt->execute()) {
                        $mailResult = sendRegistrationVerificationEmail($email, $full_name, $verificationCode);

                        if ($mailResult["success"]) {
                            clearRegistrationVerificationSession();
                            $_SESSION["registration_verification_email"] = $email;
                            $_SESSION["registration_verification_requested_at"] = time();
                            $success = "A verification code has been sent to your email. Enter it below to activate your account.";
                            $step = "verify";
                        } else {
                            $cleanupStmt = $conn->prepare("DELETE FROM registration_verifications WHERE email = ?");

                            if ($cleanupStmt) {
                                $cleanupStmt->bind_param("s", $email);
                                $cleanupStmt->execute();
                                $cleanupStmt->close();
                            }

                            $error = "Verification code could not be sent. " . $mailResult["message"];
                        }
                    } else {
                        $error = intval($stmt->errno) === 1062
                            ? "Email must be unique. Duplicate email is not allowed."
                            : "Failed to create verification request.";
                    }

                    $stmt->close();
                }
            }

            $check->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | CampusCare</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($frontendBaseUrl); ?>/assets/style.css">
</head>
<body class="login-page">
<div class="form-page login-form-page">
    <div class="form-left login-hero">
        <img
            class="login-hero-image"
            src="<?php echo htmlspecialchars($projectBaseUrl); ?>/php-frontend/assets/images/Signin_cover.png"
            alt="CampusCare registration cover"
            loading="eager"
            decoding="async"
        >
        <div class="login-hero-content">
            <h1>Get Started</h1>
            <p>Join our community and begin your wellness journey today.</p>
        </div>
    </div>

    <div class="form-right login-panel">
        <div class="form-box login-box">
            <h2>Create Account</h2>
            <p>Join CampusCare</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($isGoogleSignup): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="google_signup">

                    <div class="form-group">
                        <label>Google Account</label>
                        <input type="text" value="<?php echo htmlspecialchars($full_name); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" required>
                    </div>

                    <button type="submit" class="btn" style="width:100%;">Complete Google Sign Up</button>
                </form>
            <?php elseif ($step === "verify"): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_email">

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Verification Code</label>
                        <input type="text" name="verification_code" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit code" required>
                    </div>

                    <button type="submit" class="btn" style="width:100%;">Verify Email</button>
                </form>

                <form method="POST" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="resend_code">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" class="btn-outline" style="width:100%;">Resend Code</button>
                </form>

                <form method="POST" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="start_over">
                    <button type="submit" class="btn-outline" style="width:100%;">Use Different Email</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>ID</label>
                        <input type="text" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="Student" <?php echo $role === "Student" ? "selected" : ""; ?>>Student</option>
                            <option value="Counselor" <?php echo $role === "Counselor" ? "selected" : ""; ?>>Counselor</option>
                            <option value="Facilitator" <?php echo $role === "Facilitator" ? "selected" : ""; ?>>Facilitator</option>
                            <option value="Instructor" <?php echo $role === "Instructor" ? "selected" : ""; ?>>Instructor</option>
                            <option value="Administrator" <?php echo $role === "Administrator" ? "selected" : ""; ?>>Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-field">
                            <input id="registerPassword" type="password" name="password" required>
                            <button type="button" class="password-toggle" data-target="registerPassword" aria-label="Show password" aria-pressed="false">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="M12 5c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8C21.4 14.2 17.8 19 12 19S2.6 14.2 1.4 12.5a.8.8 0 0 1 0-.8C2.6 9.8 6.2 5 12 5Zm0 2c-4.4 0-7.3 3.4-8.6 5 1.3 1.6 4.2 5 8.6 5s7.3-3.4 8.6-5c-1.3-1.6-4.2-5-8.6-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"></path>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A11.8 11.8 0 0 1 12 20c-5.8 0-9.4-4.8-10.6-6.7a.8.8 0 0 1 0-.8A19 19 0 0 1 6.4 7L2 2.6 3.3 2Zm4.6 6L6 6.1A16.8 16.8 0 0 0 3.4 12c1.3 1.6 4.2 6 8.6 6 1.5 0 2.8-.4 4-1l-1.8-1.8a4.8 4.8 0 0 1-6.3-6.3Zm4.2-3c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8a19 19 0 0 1-3.5 4.2l-1.4-1.4a16.8 16.8 0 0 0 2.8-3.2c-1.3-1.6-4.2-5-8.6-5h-.5L10 5.4c.6-.3 1.3-.4 2-.4Z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="password-field">
                            <input id="confirmRegisterPassword" type="password" name="confirm_password" required>
                            <button type="button" class="password-toggle" data-target="confirmRegisterPassword" aria-label="Show password" aria-pressed="false">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="M12 5c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8C21.4 14.2 17.8 19 12 19S2.6 14.2 1.4 12.5a.8.8 0 0 1 0-.8C2.6 9.8 6.2 5 12 5Zm0 2c-4.4 0-7.3 3.4-8.6 5 1.3 1.6 4.2 5 8.6 5s7.3-3.4 8.6-5c-1.3-1.6-4.2-5-8.6-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"></path>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A11.8 11.8 0 0 1 12 20c-5.8 0-9.4-4.8-10.6-6.7a.8.8 0 0 1 0-.8A19 19 0 0 1 6.4 7L2 2.6 3.3 2Zm4.6 6L6 6.1A16.8 16.8 0 0 0 3.4 12c1.3 1.6 4.2 6 8.6 6 1.5 0 2.8-.4 4-1l-1.8-1.8a4.8 4.8 0 0 1-6.3-6.3Zm4.2-3c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8a19 19 0 0 1-3.5 4.2l-1.4-1.4a16.8 16.8 0 0 0 2.8-3.2c-1.3-1.6-4.2-5-8.6-5h-.5L10 5.4c.6-.3 1.3-.4 2-.4Z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width:100%; margin-top:20px";>Register</button>
                </form>
            <?php endif; ?>

            <div class="oauth-divider"><span>or</span></div>

            <?php if ($googleOauthEnabled): ?>
                <a href="google-login.php?mode=signup" class="google-login-btn" aria-label="Sign up with Google">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-2 3.1l3.2 2.5c1.9-1.8 3-4.5 3-7.7 0-.7-.1-1.3-.2-1.9H12z"></path>
                        <path fill="#34A853" d="M12 22c2.7 0 5-0.9 6.7-2.4l-3.2-2.5c-.9.6-2 .9-3.5.9-2.7 0-5-1.8-5.8-4.3l-3.3 2.6C4.7 19.7 8.1 22 12 22z"></path>
                        <path fill="#4A90E2" d="M6.2 13.7c-.2-.6-.3-1.1-.3-1.7s.1-1.2.3-1.7L2.9 7.7C2.3 8.9 2 10.4 2 12s.3 3.1.9 4.3l3.3-2.6z"></path>
                        <path fill="#FBBC05" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9C17 2.9 14.7 2 12 2 8.1 2 4.7 4.3 2.9 7.7l3.3 2.6c.8-2.5 3.1-4.4 5.8-4.4z"></path>
                    </svg>
                    <?php echo $isGoogleSignup ? "Continue with Google" : "Sign up with Google"; ?>
                </a>
            <?php else: ?>
                <p class="oauth-help">Google sign-in is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in backend/.env.</p>
            <?php endif; ?>

            <p style="margin-top:20px;">
                Already have an account?
                <a href="index.php" class="small-link">Login</a>
            </p>
        </div>
    </div>
</div>
<script>
(function () {
    var toggles = document.querySelectorAll(".password-toggle");

    toggles.forEach(function (toggleButton) {
        var targetId = toggleButton.getAttribute("data-target");
        var targetInput = targetId ? document.getElementById(targetId) : null;

        if (!targetInput) {
            return;
        }

        toggleButton.addEventListener("click", function () {
            var isVisible = targetInput.getAttribute("type") === "text";
            targetInput.setAttribute("type", isVisible ? "password" : "text");
            toggleButton.classList.toggle("is-visible", !isVisible);
            toggleButton.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
            toggleButton.setAttribute("aria-pressed", isVisible ? "false" : "true");
            targetInput.focus();
        });
    });
})();
</script>
</body>
</html>

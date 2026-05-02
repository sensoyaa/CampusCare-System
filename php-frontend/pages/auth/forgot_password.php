<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../../backend/config/mail.php";

$error = "";
$success = "";
$email = trim((string) ($_POST["email"] ?? ($_GET["email"] ?? ($_SESSION["password_reset_email"] ?? ""))));
$step = "request";
$frontendBaseUrl = "/campuscare-api/php-frontend";
$projectBaseUrl = "/campuscare-api";

function ensurePasswordResetTable(mysqli $conn): bool
{
    $sql = "
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_resets_email (email),
            INDEX idx_password_resets_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    return $conn->query($sql) === true;
}

function clearPasswordResetSession(): void
{
    unset(
        $_SESSION["password_reset_request_id"],
        $_SESSION["password_reset_user_id"],
        $_SESSION["password_reset_email"],
        $_SESSION["password_reset_verified_at"]
    );
}

function findActivePasswordResetByEmail(mysqli $conn, string $email): ?array
{
    $stmt = $conn->prepare("
        SELECT id, user_id, email, token_hash, expires_at
        FROM password_resets
        WHERE email = ? AND used_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ");

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

if (isset($_SESSION["password_reset_email"]) && !isset($_SESSION["password_reset_verified_at"])) {
    $step = "verify";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "request_code"));

    if (!ensurePasswordResetTable($conn)) {
        $error = "Unable to prepare password reset requests.";
    } elseif ($action === "request_code") {
        clearPasswordResetSession();

        if ($email === "") {
            $error = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $lookupStmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");

            if (!$lookupStmt) {
                $error = "Unable to process password reset request.";
            } else {
                $lookupStmt->bind_param("s", $email);
                $lookupStmt->execute();
                $user = $lookupStmt->get_result()->fetch_assoc();
                $lookupStmt->close();

                $success = "If that email exists in our system, a verification code has been sent.";
                $step = "verify";

                if ($user) {
                    $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");

                    if ($deleteStmt) {
                        $deleteStmt->bind_param("s", $email);
                        $deleteStmt->execute();
                        $deleteStmt->close();
                    }

                    $resetCode = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);
                    $codeHash = password_hash($resetCode, PASSWORD_DEFAULT);
                    $expiresAt = date("Y-m-d H:i:s", time() + 900);
                    $userId = intval($user["id"]);

                    $insertStmt = $conn->prepare("
                        INSERT INTO password_resets (user_id, email, token_hash, expires_at)
                        VALUES (?, ?, ?, ?)
                    ");

                    if (!$insertStmt) {
                        $error = "Unable to create password reset request.";
                        $success = "";
                        $step = "request";
                    } else {
                        $insertStmt->bind_param("isss", $userId, $email, $codeHash, $expiresAt);

                        if (!$insertStmt->execute()) {
                            $error = "Unable to save password reset request.";
                            $success = "";
                            $step = "request";
                        } else {
                            $recipientName = trim((string) ($user["full_name"] ?? "Student"));
                            $subject = "CampusCare Password Reset Code";
                            $htmlBody = campuscare_email_template(
                                "Password Reset Code",
                                "Use the verification code below to continue resetting your CampusCare password.",
                                "
                                <p style=\"margin:0 0 16px;\">Hello " . htmlspecialchars($recipientName, ENT_QUOTES, "UTF-8") . ",</p>
                                <p style=\"margin:0 0 24px;\">We received a request to reset your CampusCare password. Enter this code in the app to continue:</p>
                                <div style=\"margin:0 0 24px; padding:22px; border-radius:20px; background:#f4f9fc; border:1px solid #d5e7f2; text-align:center;\">
                                    <div style=\"font-size:13px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#5d7a91; margin-bottom:12px;\">Verification Code</div>
                                    <div style=\"font-size:34px; font-weight:700; letter-spacing:10px; color:#214d70;\">" . htmlspecialchars($resetCode, ENT_QUOTES, "UTF-8") . "</div>
                                </div>
                                <div style=\"margin:0 0 24px; padding:18px 20px; border-radius:18px; background:#fff8eb; border:1px solid #f2dfb2; color:#6b5221;\">
                                    This code will expire in <strong>15 minutes</strong>.
                                </div>
                                <p style=\"margin:0;\">If you did not request this, you can safely ignore this email.</p>
                                ",
                                [
                                    "preview" => "Your CampusCare verification code is {$resetCode}",
                                    "footer" => "For your security, never share this verification code with anyone."
                                ]
                            );
                            $textBody = "Hello {$recipientName},\n\nWe received a request to reset your CampusCare password.\n\nYour verification code is: {$resetCode}\n\nThis code will expire in 15 minutes.\n\nIf you did not request this, you can safely ignore this email.";
                            $mailResult = send_smtp_mail($email, $recipientName, $subject, $htmlBody, $textBody);

                            if (!$mailResult["success"]) {
                                $cleanupStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");

                                if ($cleanupStmt) {
                                    $cleanupStmt->bind_param("s", $email);
                                    $cleanupStmt->execute();
                                    $cleanupStmt->close();
                                }

                                $error = "Password reset code could not be sent. " . $mailResult["message"];
                                $success = "";
                                $step = "request";
                            } else {
                                $_SESSION["password_reset_email"] = $email;
                            }
                        }

                        $insertStmt->close();
                    }
                }
            }
        }
    } elseif ($action === "verify_code") {
        $code = trim((string) ($_POST["reset_code"] ?? ""));

        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
            $step = "request";
        } elseif ($code === "") {
            $error = "Verification code is required.";
            $step = "verify";
        } else {
            $resetRequest = findActivePasswordResetByEmail($conn, $email);
            $expiresAt = $resetRequest ? strtotime((string) ($resetRequest["expires_at"] ?? "")) : false;

            if (!$resetRequest || $expiresAt === false || $expiresAt < time() || !password_verify($code, $resetRequest["token_hash"])) {
                $error = "The verification code is invalid or has expired.";
                $step = "verify";
            } else {
                $_SESSION["password_reset_request_id"] = intval($resetRequest["id"]);
                $_SESSION["password_reset_user_id"] = intval($resetRequest["user_id"]);
                $_SESSION["password_reset_email"] = $email;
                $_SESSION["password_reset_verified_at"] = time();

                header("Location: reset_password.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | CampusCare</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($projectBaseUrl); ?>/php-frontend/assets/images/logo.png">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($frontendBaseUrl); ?>/assets/style.css">
</head>
<body class="login-page">
<div class="form-page login-form-page">
    <div class="form-left login-hero">
        <img
            class="login-hero-image"
            src="<?php echo htmlspecialchars($projectBaseUrl); ?>/php-frontend/assets/images/forgot_cover.png"
            alt="CampusCare password reset cover"
            loading="eager"
            decoding="async"
        >
        <div class="login-hero-content">
            <h1>CampusCare</h1>
            <p>Your university mental health and wellness companion</p>
        </div>
    </div>

    <div class="form-right">
        <div class="form-box-forgot">
            <div class="login-brand-row">
                <p> Campus<span class="brand-second-c">C</span>are</p>
                <img
                    class="login-heartbeat"
                    src="<?php echo htmlspecialchars($projectBaseUrl); ?>/php-frontend/assets/images/Heartbeat.png"
                    alt="Heartbeat icon"
                    loading="eager"
                    decoding="async"
                >
            </div>
            <h2>Forgot Password</h2>
            <p>
                <?php echo $step === "verify" ? "Enter the 6-digit code we sent to your email" : "Enter your email to receive a reset code"; ?>
            </p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($step === "verify"): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_code">

                    <div class="form-group" style="margin: 10px 0;">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group" style="margin: 10px 0;">
                        <label>Verification Code</label>
                        <input type="text" name="reset_code" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="Enter 6-digit code" required>
                    </div>

                    <button type="submit" class="btn" style="width:100%;">Verify Code</button>
                </form>

                <form method="POST" style="margin-top: 12px;">
                    <input type="hidden" name="action" value="request_code">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" class="btn-outline" style="width:100%;">Resend Code</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request_code">

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <button type="submit" class="btn" style="width:100%;">Send Code</button>
                </form>
            <?php endif; ?>

            <p style="margin-top:20px;">
                Remembered your password?
                <a href="index.php" class="small-link">Back to Login</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>

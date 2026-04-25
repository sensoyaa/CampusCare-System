<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";

$error = "";
$success = "";
$resetRequestId = intval($_SESSION["password_reset_request_id"] ?? 0);
$resetUserId = intval($_SESSION["password_reset_user_id"] ?? 0);
$verifiedAt = intval($_SESSION["password_reset_verified_at"] ?? 0);
$frontendBaseUrl = "/campuscare-api/php-frontend";
$projectBaseUrl = "/campuscare-api";

function clearPasswordResetSession(): void
{
    unset(
        $_SESSION["password_reset_request_id"],
        $_SESSION["password_reset_user_id"],
        $_SESSION["password_reset_email"],
        $_SESSION["password_reset_verified_at"]
    );
}

function getVerifiedPasswordResetRequest(mysqli $conn, int $requestId, int $userId): ?array
{
    if ($requestId <= 0 || $userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, user_id, email, expires_at
        FROM password_resets
        WHERE id = ? AND user_id = ? AND used_at IS NULL
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ii", $requestId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

$resetRequest = getVerifiedPasswordResetRequest($conn, $resetRequestId, $resetUserId);
$resetExpiresAt = $resetRequest ? strtotime((string) ($resetRequest["expires_at"] ?? "")) : false;

if ($verifiedAt <= 0 || (time() - $verifiedAt) > 900 || !$resetRequest || $resetExpiresAt === false || $resetExpiresAt < time()) {
    clearPasswordResetSession();
    $error = "Your password reset session is invalid or has expired.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $newPassword = (string) ($_POST["new_password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

    if ($error !== "") {
        // Keep the session-expired message.
    } elseif ($newPassword === "" || $confirmPassword === "") {
        $error = "All fields are required.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Password confirmation does not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $userId = intval($resetRequest["user_id"]);
        $resetId = intval($resetRequest["id"]);

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

        if (!$updateStmt) {
            $error = "Unable to update password.";
        } else {
            $updateStmt->bind_param("si", $hashedPassword, $userId);

            if ($updateStmt->execute()) {
                $markStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");

                if ($markStmt) {
                    $markStmt->bind_param("i", $resetId);
                    $markStmt->execute();
                    $markStmt->close();
                }

                clearPasswordResetSession();
                header("Location: index.php?reset=success");
                exit();
            } else {
                $error = "Failed to reset password.";
            }

            $updateStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | CampusCare</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($frontendBaseUrl); ?>/assets/style.css">
</head>
<body>
<div class="form-page">
    <div class="form-left">
        <div>
            <img src="<?php echo htmlspecialchars($projectBaseUrl); ?>/images/logo.png" alt="CampusCare">
            <h1>CampusCare</h1>
            <p>Create a new password to continue using your account</p>
        </div>
    </div>

    <div class="form-right">
        <div class="form-box">
            <h2>Reset Password</h2>
            <p>Choose a new secure password</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($success === "" && $error === ""): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="password-field">
                            <input id="newPassword" type="password" name="new_password" minlength="8" required>
                            <button type="button" class="password-toggle" data-target="newPassword" aria-label="Show password" aria-pressed="false">
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
                            <input id="confirmPassword" type="password" name="confirm_password" minlength="8" required>
                            <button type="button" class="password-toggle" data-target="confirmPassword" aria-label="Show password" aria-pressed="false">
                                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="M12 5c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8C21.4 14.2 17.8 19 12 19S2.6 14.2 1.4 12.5a.8.8 0 0 1 0-.8C2.6 9.8 6.2 5 12 5Zm0 2c-4.4 0-7.3 3.4-8.6 5 1.3 1.6 4.2 5 8.6 5s7.3-3.4 8.6-5c-1.3-1.6-4.2-5-8.6-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"></path>
                                </svg>
                                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path fill="currentColor" d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A11.8 11.8 0 0 1 12 20c-5.8 0-9.4-4.8-10.6-6.7a.8.8 0 0 1 0-.8A19 19 0 0 1 6.4 7L2 2.6 3.3 2Zm4.6 6L6 6.1A16.8 16.8 0 0 0 3.4 12c1.3 1.6 4.2 6 8.6 6 1.5 0 2.8-.4 4-1l-1.8-1.8a4.8 4.8 0 0 1-6.3-6.3Zm4.2-3c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8a19 19 0 0 1-3.5 4.2l-1.4-1.4a16.8 16.8 0 0 0 2.8-3.2c-1.3-1.6-4.2-5-8.6-5h-.5L10 5.4c.6-.3 1.3-.4 2-.4Z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width:100%;">Reset Password</button>
                </form>
            <?php endif; ?>

            <p style="margin-top:20px;">
                <a href="<?php echo $success === "" ? 'forgot_password.php' : 'index.php'; ?>" class="small-link">
                    <?php echo $success === "" ? 'Back to Forgot Password' : 'Back to Login'; ?>
                </a>
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

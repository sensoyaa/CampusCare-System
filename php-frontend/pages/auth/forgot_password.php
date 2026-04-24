<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";

$error = "";
$success = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim((string) ($_POST["email"] ?? ""));
    $newPassword = (string) ($_POST["new_password"] ?? "");
    $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

    if ($email === "" || $newPassword === "" || $confirmPassword === "") {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Password confirmation does not match.";
    } else {
        $lookupStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");

        if (!$lookupStmt) {
            $error = "Unable to process password reset request.";
        } else {
            $lookupStmt->bind_param("s", $email);
            $lookupStmt->execute();
            $lookupResult = $lookupStmt->get_result();
            $user = $lookupResult->fetch_assoc();
            $lookupStmt->close();

            if (!$user) {
                $error = "No account found for that email.";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

                if (!$updateStmt) {
                    $error = "Unable to update password.";
                } else {
                    $userId = intval($user["id"] ?? 0);
                    $updateStmt->bind_param("si", $hashedPassword, $userId);

                    if ($updateStmt->execute()) {
                        $success = "Password updated successfully. You can now login.";
                    } else {
                        $error = "Failed to reset password.";
                    }

                    $updateStmt->close();
                }
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
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="form-page">
    <div class="form-left">
        <div>
            <img src="../images/logo.png" alt="CampusCare">
            <h1>CampusCare</h1>
            <p>Reset your password and get back to your account</p>
        </div>
    </div>

    <div class="form-right">
        <div class="form-box">
            <h2>Forgot Password</h2>
            <p>Enter your email and create a new password</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

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

            <p style="margin-top:20px;">
                Remembered your password?
                <a href="index.php" class="small-link">Back to Login</a>
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


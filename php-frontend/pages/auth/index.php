<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/recaptcha.php";
require_once __DIR__ . "/../../includes/google_oauth.php";

$error = "";
$email = "";
$frontendBaseUrl = "/campuscare-api/php-frontend";
$projectBaseUrl = "/campuscare-api";
$googleOauthConfig = campuscare_google_oauth_config();
$googleOauthEnabled = (bool) ($googleOauthConfig["is_configured"] ?? false);

if (isset($_SESSION["oauth_error"])) {
    $error = trim((string) $_SESSION["oauth_error"]);
    unset($_SESSION["oauth_error"]);
}

if (($_GET["expired"] ?? "") === "1") {
    $error = "Your session expired due to inactivity. Please log in again.";
}

if (($_GET["reset"] ?? "") === "success") {
    $success = "Password reset successful. Please log in.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $recaptchaToken = trim($_POST["g-recaptcha-response"] ?? "");

    if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } elseif ($RECAPTCHA_SITE_KEY === "") {
        $error = "reCAPTCHA is not configured. Please contact the administrator.";
    } elseif ($recaptchaToken === "") {
        $error = "Please complete the reCAPTCHA challenge.";
    } else {
        $recaptchaCheck = verify_recaptcha_token($recaptchaToken, $_SERVER["REMOTE_ADDR"] ?? null);

        if (!($recaptchaCheck["success"] ?? false)) {
            $error = $recaptchaCheck["message"] ?? "reCAPTCHA verification failed.";
        } else {
            $stmt = $conn->prepare("SELECT id, full_name, student_id, email, password, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if ($user["status"] !== "Active") {
                    $error = "Your account is not active. Please verify your email before logging in.";
                } elseif (password_verify($password, $user["password"])) {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["student_id"] = $user["student_id"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | CampusCare</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($frontendBaseUrl); ?>/assets/style.css">
    <?php if ($RECAPTCHA_SITE_KEY !== ""): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body class="login-page">
<div class="form-page login-form-page">
    <div class="form-left login-hero">
        <img
            class="login-hero-image"
            src="<?php echo htmlspecialchars($projectBaseUrl); ?>/images/LoginCover.png"
            alt="CampusCare login cover"
            loading="eager"
            decoding="async"
        >
        <div class="login-hero-content">
            <h1>Welcome</h1>
            <p>A caring space where your mind can breathe, heal, and grow.</p>
        </div>
    </div>

    <div class="form-right login-panel">
        <div class="form-box login-box">
            <div class="login-brand-row">
                <h2>Campus<span class="brand-second-c">C</span>are</h2>
                <img
                    class="login-heartbeat"
                    src="<?php echo htmlspecialchars($projectBaseUrl); ?>/images/Heartbeat.png"
                    alt="Heartbeat icon"
                    loading="eager"
                    decoding="async"
                >
            </div>
            <p>Login with Email</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <div class="input-icon-field">
                        <span class="field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path fill="currentColor" d="M20 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 2-8 5-8-5h16Zm0 10H4V9l8 5 8-5v8Z"></path>
                            </svg>
                        </span>
                        <input type="email" name="email" placeholder="yourname@mail.com" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="password-field input-icon-field">
                        <span class="field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path fill="currentColor" d="M17 8h-1V6a4 4 0 1 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4V6Zm7 12H7v-8h10v8Z"></path>
                            </svg>
                        </span>
                        <input id="loginPassword" type="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" data-target="loginPassword" aria-label="Show password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="M12 5c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8C21.4 14.2 17.8 19 12 19S2.6 14.2 1.4 12.5a.8.8 0 0 1 0-.8C2.6 9.8 6.2 5 12 5Zm0 2c-4.4 0-7.3 3.4-8.6 5 1.3 1.6 4.2 5 8.6 5s7.3-3.4 8.6-5c-1.3-1.6-4.2-5-8.6-5Zm0 2.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Z"></path>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill="currentColor" d="m3.3 2 18.7 18.7-1.3 1.3-3.2-3.2A11.8 11.8 0 0 1 12 20c-5.8 0-9.4-4.8-10.6-6.7a.8.8 0 0 1 0-.8A19 19 0 0 1 6.4 7L2 2.6 3.3 2Zm4.6 6L6 6.1A16.8 16.8 0 0 0 3.4 12c1.3 1.6 4.2 6 8.6 6 1.5 0 2.8-.4 4-1l-1.8-1.8a4.8 4.8 0 0 1-6.3-6.3Zm4.2-3c5.8 0 9.4 4.8 10.6 6.7.2.2.2.6 0 .8a19 19 0 0 1-3.5 4.2l-1.4-1.4a16.8 16.8 0 0 0 2.8-3.2c-1.3-1.6-4.2-5-8.6-5h-.5L10 5.4c.6-.3 1.3-.4 2-.4Z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <p style="margin-top:-4px; margin-bottom:10px; text-align:right;">
                    <a href="forgot_password.php" id="forgotPasswordLink" class="small-link">Forgot password?</a>
                </p>

                <div class="form-group">
                    <?php if ($RECAPTCHA_SITE_KEY !== ""): ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($RECAPTCHA_SITE_KEY); ?>"></div>
                    <?php else: ?>
                        <p class="page-subtitle" style="font-size: 13px; margin-bottom: 0;">
                            reCAPTCHA is not configured. Set RECAPTCHA_SITE_KEY in backend/.env.
                        </p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn login-submit" style="width:100%;">LOGIN</button>
            </form>

            <div class="oauth-divider"><span>or</span></div>

            <div class="social-row">
                <?php if ($googleOauthEnabled): ?>
                    <a href="google-login.php?mode=login" class="social-btn" aria-label="Continue with Google">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-2 3.1l3.2 2.5c1.9-1.8 3-4.5 3-7.7 0-.7-.1-1.3-.2-1.9H12z"></path>
                            <path fill="#34A853" d="M12 22c2.7 0 5-0.9 6.7-2.4l-3.2-2.5c-.9.6-2 .9-3.5.9-2.7 0-5-1.8-5.8-4.3l-3.3 2.6C4.7 19.7 8.1 22 12 22z"></path>
                            <path fill="#4A90E2" d="M6.2 13.7c-.2-.6-.3-1.1-.3-1.7s.1-1.2.3-1.7L2.9 7.7C2.3 8.9 2 10.4 2 12s.3 3.1.9 4.3l3.3-2.6z"></path>
                            <path fill="#FBBC05" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9C17 2.9 14.7 2 12 2 8.1 2 4.7 4.3 2.9 7.7l3.3 2.6c.8-2.5 3.1-4.4 5.8-4.4z"></path>
                        </svg>
                    </a>
                <?php else: ?>
                    <span class="social-btn is-disabled" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.2-.9 2.3-2 3.1l3.2 2.5c1.9-1.8 3-4.5 3-7.7 0-.7-.1-1.3-.2-1.9H12z"></path>
                            <path fill="#34A853" d="M12 22c2.7 0 5-0.9 6.7-2.4l-3.2-2.5c-.9.6-2 .9-3.5.9-2.7 0-5-1.8-5.8-4.3l-3.3 2.6C4.7 19.7 8.1 22 12 22z"></path>
                            <path fill="#4A90E2" d="M6.2 13.7c-.2-.6-.3-1.1-.3-1.7s.1-1.2.3-1.7L2.9 7.7C2.3 8.9 2 10.4 2 12s.3 3.1.9 4.3l3.3-2.6z"></path>
                            <path fill="#FBBC05" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.9C17 2.9 14.7 2 12 2 8.1 2 4.7 4.3 2.9 7.7l3.3 2.6c.8-2.5 3.1-4.4 5.8-4.4z"></path>
                        </svg>
                    </span>
                <?php endif; ?>

                <span class="social-btn is-disabled" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path fill="currentColor" d="M22 12a10 10 0 1 0-11.6 9.9v-7h-2.1V12h2.1V9.8c0-2.1 1.3-3.3 3.2-3.3.9 0 1.9.2 1.9.2v2.1h-1.1c-1.1 0-1.4.7-1.4 1.4V12h2.4l-.4 2.9H13v7A10 10 0 0 0 22 12Z"></path>
                    </svg>
                </span>

                <span class="social-btn is-disabled" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path fill="currentColor" d="M16.7 12.5c0-2.2 1.8-3.2 1.9-3.3-1-1.5-2.7-1.7-3.3-1.7-1.4-.1-2.8.8-3.5.8-.7 0-1.8-.8-3-.8-1.6 0-3 .9-3.8 2.3-1.6 2.8-.4 6.8 1.2 9.1.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7c1.3 0 2.1-1.1 2.9-2.2.9-1.3 1.3-2.7 1.3-2.8-.1 0-2.6-1-2.6-3.7Zm-2.3-6.4c.6-.7 1-1.7.9-2.6-.9 0-1.9.6-2.5 1.3-.6.7-1 1.7-.9 2.6 1 0 1.9-.5 2.5-1.3Z"></path>
                    </svg>
                </span>
            </div>

            <?php if (!$googleOauthEnabled): ?>
                <p class="oauth-help">Google sign-in is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in backend/.env.</p>
            <?php endif; ?>

            <p class="login-register-note">
                Don't have account?
                <a href="register.php?fresh=1" class="small-link">Create account</a>
            </p>


        </div>
    </div>
</div>
<script>
(function () {
    var forgotPasswordLink = document.getElementById("forgotPasswordLink");
    var emailInput = document.querySelector('input[name="email"]');
    var toggles = document.querySelectorAll(".password-toggle");

    if (forgotPasswordLink && emailInput) {
        var updateForgotPasswordLink = function () {
            var emailValue = emailInput.value.trim();
            var baseHref = "forgot_password.php";

            forgotPasswordLink.href = emailValue
                ? baseHref + "?email=" + encodeURIComponent(emailValue)
                : baseHref;
        };

        emailInput.addEventListener("input", updateForgotPasswordLink);
        updateForgotPasswordLink();

        forgotPasswordLink.addEventListener("click", function () {
            updateForgotPasswordLink();
        });
    }

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

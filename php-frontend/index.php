<?php
session_start();
require_once "includes/db.php";
require_once "includes/recaptcha.php";

$error = "";
$email = "";

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
                    $error = "Your account is not active.";
                } elseif (password_verify($password, $user["password"])) {
                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["full_name"] = $user["full_name"];
                    $_SESSION["student_id"] = $user["student_id"];
                    $_SESSION["email"] = $user["email"];
                    $_SESSION["role"] = $user["role"];

                    header("Location: dashboard.php");
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
    <link rel="stylesheet" href="assets/style.css">
    <?php if ($RECAPTCHA_SITE_KEY !== ""): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<div class="form-page">
    <div class="form-left">
        <div>
            <img src="../images/logo.png" alt="CampusCare">
            <h1>CampusCare</h1>
            <p>Your university mental health and wellness companion</p>
        </div>
    </div>

    <div class="form-right">
        <div class="form-box">
            <h2>Welcome Back</h2>
            <p>Login to continue to CampusCare</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>

                <div class="form-group">
                    <?php if ($RECAPTCHA_SITE_KEY !== ""): ?>
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($RECAPTCHA_SITE_KEY); ?>"></div>
                    <?php else: ?>
                        <p class="page-subtitle" style="font-size: 13px; margin-bottom: 0;">
                            reCAPTCHA is not configured. Set RECAPTCHA_SITE_KEY in backend/.env.
                        </p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn" style="width:100%;">Login</button>
            </form>

            <p style="margin-top:20px;">
                No account yet?
                <a href="register.php" class="small-link">Create account</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
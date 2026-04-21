<?php
session_start();
require_once "includes/db.php";
require_once "includes/recaptcha.php";

$error = "";
$success = "";
$full_name = "";
$email = "";
$student_id = "";
$role = "Student";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $student_id = trim($_POST["student_id"] ?? "");
    $role = trim($_POST["role"] ?? "Student");
    $password = $_POST["password"] ?? "";
    $recaptchaToken = trim($_POST["g-recaptcha-response"] ?? "");

    if ($full_name === "" || $email === "" || $student_id === "" || $role === "" || $password === "") {
        $error = "All fields are required.";
    } elseif ($RECAPTCHA_SITE_KEY === "") {
        $error = "reCAPTCHA is not configured. Please contact the administrator.";
    } elseif ($recaptchaToken === "") {
        $error = "Please complete the reCAPTCHA challenge.";
    } else {
        $recaptchaCheck = verify_recaptcha_token($recaptchaToken, $_SERVER["REMOTE_ADDR"] ?? null);

        if (!($recaptchaCheck["success"] ?? false)) {
            $error = $recaptchaCheck["message"] ?? "reCAPTCHA verification failed.";
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param("s", $email);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {
                $error = "Email already exists.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $status = "Active";

                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, student_id, email, password, role, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssss", $full_name, $student_id, $email, $hashedPassword, $role, $status);

                if ($stmt->execute()) {
                    $success = "Account created successfully. You can now login.";
                    $full_name = "";
                    $email = "";
                    $student_id = "";
                    $role = "Student";
                } else {
                    $error = "Failed to create account.";
                }

                $stmt->close();
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
            <h2>Create Account</h2>
            <p>Join CampusCare</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
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
                    <input type="password" name="password" required>
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

                <button type="submit" class="btn" style="width:100%;">Register</button>
            </form>

            <p style="margin-top:20px;">
                Already have an account?
                <a href="index.php" class="small-link">Login</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
<?php
session_start();
require_once "includes/db.php";

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

    if ($full_name === "" || $email === "" || $student_id === "" || $role === "" || $password === "") {
        $error = "All fields are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "Email must be unique. Duplicate email is not allowed.";
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
                $error = intval($stmt->errno) === 1062
                    ? "Email must be unique. Duplicate email is not allowed."
                    : "Failed to create account.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | CampusCare</title>
    <link rel="stylesheet" href="assets/style.css">
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

                <button type="submit" class="btn" style="width:100%;">Register</button>
            </form>

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
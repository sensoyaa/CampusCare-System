<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Edit Profile";
$userId = intval($_SESSION["user_id"] ?? 0);
$userRole = normalizeRole($_SESSION["role"] ?? "Student");

$error = "";
$success = "";

$fullName = trim((string) ($_SESSION["full_name"] ?? ""));
$email = trim((string) ($_SESSION["email"] ?? ""));
$studentId = trim((string) ($_SESSION["student_id"] ?? ""));
$currentPassword = "";
$newPassword = "";
$confirmPassword = "";

$formState = [
    "full_name" => $fullName,
    "email" => $email,
    "student_id" => $studentId,
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "update_info"));

    if ($action === "update_info") {
        $newFullName = trim((string) ($_POST["full_name"] ?? ""));
        $newEmail = trim((string) ($_POST["email"] ?? ""));

        if ($newFullName === "") {
            $error = "Full name is required.";
        } elseif ($newEmail === "" || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email is required.";
        } else {
            $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");

            if (!$checkEmail) {
                $error = "Unable to validate email.";
            } else {
                $checkEmail->bind_param("si", $newEmail, $userId);
                $checkEmail->execute();
                $emailExists = $checkEmail->get_result()->num_rows > 0;
                $checkEmail->close();

                if ($emailExists) {
                    $error = "This email is already in use.";
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");

                    if (!$updateStmt) {
                        $error = "Unable to update profile.";
                    } else {
                        $updateStmt->bind_param("ssi", $newFullName, $newEmail, $userId);

                        if ($updateStmt->execute()) {
                            $_SESSION["full_name"] = $newFullName;
                            $_SESSION["email"] = $newEmail;
                            $fullName = $newFullName;
                            $email = $newEmail;
                            $formState["full_name"] = $newFullName;
                            $formState["email"] = $newEmail;
                            $success = "Profile updated successfully.";
                        } else {
                            $error = "Failed to update profile.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }
    } elseif ($action === "change_password") {
        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if ($currentPassword === "") {
            $error = "Current password is required.";
        } elseif ($newPassword === "") {
            $error = "New password is required.";
        } elseif ($confirmPassword === "") {
            $error = "Password confirmation is required.";
        } elseif (strlen($newPassword) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Password confirmation does not match.";
        } else {
            $getUserStmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");

            if (!$getUserStmt) {
                $error = "Unable to verify password.";
            } else {
                $getUserStmt->bind_param("i", $userId);
                $getUserStmt->execute();
                $userResult = $getUserStmt->get_result();
                $user = $userResult->fetch_assoc();
                $getUserStmt->close();

                if (!$user || !password_verify($currentPassword, $user["password"])) {
                    $error = "Current password is incorrect.";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePasswordStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

                    if (!$updatePasswordStmt) {
                        $error = "Unable to update password.";
                    } else {
                        $updatePasswordStmt->bind_param("si", $hashedPassword, $userId);

                        if ($updatePasswordStmt->execute()) {
                            $success = "Password changed successfully.";
                            $currentPassword = "";
                            $newPassword = "";
                            $confirmPassword = "";
                        } else {
                            $error = "Failed to change password.";
                        }

                        $updatePasswordStmt->close();
                    }
                }
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

        <div class="topbar-user">
            <span>Hi, <?php echo htmlspecialchars($fullName); ?>!</span>
            <span class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></span>
            <button class="profile-menu-toggle" aria-label="Profile menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M7 10l5 5 5-5z"></path>
                </svg>
            </button>
            <div class="profile-dropdown">
                <a href="edit_profile.php" class="profile-dropdown-item">Edit Profile</a>
                <a href="settings.php" class="profile-dropdown-item">Settings</a>
                <a href="../auth/logout.php" class="profile-dropdown-item logout-item">Logout</a>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="page-shell" style="max-width: 640px;">
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle" style="margin-bottom: 24px;">Manage your account information and security</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title">Personal Information</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_info">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($formState["full_name"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($formState["email"]); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Student/Employee ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($formState["student_id"]); ?>" disabled>
                        <small style="color: var(--text-muted);">This cannot be changed.</small>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 16px;">Save Changes</button>
                </form>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h2 class="card-title">Change Password</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="8" required>
                        <small style="color: var(--text-muted);">Minimum 8 characters</small>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 16px;">Change Password</button>
                </form>
            </div>

            <p style="margin-top: 24px; text-align: center;">
                <a href="dashboard.php" class="small-link">Back to Dashboard</a>
            </p>
        </div>
    </div>
</main>

</div>
<script>
(function () {
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
        if (parent) parent.classList.remove("is-open");
        profileMenuToggle.setAttribute("aria-expanded", "false");
    });

    profileDropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });
})();
</script>
</body>
</html>

<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Edit Profile";
$userId = intval($_SESSION["user_id"] ?? 0);
$userRole = normalizeRole($_SESSION["role"] ?? "Student");

$error = "";
$success = (string) ($_SESSION["profile_flash_success"] ?? "");
unset($_SESSION["profile_flash_success"]);

$usersHasStudentId = false;
$usersHasStudentNumber = false;
$usersHasCollege = false;
$usersHasProgram = false;
$usersHasAvatarPath = false;

$columnsResult = $conn->query("SHOW COLUMNS FROM users");
if ($columnsResult) {
    while ($column = $columnsResult->fetch_assoc()) {
        $columnName = (string) ($column["Field"] ?? "");
        if ($columnName === "student_id") {
            $usersHasStudentId = true;
        }
        if ($columnName === "student_number") {
            $usersHasStudentNumber = true;
        }
        if ($columnName === "college") {
            $usersHasCollege = true;
        }
        if ($columnName === "program") {
            $usersHasProgram = true;
        }
        if ($columnName === "avatar_path") {
            $usersHasAvatarPath = true;
        }
    }
}

$fullName = trim((string) ($_SESSION["full_name"] ?? ""));
$email = trim((string) ($_SESSION["email"] ?? ""));
$studentId = trim((string) ($_SESSION["student_id"] ?? ""));
$college = "";
$program = "";
$avatarPath = trim((string) ($_SESSION["avatar_path"] ?? ""));

$profileColumns = ["id", "full_name", "email", "role"];
if ($usersHasStudentId) {
    $profileColumns[] = "student_id";
} elseif ($usersHasStudentNumber) {
    $profileColumns[] = "student_number AS student_id";
}
if ($usersHasCollege) {
    $profileColumns[] = "college";
}
if ($usersHasProgram) {
    $profileColumns[] = "program";
}
if ($usersHasAvatarPath) {
    $profileColumns[] = "avatar_path";
}

$profileStmt = $conn->prepare("SELECT " . implode(", ", $profileColumns) . " FROM users WHERE id = ? LIMIT 1");
if ($profileStmt) {
    $profileStmt->bind_param("i", $userId);
    $profileStmt->execute();
    $profileResult = $profileStmt->get_result();
    $profileRow = $profileResult ? $profileResult->fetch_assoc() : null;
    $profileStmt->close();

    if (is_array($profileRow)) {
        $fullName = trim((string) ($profileRow["full_name"] ?? $fullName));
        $email = trim((string) ($profileRow["email"] ?? $email));
        $studentId = trim((string) ($profileRow["student_id"] ?? $studentId));
        if ($usersHasCollege) {
            $college = trim((string) ($profileRow["college"] ?? ""));
        }
        if ($usersHasProgram) {
            $program = trim((string) ($profileRow["program"] ?? ""));
        }
        if ($usersHasAvatarPath) {
            $avatarPath = trim((string) ($profileRow["avatar_path"] ?? ""));
        }
    }
}

$avatarInitial = strtoupper(substr($fullName !== "" ? $fullName : "U", 0, 1));
$avatarUrl = $avatarPath !== "" ? $avatarPath : "";
$currentPassword = "";
$newPassword = "";
$confirmPassword = "";

$allowedColleges = [
    "College of Nursing",
    "College of Technology",
    "College of Arts and Sciences",
    "College of Law",
    "College of Medicine",
    "College of Public Administration and Governance",
    "College of Business",
    "College of Education",
];

$formState = [
    "full_name" => $fullName,
    "email" => $email,
    "student_id" => $studentId,
    "college" => $college,
    "program" => $program,
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "update_info"));

    if ($action === "update_info") {
        $newFullName = trim((string) ($_POST["full_name"] ?? ""));
        $newEmail = trim((string) ($_POST["email"] ?? ""));
        $newCollege = trim((string) ($_POST["college"] ?? ""));
        $newProgram = trim((string) ($_POST["program"] ?? ""));
        $uploadedAvatarPath = $avatarPath;

        if (isset($_FILES["avatar"]) && is_array($_FILES["avatar"]) && (int) ($_FILES["avatar"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $avatarFile = $_FILES["avatar"];

            if ((int) ($avatarFile["error"] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $error = "Unable to upload avatar.";
            } elseif ((int) ($avatarFile["size"] ?? 0) > 2 * 1024 * 1024) {
                $error = "Avatar must be 2MB or smaller.";
            } else {
                $imageInfo = @getimagesize((string) ($avatarFile["tmp_name"] ?? ""));
                $mimeType = (string) ($imageInfo["mime"] ?? "");
                $allowedMimeTypes = ["image/jpeg" => "jpg", "image/png" => "png", "image/gif" => "gif", "image/webp" => "webp"];

                if (!isset($allowedMimeTypes[$mimeType])) {
                    $error = "Avatar must be a JPG, PNG, GIF, or WEBP image.";
                } else {
                    $uploadDir = __DIR__ . "/../../uploads/avatars";

                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }

                    $fileExtension = $allowedMimeTypes[$mimeType];
                    $fileName = "user_" . $userId . "_" . time() . "." . $fileExtension;
                    $absoluteAvatarPath = $uploadDir . "/" . $fileName;
                    $publicAvatarPath = "/campuscare-api/php-frontend/uploads/avatars/" . $fileName;

                    if (!move_uploaded_file((string) ($avatarFile["tmp_name"] ?? ""), $absoluteAvatarPath)) {
                        $error = "Failed to save the uploaded avatar.";
                    } else {
                        if ($avatarPath !== "") {
                            $existingAvatarFile = __DIR__ . "/../../" . ltrim($avatarPath, "/");
                            if (is_file($existingAvatarFile)) {
                                @unlink($existingAvatarFile);
                            }
                        }

                        $uploadedAvatarPath = $publicAvatarPath;
                    }
                }
            }
        }

        if ($error !== "") {
            // Stop here if avatar validation/upload failed.
        } elseif ($newFullName === "") {
            $error = "Full name is required.";
        } elseif ($newEmail === "" || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Valid email is required.";
        } elseif ($newCollege === "" || !in_array($newCollege, $allowedColleges, true)) {
            $error = "Please select a valid college.";
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
                    if ($usersHasCollege && $usersHasProgram && $usersHasAvatarPath) {
                        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, college = ?, program = ?, avatar_path = ? WHERE id = ?");
                    } elseif ($usersHasCollege && $usersHasProgram) {
                        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, college = ?, program = ? WHERE id = ?");
                    } else {
                        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                    }

                    if (!$updateStmt) {
                        $error = "Unable to update profile.";
                    } else {
                        if ($usersHasCollege && $usersHasProgram && $usersHasAvatarPath) {
                            $updateStmt->bind_param("sssssi", $newFullName, $newEmail, $newCollege, $newProgram, $uploadedAvatarPath, $userId);
                        } elseif ($usersHasCollege && $usersHasProgram) {
                            $updateStmt->bind_param("ssssi", $newFullName, $newEmail, $newCollege, $newProgram, $userId);
                        } else {
                            $updateStmt->bind_param("ssi", $newFullName, $newEmail, $userId);
                        }

                        if ($updateStmt->execute()) {
                            $_SESSION["full_name"] = $newFullName;
                            $_SESSION["email"] = $newEmail;
                            $fullName = $newFullName;
                            $email = $newEmail;
                            $college = $newCollege;
                            $program = $newProgram;
                            $avatarPath = $uploadedAvatarPath;
                            $avatarUrl = $avatarPath;
                            $formState["full_name"] = $newFullName;
                            $formState["email"] = $newEmail;
                            $formState["college"] = $newCollege;
                            $formState["program"] = $newProgram;
                            $_SESSION["avatar_path"] = $avatarPath;
                            $_SESSION["profile_flash_success"] = "Profile updated successfully.";
                            header("Location: edit_profile.php");
                            exit();
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

<style>
.profile-layout {
    max-width: 1120px;
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.profile-summary-card {
    padding: 26px 24px;
}

.profile-summary-card .profile-identity {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.profile-summary-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    background: linear-gradient(135deg, #f8fafc, #dbeafe);
    border: 2px solid rgba(148, 163, 184, 0.45);
    color: #1d4ed8;
    font-size: 42px;
    font-weight: 800;
    letter-spacing: -1px;
    overflow: hidden;
}

.profile-summary-avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.profile-summary-name {
    margin: 0;
    font-size: 26px;
    line-height: 1.1;
    letter-spacing: -0.6px;
}

.profile-summary-role {
    margin: 10px 0 0;
    font-size: 18px;
    color: #475569;
}

.profile-summary-divider {
    height: 1px;
    background: #d1d5db;
    margin: 28px 0 18px;
}

.profile-summary-list {
    display: grid;
    gap: 14px;
}

.profile-summary-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: #1f2937;
    font-size: 15px;
    line-height: 1.45;
}

.profile-summary-icon {
    width: 22px;
    height: 22px;
    color: #64748b;
    flex: 0 0 auto;
    margin-top: 2px;
}

.profile-summary-label {
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    margin-bottom: 2px;
}

.profile-summary-value {
    display: block;
    font-weight: 600;
    word-break: break-word;
}

.profile-main-stack {
    display: grid;
    gap: 24px;
}

.profile-page-header {
    margin-bottom: 20px;
}

.profile-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

@media (max-width: 960px) {
    .profile-layout {
        grid-template-columns: 1fr;
    }

    .profile-summary-card {
        order: -1;
    }

    .profile-meta-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell">
            <div class="profile-page-header">
                <h1 class="page-title">Edit Profile</h1>
                <p class="page-subtitle">Manage your account information and security</p>
            </div>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="profile-layout">

            <aside class="card profile-summary-card">
                
                <div class="profile-identity">
                    <div class="profile-summary-avatar">
                        <?php if ($avatarUrl !== ""): ?>
                            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Profile avatar" class="profile-summary-avatar-image">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($avatarInitial); ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="profile-summary-name"><?php echo htmlspecialchars($fullName !== "" ? $fullName : "User"); ?></h2>
                    <p class="profile-summary-role"><?php echo htmlspecialchars($userRole); ?></p>
                </div>

                <div class="profile-summary-divider"></div>

                <div class="profile-summary-list">
                    <div class="profile-summary-item">
                        <svg class="profile-summary-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5z"/></svg>
                        <div>
                            <span class="profile-summary-label">Email</span>
                            <span class="profile-summary-value"><?php echo htmlspecialchars($email !== "" ? $email : "Not provided"); ?></span>
                        </div>
                    </div>

                    <div class="profile-summary-item">
                        <svg class="profile-summary-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3 1 9l11 6 9-4.91V17h2V9L12 3zm-7 9.5V15c0 2.76 3.13 5 7 5s7-2.24 7-5v-2.5l-7 3.82-7-3.82z"/></svg>
                        <div>
                            <span class="profile-summary-label">College</span>
                            <span class="profile-summary-value"><?php echo htmlspecialchars($college !== "" ? $college : "Not provided"); ?></span>
                        </div>
                    </div>

                    <div class="profile-summary-item">
                        <svg class="profile-summary-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 6h16v2H4zm0 5h16v2H4zm0 5h10v2H4z"/></svg>
                        <div>
                            <span class="profile-summary-label">Program</span>
                            <span class="profile-summary-value"><?php echo htmlspecialchars($program !== "" ? $program : "Not provided"); ?></span>
                        </div>
                    </div>

                    <div class="profile-summary-item">
                        <svg class="profile-summary-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16v16H4zM7 7h10v2H7zm0 4h10v2H7zm0 4h6v2H7z"/></svg>
                        <div>
                            <span class="profile-summary-label">ID</span>
                            <span class="profile-summary-value"><?php echo htmlspecialchars($studentId !== "" ? $studentId : "Not provided"); ?></span>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="profile-main-stack">
            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title">Personal Information</h2>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_info">

                    <div class="form-group">
                        <label>Profile Photo</label>
                        <input type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp">
                        <small style="color: var(--text-muted);">JPG, PNG, GIF, or WEBP. Max 2MB.</small>
                    </div>

                    <div class="profile-meta-grid">
                        <div class="form-group">
                            <label>College</label>
                            <select name="college" required>
                                <option value="">Select College</option>
                                <?php foreach ($allowedColleges as $collegeOption): ?>
                                    <option value="<?php echo htmlspecialchars($collegeOption); ?>" <?php echo $formState["college"] === $collegeOption ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($collegeOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Program</label>
                            <input type="text" name="program" value="<?php echo htmlspecialchars($formState["program"]); ?>" placeholder="e.g. BSIT">
                        </div>
                    </div>

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
                <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="small-link">Back to Dashboard</a>
            </p>
            </div>
            </div>
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


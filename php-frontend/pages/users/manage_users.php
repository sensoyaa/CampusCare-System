<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Manage Users";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "Administrator";

if ($role !== "Administrator") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

function roleToDatabase(string $role): string
{
    if ($role === "Counselor") {
        return "Counsellor";
    }

    return $role;
}

$error = "";
$success = "";
$showModal = false;
$modalMode = "add";
$showDeleteSuccess = false;

// Check for delete success from redirect
if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $showDeleteSuccess = true;
}

$formState = [
    "user_id" => 0,
    "student_id" => "",
    "full_name" => "",
    "email" => "",
    "password" => "",
    "role" => "Student",
    "status" => "Active",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    if ($action === "add_user") {
        $showModal = true;
        $modalMode = "add";

        $formState["student_id"] = trim((string) ($_POST["student_id"] ?? ""));
        $formState["full_name"] = trim((string) ($_POST["full_name"] ?? ""));
        $formState["email"] = trim((string) ($_POST["email"] ?? ""));
        $formState["password"] = trim((string) ($_POST["password"] ?? ""));
        $formState["role"] = trim((string) ($_POST["role"] ?? "Student"));
        $formState["status"] = trim((string) ($_POST["status"] ?? "Active"));

        if (
            $formState["student_id"] === "" ||
            $formState["full_name"] === "" ||
            $formState["email"] === "" ||
            $formState["password"] === "" ||
            $formState["role"] === "" ||
            $formState["status"] === ""
        ) {
            $error = "Please complete all required fields.";
        } else {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->bind_param("s", $formState["email"]);
            $checkStmt->execute();
            $exists = (bool) $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($exists) {
                $error = "Email must be unique. Duplicate email is not allowed.";
            } else {
                $hashedPassword = password_hash($formState["password"], PASSWORD_DEFAULT);
                $dbRole = roleToDatabase($formState["role"]);

                $insertStmt = $conn->prepare(
                    "INSERT INTO users (full_name, student_id, email, password, role, status)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $insertStmt->bind_param(
                    "ssssss",
                    $formState["full_name"],
                    $formState["student_id"],
                    $formState["email"],
                    $hashedPassword,
                    $dbRole,
                    $formState["status"]
                );

                if ($insertStmt->execute()) {
                    $success = "User added successfully.";
                    $showModal = false;
                    $formState = [
                        "user_id" => 0,
                        "student_id" => "",
                        "full_name" => "",
                        "email" => "",
                        "password" => "",
                        "role" => "Student",
                        "status" => "Active",
                    ];
                } else {
                    $error = "Failed to add user.";
                }

                $insertStmt->close();
            }
        }
    } elseif ($action === "update_user") {
        $showModal = true;
        $modalMode = "edit";

        $formState["user_id"] = intval($_POST["user_id"] ?? 0);
        $formState["student_id"] = trim((string) ($_POST["student_id"] ?? ""));
        $formState["full_name"] = trim((string) ($_POST["full_name"] ?? ""));
        $formState["email"] = trim((string) ($_POST["email"] ?? ""));
        $formState["role"] = trim((string) ($_POST["role"] ?? "Student"));
        $formState["status"] = trim((string) ($_POST["status"] ?? "Active"));

        if ($formState["user_id"] <= 0 || $formState["full_name"] === "" || $formState["role"] === "" || $formState["status"] === "") {
            $error = "Missing required fields for update.";
        } else {
            $dbRole = roleToDatabase($formState["role"]);

            $updateStmt = $conn->prepare(
                "UPDATE users
                 SET full_name = ?, role = ?, status = ?
                 WHERE id = ?"
            );
            $updateStmt->bind_param(
                "sssi",
                $formState["full_name"],
                $dbRole,
                $formState["status"],
                $formState["user_id"]
            );

            if ($updateStmt->execute()) {
                $success = "User updated successfully.";
                $showModal = false;
            } else {
                $error = "Failed to update user.";
            }

            $updateStmt->close();
        }
    } elseif ($action === "delete_user") {
        $userId = intval($_POST["user_id"] ?? 0);

        if ($userId <= 0) {
            $error = "Invalid user selected.";
        } else {
            // Start a transaction to ensure all deletes succeed together
            $conn->begin_transaction();

            try {
                // Delete from various tables only if they exist
                // We use conditional checks to avoid errors if tables don't exist
                
                // Check and delete from mental_health_test_answers if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_test_answers'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare(
                        "DELETE FROM mental_health_test_answers 
                         WHERE attempt_id IN (
                            SELECT id FROM mental_health_test_attempts WHERE user_id = ?
                         )"
                    );
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Check and delete from mental_health_test_attempts if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_test_attempts'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM mental_health_test_attempts WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Check and delete from mental_health_tests if table has user_id
                $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_tests'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $checkColumn = $conn->query("SHOW COLUMNS FROM mental_health_tests LIKE 'user_id'");
                    if ($checkColumn && $checkColumn->num_rows > 0) {
                        $stmt = $conn->prepare("DELETE FROM mental_health_tests WHERE user_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }

                // Delete appointments
                $stmt = $conn->prepare("DELETE FROM appointments WHERE user_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->close();
                }

                // Check and delete from event_participants if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'event_participants'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM event_participants WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Check and delete from user_notifications if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM user_notifications WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Check and delete from user_sessions if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'user_sessions'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Check and delete from user_preferences if table exists
                $checkTable = $conn->query("SHOW TABLES LIKE 'user_preferences'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                // Finally, delete the user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    throw new Exception("Failed to prepare delete statement for users table");
                }

                // Commit the transaction
                $conn->commit();
                
                // Redirect to avoid form resubmission on refresh (Post-Redirect-Get pattern)
                header("Location: /campuscare-api/php-frontend/pages/users/manage_users.php?deleted=1");
                exit();
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = "Failed to remove user: " . $e->getMessage();
            }
        }
    }
}

$search = trim((string) ($_GET["search"] ?? ""));
$filterRole = trim((string) ($_GET["role"] ?? "all"));

$users = [];
$result = $conn->query("SELECT id, student_id, full_name, email, role, status FROM users ORDER BY id DESC");

while ($row = $result->fetch_assoc()) {
    $displayRole = normalizeRole((string) ($row["role"] ?? "Student"));
    $studentId = trim((string) ($row["student_id"] ?? ""));
    $fullNameCell = trim((string) ($row["full_name"] ?? ""));
    $emailCell = trim((string) ($row["email"] ?? ""));

    if ($filterRole !== "all" && $displayRole !== $filterRole) {
        continue;
    }

    if ($search !== "") {
        $haystack = strtolower($studentId . " " . $fullNameCell . " " . $emailCell);
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }

    $row["display_role"] = $displayRole;
    $row["status"] = trim((string) ($row["status"] ?? "Active"));
    $users[] = $row;
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell admin-shell">
            <div class="manage-head">
                <div>
                    <h1 class="page-title">Manage Users</h1>
                    <p class="page-subtitle">Add, edit, or remove system users</p>
                </div>
            </div>

            <div class="manage-head-actions">
                <button type="button" class="btn slot-add-btn" id="openUserModal">
                    <?php echo sidebarIconSvg("user-plus"); ?>
                    Add User
                </button>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($showDeleteSuccess): ?>
                <div class="success-modal-overlay" id="successOverlay">
                    <div class="success-modal-card">
                        <div class="success-modal-icon">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="32" cy="32" r="32" fill="#10b981" opacity="0.1"/>
                                <circle cx="32" cy="32" r="28" fill="none" stroke="#10b981" stroke-width="2"/>
                                <path d="M20 32L28 40L44 24" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="success-modal-title">User Removed Successfully</h3>
                        <p class="success-modal-message">The user account has been deleted from CampusCare.</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('successOverlay').style.display='none'">Close</button>
                    </div>
                </div>
            <?php endif; ?>

            <form method="GET" class="admin-toolbar">
                <div class="admin-search-wrap">
                    <span class="admin-search-icon"><?php echo sidebarIconSvg("search"); ?></span>
                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, ID, or email..."
                    >
                </div>

                <select name="role" class="admin-role-filter" onchange="this.form.submit()">
                    <option value="all" <?php echo $filterRole === "all" ? "selected" : ""; ?>>All Roles</option>
                    <option value="Student" <?php echo $filterRole === "Student" ? "selected" : ""; ?>>Student</option>
                    <option value="Counselor" <?php echo $filterRole === "Counselor" ? "selected" : ""; ?>>Counselor</option>
                    <option value="Facilitator" <?php echo $filterRole === "Facilitator" ? "selected" : ""; ?>>Facilitator</option>
                    <option value="Instructor" <?php echo $filterRole === "Instructor" ? "selected" : ""; ?>>Instructor</option>
                    <option value="Administrator" <?php echo $filterRole === "Administrator" ? "selected" : ""; ?>>Administrator</option>
                </select>
            </form>

            <section class="admin-users-table">
                <div class="admin-users-head">
                    <span>ID</span>
                    <span>Name</span>
                    <span>Email</span>
                    <span>Role</span>
                    <span>Status</span>
                    <span>Actions</span>
                </div>

                <?php if (empty($users)): ?>
                    <div class="admin-users-empty">No users found.</div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <article class="admin-users-row">
                            <p class="mono"><?php echo htmlspecialchars((string) ($user["student_id"] ?? "")); ?></p>
                            <p class="strong"><?php echo htmlspecialchars((string) ($user["full_name"] ?? "")); ?></p>
                            <p class="muted"><?php echo htmlspecialchars((string) ($user["email"] ?? "")); ?></p>
                            <p>
                                <span class="admin-role-pill"><?php echo htmlspecialchars((string) ($user["display_role"] ?? "")); ?></span>
                            </p>
                            <p>
                                <span class="admin-status-pill <?php echo (string) ($user["status"] ?? "") === "Active" ? "active" : "inactive"; ?>">
                                    <?php echo htmlspecialchars((string) ($user["status"] ?? "Inactive")); ?>
                                </span>
                            </p>
                            <div class="admin-row-actions">
                                <button
                                    type="button"
                                    class="row-icon-btn js-edit-user"
                                    title="Edit user"
                                    data-id="<?php echo intval($user["id"]); ?>"
                                    data-student-id="<?php echo htmlspecialchars((string) ($user["student_id"] ?? ""), ENT_QUOTES); ?>"
                                    data-full-name="<?php echo htmlspecialchars((string) ($user["full_name"] ?? ""), ENT_QUOTES); ?>"
                                    data-email="<?php echo htmlspecialchars((string) ($user["email"] ?? ""), ENT_QUOTES); ?>"
                                    data-role="<?php echo htmlspecialchars((string) ($user["display_role"] ?? "Student"), ENT_QUOTES); ?>"
                                    data-status="<?php echo htmlspecialchars((string) ($user["status"] ?? "Active"), ENT_QUOTES); ?>"
                                    aria-label="Edit user"
                                >
                                    <?php echo sidebarIconSvg("edit"); ?>
                                </button>

                                <form
                                    method="POST"
                                    data-confirm-title="Delete user"
                                    data-confirm-message="Delete this user account from CampusCare?"
                                    data-confirm-button="Delete User"
                                    data-confirm-variant="danger"
                                >
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo intval($user["id"]); ?>">
                                    <button type="submit" class="row-icon-btn danger" aria-label="Delete user">
                                        <?php echo sidebarIconSvg("trash"); ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <div class="modal-overlay<?php echo $showModal ? " open" : ""; ?>" id="userModal" aria-hidden="<?php echo $showModal ? "false" : "true"; ?>">
            <div class="modal-card">
                <div class="modal-head">
                    <h3 id="userModalTitle"><?php echo $modalMode === "edit" ? "Edit User" : "Add New User"; ?></h3>
                    <button type="button" class="modal-close" data-close-user-modal aria-label="Close">&times;</button>
                </div>

                <form method="POST" id="userForm">
                    <input type="hidden" name="action" id="userAction" value="<?php echo $modalMode === "edit" ? "update_user" : "add_user"; ?>">
                    <input type="hidden" name="user_id" id="userIdField" value="<?php echo intval($formState["user_id"]); ?>">

                    <div class="form-group">
                        <label for="userStudentId">User ID</label>
                        <input
                            id="userStudentId"
                            type="text"
                            name="student_id"
                            value="<?php echo htmlspecialchars((string) $formState["student_id"]); ?>"
                            <?php echo $modalMode === "edit" ? "readonly" : "required"; ?>
                        >
                    </div>

                    <div class="form-group">
                        <label for="userFullName">Full Name</label>
                        <input
                            id="userFullName"
                            type="text"
                            name="full_name"
                            value="<?php echo htmlspecialchars((string) $formState["full_name"]); ?>"
                            required
                        >
                    </div>

                    <div class="form-group" id="emailGroup">
                        <label for="userEmail">Email</label>
                        <input
                            id="userEmail"
                            type="email"
                            name="email"
                            value="<?php echo htmlspecialchars((string) $formState["email"]); ?>"
                            <?php echo $modalMode === "edit" ? "readonly" : "required"; ?>
                        >
                    </div>

                    <div class="form-group" id="passwordGroup"<?php echo $modalMode === "edit" ? " style=\"display:none;\"" : ""; ?>>
                        <label for="userPassword">Password</label>
                        <input id="userPassword" type="password" name="password" value="" <?php echo $modalMode === "edit" ? "" : "required"; ?>>
                    </div>

                    <div class="modal-grid">
                        <div class="form-group">
                            <label for="userRole">Role</label>
                            <select id="userRole" name="role" required>
                                <?php
                                    $roleOptions = ["Student", "Counselor", "Facilitator", "Instructor", "Administrator"];
                                    foreach ($roleOptions as $roleOption):
                                ?>
                                    <option value="<?php echo htmlspecialchars($roleOption); ?>" <?php echo $formState["role"] === $roleOption ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($roleOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="userStatus">Status</label>
                            <select id="userStatus" name="status" required>
                                <option value="Active" <?php echo $formState["status"] === "Active" ? "selected" : ""; ?>>Active</option>
                                <option value="Inactive" <?php echo $formState["status"] === "Inactive" ? "selected" : ""; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" data-close-user-modal>Cancel</button>
                        <button type="submit" class="btn" id="userSubmitBtn"><?php echo $modalMode === "edit" ? "Save Changes" : "Add User"; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
    .admin-shell .manage-head {
        padding: 30px 34px 20px;
        background: var(--primary);
        color: #fff;
        border-radius: 22px;
        margin-bottom: 1rem;
        box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    }

    .admin-shell .manage-head h1 {
        margin: 0 0 8px;
        font-size: 34px;
        color: #fff;
    }

    .admin-shell .manage-head p {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, 0.9);
    }

    .admin-shell .manage-head-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 1rem;
    }

    /* Success Modal Styles */
    .success-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        animation: fadeIn 0.3s ease-in-out;
    }

    .success-modal-card {
        background: white;
        border-radius: 20px;
        padding: 48px 40px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        max-width: 420px;
        width: 90%;
        animation: slideUp 0.4s ease-out;
    }

    body.theme-dark .success-modal-card {
        background: #1a2d42;
        color: #e6edf5;
    }

    .success-modal-icon {
        margin-bottom: 24px;
        animation: bounceIn 0.6s ease-out;
    }

    .success-modal-title {
        margin: 0 0 12px;
        font-size: 24px;
        font-weight: 700;
        color: #10b981;
        font-family: "Poppins", sans-serif;
    }

    body.theme-dark .success-modal-title {
        color: #6ee7b7;
    }

    .success-modal-message {
        margin: 0 0 28px;
        font-size: 15px;
        color: #6b7280;
        line-height: 1.6;
    }

    body.theme-dark .success-modal-message {
        color: #b7c7d8;
    }

    .success-modal-card .btn {
        min-width: 140px;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes bounceIn {
        0% {
            transform: scale(0.3);
            opacity: 0;
        }
        50% {
            transform: scale(1.05);
        }
        70% {
            transform: scale(0.9);
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>

<script>
(function () {
    const modal = document.getElementById("userModal");
    const openButton = document.getElementById("openUserModal");
    const closeButtons = modal ? modal.querySelectorAll("[data-close-user-modal]") : [];

    const modalTitle = document.getElementById("userModalTitle");
    const actionInput = document.getElementById("userAction");
    const userIdInput = document.getElementById("userIdField");
    const studentIdInput = document.getElementById("userStudentId");
    const fullNameInput = document.getElementById("userFullName");
    const emailInput = document.getElementById("userEmail");
    const passwordInput = document.getElementById("userPassword");
    const roleInput = document.getElementById("userRole");
    const statusInput = document.getElementById("userStatus");
    const passwordGroup = document.getElementById("passwordGroup");
    const submitButton = document.getElementById("userSubmitBtn");

    if (!modal || !openButton || !modalTitle || !actionInput || !userIdInput || !studentIdInput || !fullNameInput || !emailInput || !roleInput || !statusInput || !passwordGroup || !submitButton) {
        return;
    }

    function openModal() {
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    function setMode(mode) {
        if (mode === "edit") {
            modalTitle.textContent = "Edit User";
            actionInput.value = "update_user";
            submitButton.textContent = "Save Changes";
            studentIdInput.readOnly = true;
            emailInput.readOnly = true;
            passwordGroup.style.display = "none";
            if (passwordInput) {
                passwordInput.required = false;
                passwordInput.value = "";
            }
            return;
        }

        modalTitle.textContent = "Add New User";
        actionInput.value = "add_user";
        submitButton.textContent = "Add User";
        userIdInput.value = "0";
        studentIdInput.readOnly = false;
        emailInput.readOnly = false;
        passwordGroup.style.display = "block";
        if (passwordInput) {
            passwordInput.required = true;
            passwordInput.value = "";
        }
    }

    openButton.addEventListener("click", function () {
        setMode("add");
        studentIdInput.value = "";
        fullNameInput.value = "";
        emailInput.value = "";
        roleInput.value = "Student";
        statusInput.value = "Active";
        openModal();
    });

    const editButtons = document.querySelectorAll(".js-edit-user");
    editButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            setMode("edit");
            userIdInput.value = button.getAttribute("data-id") || "0";
            studentIdInput.value = button.getAttribute("data-student-id") || "";
            fullNameInput.value = button.getAttribute("data-full-name") || "";
            emailInput.value = button.getAttribute("data-email") || "";
            roleInput.value = button.getAttribute("data-role") || "Student";
            statusInput.value = button.getAttribute("data-status") || "Active";
            openModal();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
})();
</script>

</div>
<script>
(function () {
    // Auto-close success modal after 3 seconds and clear URL parameter
    const successOverlay = document.getElementById("successOverlay");
    if (successOverlay) {
        setTimeout(function () {
            successOverlay.style.display = "none";
            // Clean up the URL by removing the ?deleted=1 parameter
            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, "/campuscare-api/php-frontend/pages/users/manage_users.php");
            }
        }, 3000);
    }

    // Handle delete form confirmation
    const deleteForms = document.querySelectorAll("form[data-confirm-title]");
    deleteForms.forEach(function (form) {
        form.addEventListener("submit", function (e) {
            const confirmMessage = form.getAttribute("data-confirm-message") || "Are you sure?";

            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });
    });
})();
</body>
</html>



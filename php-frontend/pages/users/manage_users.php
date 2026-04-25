<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Manage Users";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "Administrator";

if ($role !== "Administrator") {
    header("Location: dashboard.php");
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
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->bind_param("i", $userId);

            if ($deleteStmt->execute()) {
                $success = "User removed successfully.";
            } else {
                $error = "Failed to remove user.";
            }

            $deleteStmt->close();
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
        <div class="page-shell admin-shell">
            <div class="manage-head">
                <div>
                    <h1 class="page-title">Manage Users</h1>
                    <p class="page-subtitle">Add, edit, or remove system users</p>
                </div>

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


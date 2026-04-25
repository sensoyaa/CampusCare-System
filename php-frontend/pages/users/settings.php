<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Settings";
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));

$error = "";
$success = "";

$darkMode = isset($_COOKIE["campuscare_dark_mode"]) && $_COOKIE["campuscare_dark_mode"] === "true";
$enableNotifications = isset($_COOKIE["campuscare_notifications"]) && $_COOKIE["campuscare_notifications"] === "true";
$notificationsEmail = isset($_COOKIE["campuscare_notifications_email"]) && $_COOKIE["campuscare_notifications_email"] === "true";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "update_preferences"));

    if ($action === "update_preferences") {
        $darkModeEnabled = isset($_POST["dark_mode"]);
        $notificationsEnabled = isset($_POST["enable_notifications"]);
        $notificationsEmailEnabled = isset($_POST["notifications_email"]);

        setcookie("campuscare_dark_mode", $darkModeEnabled ? "true" : "false", time() + (365 * 24 * 60 * 60), "/");
        setcookie("campuscare_notifications", $notificationsEnabled ? "true" : "false", time() + (365 * 24 * 60 * 60), "/");
        setcookie("campuscare_notifications_email", $notificationsEmailEnabled ? "true" : "false", time() + (365 * 24 * 60 * 60), "/");

        $darkMode = $darkModeEnabled;
        $enableNotifications = $notificationsEnabled;
        $notificationsEmail = $notificationsEmailEnabled;

        $success = "Preferences updated successfully.";
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
        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell" style="max-width: 640px;">
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle" style="margin-bottom: 24px;">Customize your CampusCare experience</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title">Display</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Dark Mode</label>
                                <p class="settings-description">Use dark theme for reduced eye strain</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="dark_mode" <?php echo $darkMode ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h2 class="card-title">Notifications</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Enable Notifications</label>
                                <p class="settings-description">Receive in-app notifications for appointments and events</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="enable_notifications" <?php echo $enableNotifications ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Email Notifications</label>
                                <p class="settings-description">Receive email reminders for upcoming appointments</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications_email" <?php echo $notificationsEmail ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 16px;">Save Preferences</button>
                </form>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h2 class="card-title">About</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 12px;">CampusCare v1.0</p>
                <p style="color: var(--text-muted); font-size: 13px;">Your university mental health and wellness companion</p>
            </div>

            <p style="margin-top: 24px; text-align: center;">
                <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="small-link">Back to Dashboard</a>
            </p>
        </div>
    </div>
</main>

</div>
<script>
(function () {
    const profileMenuToggle = document.querySelector(".profile-menu-toggle");
    const profileDropdown = document.querySelector(".profile-dropdown");
    const profilePill = document.querySelector(".profile-pill");

    if (!profileMenuToggle || !profileDropdown || !profilePill) {
        return;
    }

    profileMenuToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        const isOpen = profilePill.classList.toggle("is-open");
        profileMenuToggle.setAttribute("aria-expanded", isOpen);
    });

    document.addEventListener("click", function () {
        profilePill.classList.remove("is-open");
        profileMenuToggle.setAttribute("aria-expanded", "false");
    });

    profileDropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });
})();
</script>
</body>
</html>


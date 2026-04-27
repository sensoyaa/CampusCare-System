<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Settings";
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = trim((string) ($_SESSION["full_name"] ?? ""));
$email = trim((string) ($_SESSION["email"] ?? ""));

$error = "";
$success = "";

function getPrefBool(string $name, bool $default = false): bool
{
    if (!isset($_COOKIE[$name])) {
        return $default;
    }

    return $_COOKIE[$name] === "true";
}

function setPrefCookie(string $name, string $value): void
{
    setcookie($name, $value, time() + (365 * 24 * 60 * 60), "/");
    $_COOKIE[$name] = $value;
}

function normalizeChoice(string $value, array $allowed, string $default): string
{
    return in_array($value, $allowed, true) ? $value : $default;
}

$darkMode = getPrefBool("campuscare_dark_mode", false);
$enableNotifications = getPrefBool("campuscare_notifications", true);
$notificationsInApp = getPrefBool("campuscare_notifications_in_app", true);
$notificationsEmail = getPrefBool("campuscare_notifications_email", false);
$notifyAppointments = getPrefBool("campuscare_notify_appointments", true);
$notifyEvents = getPrefBool("campuscare_notify_events", true);
$notifySystem = getPrefBool("campuscare_notify_system", true);
$notificationTiming = normalizeChoice((string) ($_COOKIE["campuscare_notification_timing"] ?? "24h"), ["15m", "1h", "24h", "3d"], "24h");

$privacyProfileVisible = getPrefBool("campuscare_privacy_profile_visible", true);
$privacyDataSharing = getPrefBool("campuscare_privacy_data_sharing", false);

$sessionIdleTimeout = normalizeChoice((string) ($_COOKIE["campuscare_idle_timeout"] ?? "60"), ["15", "30", "60", "120"], "60");
$trustedBrowser = getPrefBool("campuscare_trusted_browser", false);
$currentSessionIdPreview = substr(session_id(), 0, 10) . "...";
$lastActivityTs = intval($_SESSION["last_activity_at"] ?? 0);
$lastActivityText = $lastActivityTs > 0 ? date("M j, Y g:i A", $lastActivityTs) : "Current login";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? "update_preferences"));

    if ($action === "update_preferences") {
        $darkModeEnabled = isset($_POST["dark_mode"]);
        $notificationsEnabled = isset($_POST["enable_notifications"]);
        $notificationsInAppEnabled = isset($_POST["notifications_in_app"]);
        $notificationsEmailEnabled = isset($_POST["notifications_email"]);
        $appointmentsEnabled = isset($_POST["notify_appointments"]);
        $eventsEnabled = isset($_POST["notify_events"]);
        $systemEnabled = isset($_POST["notify_system"]);
        $timing = normalizeChoice(trim((string) ($_POST["notification_timing"] ?? "24h")), ["15m", "1h", "24h", "3d"], "24h");

        $profileVisible = isset($_POST["privacy_profile_visible"]);
        $dataSharing = isset($_POST["privacy_data_sharing"]);

        $idleTimeout = normalizeChoice(trim((string) ($_POST["idle_timeout"] ?? "60")), ["15", "30", "60", "120"], "60");
        $trustedBrowserEnabled = isset($_POST["trusted_browser"]);

        setPrefCookie("campuscare_dark_mode", $darkModeEnabled ? "true" : "false");
        setPrefCookie("campuscare_notifications", $notificationsEnabled ? "true" : "false");
        setPrefCookie("campuscare_notifications_in_app", $notificationsInAppEnabled ? "true" : "false");
        setPrefCookie("campuscare_notifications_email", $notificationsEmailEnabled ? "true" : "false");
        setPrefCookie("campuscare_notify_appointments", $appointmentsEnabled ? "true" : "false");
        setPrefCookie("campuscare_notify_events", $eventsEnabled ? "true" : "false");
        setPrefCookie("campuscare_notify_system", $systemEnabled ? "true" : "false");
        setPrefCookie("campuscare_notification_timing", $timing);

        setPrefCookie("campuscare_privacy_profile_visible", $profileVisible ? "true" : "false");
        setPrefCookie("campuscare_privacy_data_sharing", $dataSharing ? "true" : "false");

        setPrefCookie("campuscare_idle_timeout", $idleTimeout);
        setPrefCookie("campuscare_trusted_browser", $trustedBrowserEnabled ? "true" : "false");

        $darkMode = $darkModeEnabled;
        $enableNotifications = $notificationsEnabled;
        $notificationsInApp = $notificationsInAppEnabled;
        $notificationsEmail = $notificationsEmailEnabled;
        $notifyAppointments = $appointmentsEnabled;
        $notifyEvents = $eventsEnabled;
        $notifySystem = $systemEnabled;
        $notificationTiming = $timing;

        $privacyProfileVisible = $profileVisible;
        $privacyDataSharing = $dataSharing;

        $sessionIdleTimeout = $idleTimeout;
        $trustedBrowser = $trustedBrowserEnabled;

        $success = "Preferences updated successfully.";
    } elseif ($action === "change_password") {
        $currentPassword = (string) ($_POST["current_password"] ?? "");
        $newPassword = (string) ($_POST["new_password"] ?? "");
        $confirmPassword = (string) ($_POST["confirm_password"] ?? "");

        if ($currentPassword === "" || $newPassword === "" || $confirmPassword === "") {
            $error = "Please complete all password fields.";
        } elseif (strlen($newPassword) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $error = "New password must include uppercase, lowercase, and a number.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New password and confirm password do not match.";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");

            if (!$stmt) {
                $error = "Unable to validate current password.";
            } else {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userRow = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if (!$userRow || !password_verify($currentPassword, (string) $userRow["password"])) {
                    $error = "Current password is incorrect.";
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");

                    if (!$updateStmt) {
                        $error = "Unable to update password right now.";
                    } else {
                        $updateStmt->bind_param("si", $newHash, $userId);

                        if ($updateStmt->execute()) {
                            session_regenerate_id(true);
                            $currentSessionIdPreview = substr(session_id(), 0, 10) . "...";
                            $success = "Password updated successfully.";
                        } else {
                            $error = "Unable to update password right now.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }
    }
}

$_SESSION["last_activity_at"] = time();

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
            <p class="page-subtitle" style="margin-bottom: 24px;">Customize your CampusCare experience and account security</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" class="settings-stack">
                <input type="hidden" name="action" value="update_preferences">

                <div class="card">
                    <h2 class="card-title">Display</h2>

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
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2 class="card-title">Notifications</h2>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Enable Notifications</label>
                                <p class="settings-description">Turn on all CampusCare notifications.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input id="enableNotificationsToggle" type="checkbox" name="enable_notifications" <?php echo $enableNotifications ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label class="settings-label">In-App Notifications</label>
                                <p class="settings-description">Show updates in the top bar notifications panel.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications_in_app" <?php echo $notificationsInApp ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label class="settings-label">Email Notifications</label>
                                <p class="settings-description">Receive email reminders for upcoming appointments.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notifications_email" <?php echo $notificationsEmail ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label class="settings-label">Appointment Updates</label>
                                <p class="settings-description">Get reminders for upcoming sessions and status changes.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notify_appointments" <?php echo $notifyAppointments ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label class="settings-label">Event Updates</label>
                                <p class="settings-description">Get reminders for joined events.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notify_events" <?php echo $notifyEvents ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label class="settings-label">System Updates</label>
                                <p class="settings-description">Get app notices and service updates.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="notify_system" <?php echo $notifySystem ? "checked" : ""; ?> <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item notification-dependent">
                            <div class="settings-content">
                                <label for="notificationTiming" class="settings-label">Notification Timing</label>
                                <p class="settings-description">How early reminders should appear in your top bar.</p>
                            </div>
                            <div class="settings-control settings-select-control">
                                <select id="notificationTiming" name="notification_timing" <?php echo !$enableNotifications ? "disabled" : ""; ?>>
                                    <option value="15m" <?php echo $notificationTiming === "15m" ? "selected" : ""; ?>>15 minutes before</option>
                                    <option value="1h" <?php echo $notificationTiming === "1h" ? "selected" : ""; ?>>1 hour before</option>
                                    <option value="24h" <?php echo $notificationTiming === "24h" ? "selected" : ""; ?>>24 hours before</option>
                                    <option value="3d" <?php echo $notificationTiming === "3d" ? "selected" : ""; ?>>3 days before</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2 class="card-title">Session Management</h2>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Current Session</label>
                                <p class="settings-description">Logged in as <?php echo htmlspecialchars($fullName !== "" ? $fullName : "User"); ?> (<?php echo htmlspecialchars($email !== "" ? $email : "no-email@campuscare.local"); ?>)</p>
                                <p class="settings-description" style="margin-top: 4px;">Session ID: <?php echo htmlspecialchars($currentSessionIdPreview); ?> | Last activity: <?php echo htmlspecialchars($lastActivityText); ?></p>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-content">
                                <label for="idleTimeout" class="settings-label">Auto Logout (Inactivity)</label>
                                <p class="settings-description">Set how long CampusCare keeps you signed in when idle.</p>
                            </div>
                            <div class="settings-control settings-select-control">
                                <select id="idleTimeout" name="idle_timeout">
                                    <option value="15" <?php echo $sessionIdleTimeout === "15" ? "selected" : ""; ?>>15 minutes</option>
                                    <option value="30" <?php echo $sessionIdleTimeout === "30" ? "selected" : ""; ?>>30 minutes</option>
                                    <option value="60" <?php echo $sessionIdleTimeout === "60" ? "selected" : ""; ?>>60 minutes</option>
                                    <option value="120" <?php echo $sessionIdleTimeout === "120" ? "selected" : ""; ?>>2 hours</option>
                                </select>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Trust This Browser</label>
                                <p class="settings-description">Keep this browser trusted for longer login sessions.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="trusted_browser" <?php echo $trustedBrowser ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top: 24px;">
                    <h2 class="card-title">Privacy</h2>

                    <div class="settings-group">
                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Show Profile to Campus Staff</label>
                                <p class="settings-description">Allow counselors and facilitators to view your profile details when needed.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="privacy_profile_visible" <?php echo $privacyProfileVisible ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-content">
                                <label class="settings-label">Share Anonymous Analytics</label>
                                <p class="settings-description">Help improve CampusCare by sharing anonymized usage analytics.</p>
                            </div>
                            <div class="settings-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="privacy_data_sharing" <?php echo $privacyDataSharing ? "checked" : ""; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn">Save Preferences</button>
                </div>
            </form>

            <div class="card" style="margin-top: 24px;">
                <h2 class="card-title">Password and Security</h2>
                <p class="settings-description" style="margin-bottom: 14px;">Use a strong password with mixed characters.</p>

                <form method="POST" class="password-settings-form">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="currentPassword">Current Password</label>
                        <input id="currentPassword" type="password" name="current_password" placeholder="Enter current password" required>
                    </div>

                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <input id="newPassword" type="password" name="new_password" placeholder="At least 8 characters" required>
                        <div class="password-strength-wrap" aria-live="polite">
                            <div class="password-strength-track">
                                <div id="passwordStrengthBar" class="password-strength-bar"></div>
                            </div>
                            <p id="passwordStrengthText" class="password-strength-text">Strength: Too weak</p>
                        </div>
                        <p class="settings-description">Use uppercase, lowercase, and numbers.</p>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input id="confirmPassword" type="password" name="confirm_password" placeholder="Re-enter new password" required>
                    </div>

                    <button type="submit" class="btn">Update Password</button>
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
    const preferencesForm = document.querySelector('form.settings-stack');
    const darkModeToggle = document.querySelector('input[name="dark_mode"]');
    const notificationsToggle = document.getElementById("enableNotificationsToggle");
    const notificationDependents = document.querySelectorAll(".notification-dependent input, .notification-dependent select");
    const newPasswordInput = document.getElementById("newPassword");
    const passwordStrengthBar = document.getElementById("passwordStrengthBar");
    const passwordStrengthText = document.getElementById("passwordStrengthText");

    function applyDarkMode(enabled) {
        document.body.classList.toggle("theme-dark", !!enabled);

        try {
            localStorage.setItem("campuscare_dark_mode", enabled ? "true" : "false");
        } catch (error) {
            // Ignore storage errors and keep cookie-based fallback.
        }
    }

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

    function updateNotificationDependentState() {
        if (!notificationsToggle) {
            return;
        }

        const enabled = notificationsToggle.checked;

        notificationDependents.forEach(function (el) {
            el.disabled = !enabled;
        });
    }

    function scorePassword(password) {
        let score = 0;

        if (password.length >= 8) {
            score += 1;
        }
        if (password.length >= 12) {
            score += 1;
        }
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            score += 1;
        }
        if (/[0-9]/.test(password)) {
            score += 1;
        }
        if (/[^A-Za-z0-9]/.test(password)) {
            score += 1;
        }

        return score;
    }

    function updatePasswordStrength() {
        if (!newPasswordInput || !passwordStrengthBar || !passwordStrengthText) {
            return;
        }

        const password = newPasswordInput.value || "";
        const score = scorePassword(password);
        let width = "8%";
        let label = "Strength: Too weak";
        let color = "#ef4444";

        if (score === 1) {
            width = "24%";
            label = "Strength: Weak";
            color = "#f97316";
        } else if (score === 2) {
            width = "42%";
            label = "Strength: Fair";
            color = "#f59e0b";
        } else if (score === 3) {
            width = "64%";
            label = "Strength: Good";
            color = "#22c55e";
        } else if (score >= 4) {
            width = "100%";
            label = "Strength: Strong";
            color = "#16a34a";
        }

        passwordStrengthBar.style.width = width;
        passwordStrengthBar.style.background = color;
        passwordStrengthText.textContent = label;
    }

    if (darkModeToggle) {
        applyDarkMode(darkModeToggle.checked);

        darkModeToggle.addEventListener("change", function () {
            applyDarkMode(darkModeToggle.checked);
        });
    }

    if (preferencesForm) {
        preferencesForm.addEventListener("submit", function () {
            if (darkModeToggle) {
                applyDarkMode(darkModeToggle.checked);
            }
        });
    }

    if (notificationsToggle) {
        notificationsToggle.addEventListener("change", updateNotificationDependentState);
        updateNotificationDependentState();
    }

    if (newPasswordInput) {
        newPasswordInput.addEventListener("input", updatePasswordStrength);
        updatePasswordStrength();
    }
})();
</script>
</body>
</html>


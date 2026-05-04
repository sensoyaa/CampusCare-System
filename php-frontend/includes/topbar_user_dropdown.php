<?php
// Refactored Profile Dropdown Component
// Notifications are fetched and rendered via JavaScript from the API

// Extract user data from session
$topbarFullName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
$topbarEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$topbarRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$topbarAvatarPath = isset($_SESSION['avatar_path']) ? $_SESSION['avatar_path'] : '';
$topbarAvatarInitial = !empty($topbarFullName) ? strtoupper(substr($topbarFullName, 0, 1)) : 'U';

function prefEnabled($cookieName, $default = true) {
    if (!isset($_COOKIE[$cookieName])) {
        return $default;
    }
    return $_COOKIE[$cookieName] === 'true';
}

// Read notification preferences so settings are applied on every page.
$notificationsEnabled = isset($_SESSION['notifications_enabled'])
    ? (bool) $_SESSION['notifications_enabled']
    : prefEnabled('campuscare_notifications', true);

$notificationsInApp = isset($_SESSION['notifications_in_app'])
    ? (bool) $_SESSION['notifications_in_app']
    : prefEnabled('campuscare_notifications_in_app', true);

// Note: Actual notifications are fetched via /backend/api/notifications.php by JavaScript
?>

<div class="topbar-user topbar-user-refactored">
    <!-- Notifications Menu -->
    <?php if ($notificationsEnabled && $notificationsInApp): ?>
    <div class="notify-menu" aria-label="Notifications">
        <button class="notify-summary notify-toggle" id="notify-trigger" aria-label="Toggle notifications menu" aria-expanded="false" aria-haspopup="dialog" aria-controls="notify-panel" type="button">
            <svg class="notify-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
            </svg>
            <span class="notify-badge" style="display: none;" data-count="0"></span>
        </button>

        <div class="notify-panel notify-dropdown" id="notify-panel" role="dialog" aria-label="Notifications panel" hidden>
            <div class="notify-head">
                <div>
                    <h3 class="notify-title">Notifications</h3>
                    <p class="notify-subtitle">Latest updates for your account</p>
                </div>
                <div class="notify-actions">
                    <button class="notify-mark-all-btn" aria-label="Mark all notifications as read" type="button">Mark all</button>
                    <button class="notify-clear-btn" aria-label="Clear all notifications" type="button">Clear</button>
                </div>
            </div>

            <div class="notify-list" role="list"></div>

            <div class="notify-empty">
                <span class="notify-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path fill="currentColor" d="M12 3.75a4.75 4.75 0 0 1 4.75 4.75v2.33c0 .9.28 1.78.81 2.51l1.23 1.71a1 1 0 0 1-.82 1.59H5.03a1 1 0 0 1-.82-1.59l1.23-1.71c.53-.73.81-1.61.81-2.51V8.5A4.75 4.75 0 0 1 12 3.75Zm0 16.5a2.74 2.74 0 0 0 2.58-1.83H9.42A2.74 2.74 0 0 0 12 20.25Z"/>
                    </svg>
                </span>
                <p class="notify-empty-title">No notifications yet.</p>
                <p class="notify-empty-copy">We’ll show approvals, reminders, referrals, and account activity here.</p>
            </div>

            <div class="notify-footer">
                <a href="/campuscare-api/php-frontend/pages/users/settings.php">Notification Settings</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="topbar-divider" aria-hidden="true"></div>

    <!-- Refactored Profile Menu -->
    <div class="profile-section">
        <!-- Profile Trigger Button -->
        <button class="profile-trigger" id="profile-trigger" aria-label="Open profile menu" aria-expanded="false" aria-haspopup="menu" type="button">
            <span class="profile-avatar">
                <?php if ($topbarAvatarPath !== ""): ?>
                    <img src="<?php echo htmlspecialchars($topbarAvatarPath); ?>" alt="<?php echo htmlspecialchars($topbarFullName); ?>" class="avatar-image" loading="lazy">
                <?php else: ?>
                    <span class="avatar-initials"><?php echo htmlspecialchars($topbarAvatarInitial); ?></span>
                <?php endif; ?>
            </span>
            <span class="profile-label">
                <span class="profile-name"><?php echo htmlspecialchars($topbarFullName !== "" ? $topbarFullName : "User"); ?></span>
                <svg class="dropdown-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M7 10l5 5 5-5z"></path>
                </svg>
            </span>
        </button>

        <!-- Profile Menu Dropdown -->
        <div class="profile-menu" id="profile-menu" role="menu" aria-orientation="vertical" aria-labelledby="profile-trigger" hidden>
            <!-- Profile Header Card -->
            <div class="profile-header">
                <div class="profile-header-avatar">
                    <?php if ($topbarAvatarPath !== ""): ?>
                        <img src="<?php echo htmlspecialchars($topbarAvatarPath); ?>" alt="<?php echo htmlspecialchars($topbarFullName); ?>" class="header-avatar-image" loading="lazy">
                    <?php else: ?>
                        <span class="header-avatar-initials"><?php echo htmlspecialchars($topbarAvatarInitial); ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-header-info">
                    <p class="profile-header-name"><?php echo htmlspecialchars($topbarFullName !== "" ? $topbarFullName : "User"); ?></p>
                    <p class="profile-header-role"><?php echo htmlspecialchars($topbarRole); ?></p>
                    <p class="profile-header-email"><?php echo htmlspecialchars($topbarEmail !== "" ? $topbarEmail : "no-email@campuscare.local"); ?></p>
                </div>
            </div>

            <!-- Menu Divider -->
            <div class="menu-divider" role="separator"></div>

            <!-- Primary Actions -->
            <a href="/campuscare-api/php-frontend/pages/users/edit_profile.php" class="profile-menu-item" role="menuitem" data-menu-item="edit-profile">
                <svg class="menu-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z"/>
                </svg>
                <span class="menu-item-label">Edit Profile</span>
                <svg class="menu-item-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L12.17 12z"/>
                </svg>
            </a>

            <a href="/campuscare-api/php-frontend/pages/users/settings.php" class="profile-menu-item" role="menuitem" data-menu-item="settings">
                <svg class="menu-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M19.43 12.98a7.93 7.93 0 0 0 .07-.98 7.93 7.93 0 0 0-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.14 7.14 0 0 0-1.69-.98l-.38-2.65a.5.5 0 0 0-.49-.42h-4a.5.5 0 0 0-.49.42L9.13 5.07a7.14 7.14 0 0 0-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64L4.57 11a7.93 7.93 0 0 0-.07.98 7.93 7.93 0 0 0 .07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.22l2.49-1a7.14 7.14 0 0 0 1.69.98l.38 2.65a.5.5 0 0 0 .49.42h4a.5.5 0 0 0 .49-.42l.38-2.65a7.14 7.14 0 0 0 1.69-.98l2.49 1a.5.5 0 0 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64zM12 15.5a3.5 3.5 0 1 1 3.5-3.5 3.5 3.5 0 0 1-3.5 3.5z"/>
                </svg>
                <span class="menu-item-label">Settings & Preferences</span>
                <svg class="menu-item-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L12.17 12z"/>
                </svg>
            </a>

            <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="profile-menu-item" role="menuitem" data-menu-item="dashboard">
                <svg class="menu-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M3 13h8V3H3zm0 8h8v-6H3zm10 0h8V11h-8zm0-18v6h8V3z"/>
                </svg>
                <span class="menu-item-label">Dashboard</span>
                <svg class="menu-item-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L12.17 12z"/>
                </svg>
            </a>

            <!-- Logout Section -->
            <div class="menu-divider" role="separator"></div>

            <a href="/campuscare-api/php-frontend/pages/auth/logout.php" class="profile-menu-item logout-item" role="menuitem" data-menu-item="logout">
                <svg class="menu-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8v-2H4V5z"/>
                </svg>
                <span class="menu-item-label">Sign Out</span>
            </a>
        </div>
    </div>
</div>

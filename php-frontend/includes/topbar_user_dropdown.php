<?php
// Refactored Profile Dropdown Component
// Enhanced with better semantics, accessibility, and modern UI/UX

// Extract user data from session
$topbarFullName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
$topbarEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$topbarRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$topbarAvatarPath = isset($_SESSION['avatar_path']) ? $_SESSION['avatar_path'] : '';
$topbarAvatarInitial = !empty($topbarFullName) ? strtoupper(substr($topbarFullName, 0, 1)) : 'U';

// Handle notifications
$notificationsEnabled = isset($_SESSION['notifications_enabled']) ? $_SESSION['notifications_enabled'] : true;
$notificationsInApp = isset($_SESSION['notifications_in_app']) ? $_SESSION['notifications_in_app'] : true;
$notifyAppointments = isset($_SESSION['notify_appointments']) ? $_SESSION['notify_appointments'] : true;
$notifyEvents = isset($_SESSION['notify_events']) ? $_SESSION['notify_events'] : true;
$notifySystem = isset($_SESSION['notify_system']) ? $_SESSION['notify_system'] : true;

// Build notification items array
$notificationItems = [];
if (isset($_SESSION['appointments']) && $notifyAppointments) {
    foreach ($_SESSION['appointments'] as $appt) {
        $notificationItems[] = [
            'type' => 'appointment',
            'title' => 'Appointment: ' . htmlspecialchars($appt['title'] ?? 'Upcoming Appointment'),
            'message' => htmlspecialchars($appt['description'] ?? ''),
            'time' => htmlspecialchars($appt['date'] ?? 'Scheduled'),
            'icon' => 'calendar'
        ];
    }
}

if (isset($_SESSION['events']) && $notifyEvents) {
    foreach ($_SESSION['events'] as $event) {
        $notificationItems[] = [
            'type' => 'event',
            'title' => 'Event: ' . htmlspecialchars($event['title'] ?? 'Upcoming Event'),
            'message' => htmlspecialchars($event['description'] ?? ''),
            'time' => htmlspecialchars($event['date'] ?? 'Scheduled'),
            'icon' => 'event'
        ];
    }
}

if (isset($_SESSION['system_notifications']) && $notifySystem) {
    foreach ($_SESSION['system_notifications'] as $notif) {
        $notificationItems[] = [
            'type' => 'system',
            'title' => htmlspecialchars($notif['title'] ?? 'System Notification'),
            'message' => htmlspecialchars($notif['message'] ?? ''),
            'time' => htmlspecialchars($notif['timestamp'] ?? 'Now'),
            'icon' => 'info'
        ];
    }
}

$notificationCount = count($notificationItems);
?>

<div class="topbar-user topbar-user-refactored">
    <!-- Notifications Menu (if enabled) -->
    <?php if ($notificationsEnabled && $notificationsInApp): ?>
    <details class="notify-menu" aria-label="Notifications">
        <summary class="notify-summary" aria-label="Toggle notifications menu">
            <svg class="notify-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
            </svg>
            <?php if ($notificationCount > 0): ?>
            <span class="notify-badge" data-count="<?php echo $notificationCount; ?>"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
            <?php endif; ?>
        </summary>

        <div class="notify-panel">
            <?php if ($notificationCount > 0): ?>
                <div class="notify-header">
                    <h3>Notifications</h3>
                    <button class="notify-clear-btn" aria-label="Clear all notifications" type="button">Clear</button>
                </div>
                <div class="notify-list">
                    <?php foreach ($notificationItems as $notif): ?>
                    <div class="notify-item" data-type="<?php echo $notif['type']; ?>">
                        <div class="notify-item-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <?php if ($notif['icon'] === 'calendar'): ?>
                                    <path fill="currentColor" d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 16H5V8h14v11z"/>
                                <?php elseif ($notif['icon'] === 'event'): ?>
                                    <path fill="currentColor" d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                                <?php else: ?>
                                    <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                                <?php endif; ?>
                            </svg>
                        </div>
                        <div class="notify-item-content">
                            <p class="notify-item-title"><?php echo $notif['title']; ?></p>
                            <p class="notify-item-message"><?php echo $notif['message']; ?></p>
                            <span class="notify-item-time"><?php echo $notif['time']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="notify-empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 22c1.1 0 2-.9 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                    </svg>
                    <p>No notifications</p>
                </div>
            <?php endif; ?>
        </div>
    </details>
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

<?php
$topbarFullName = trim((string) ($_SESSION["full_name"] ?? ""));
$topbarEmail = trim((string) ($_SESSION["email"] ?? ""));
$topbarRole = trim((string) ($_SESSION["role"] ?? "Student"));
$topbarAvatarPath = trim((string) ($_SESSION["avatar_path"] ?? ""));
$topbarAvatarInitial = strtoupper(substr($topbarFullName !== "" ? $topbarFullName : "U", 0, 1));
$topbarUserId = intval($_SESSION["user_id"] ?? 0);

$notificationsEnabled = (!isset($_COOKIE["campuscare_notifications"]) || $_COOKIE["campuscare_notifications"] === "true");
$notificationsInApp = (!isset($_COOKIE["campuscare_notifications_in_app"]) || $_COOKIE["campuscare_notifications_in_app"] === "true");
$notifyAppointments = (!isset($_COOKIE["campuscare_notify_appointments"]) || $_COOKIE["campuscare_notify_appointments"] === "true");
$notifyEvents = (!isset($_COOKIE["campuscare_notify_events"]) || $_COOKIE["campuscare_notify_events"] === "true");
$notifySystem = (!isset($_COOKIE["campuscare_notify_system"]) || $_COOKIE["campuscare_notify_system"] === "true");
$notificationTiming = (string) ($_COOKIE["campuscare_notification_timing"] ?? "24h");

$timingWindowMap = [
    "15m" => 15 * 60,
    "1h" => 60 * 60,
    "24h" => 24 * 60 * 60,
    "3d" => 3 * 24 * 60 * 60,
];
$timingWindowSeconds = $timingWindowMap[$notificationTiming] ?? $timingWindowMap["24h"];

$notificationItems = [];

if ($notificationsEnabled && $notificationsInApp && isset($conn) && ($conn instanceof mysqli) && $topbarUserId > 0) {
    $now = time();

    if ($notifyAppointments) {
        try {
            $appointmentStmt = $conn->prepare(
                "SELECT id, service, appointment_date, appointment_time, status
                FROM appointments
                WHERE user_id = ? OR counselor_id = ?
                ORDER BY appointment_date ASC, appointment_time ASC
                LIMIT 20"
            );

            if ($appointmentStmt) {
                $appointmentStmt->bind_param("ii", $topbarUserId, $topbarUserId);
                $appointmentStmt->execute();
                $appointmentResult = $appointmentStmt->get_result();

                while ($appointmentResult && ($row = $appointmentResult->fetch_assoc())) {
                    $status = (string) ($row["status"] ?? "");

                    if ($status === "Cancelled" || $status === "Rejected") {
                        continue;
                    }

                    $dateTimeRaw = trim((string) ($row["appointment_date"] ?? "")) . " " . trim((string) ($row["appointment_time"] ?? ""));
                    $dueTs = strtotime($dateTimeRaw);

                    if ($dueTs === false || $dueTs < $now) {
                        continue;
                    }

                    $secondsUntil = $dueTs - $now;

                    if ($secondsUntil <= $timingWindowSeconds) {
                        $service = trim((string) ($row["service"] ?? "Counseling"));
                        $notificationItems[] = [
                            "ts" => $dueTs,
                            "icon" => "AP",
                            "title" => "Appointment Reminder",
                            "body" => $service . " at " . date("M j, g:i A", $dueTs),
                            "href" => "/campuscare-api/php-frontend/pages/appointments/manage_appointments.php",
                        ];
                    }
                }

                $appointmentStmt->close();
            }
        } catch (Throwable $exception) {
            // Keep dashboard usable even when notification queries fail.
        }
    }

    if ($notifyEvents) {
        try {
            $eventStmt = $conn->prepare(
                "SELECT e.id, e.title, e.event_date, e.event_time
                FROM event_participants ep
                INNER JOIN events e ON e.id = ep.event_id
                WHERE ep.user_id = ?
                ORDER BY e.event_date ASC, e.event_time ASC
                LIMIT 20"
            );

            if ($eventStmt) {
                $eventStmt->bind_param("i", $topbarUserId);
                $eventStmt->execute();
                $eventResult = $eventStmt->get_result();

                while ($eventResult && ($row = $eventResult->fetch_assoc())) {
                    $dateTimeRaw = trim((string) ($row["event_date"] ?? "")) . " " . trim((string) ($row["event_time"] ?? ""));
                    $dueTs = strtotime($dateTimeRaw);

                    if ($dueTs === false || $dueTs < $now) {
                        continue;
                    }

                    $secondsUntil = $dueTs - $now;

                    if ($secondsUntil <= $timingWindowSeconds) {
                        $title = trim((string) ($row["title"] ?? "Campus Event"));
                        $notificationItems[] = [
                            "ts" => $dueTs,
                            "icon" => "EV",
                            "title" => "Event Reminder",
                            "body" => $title . " on " . date("M j, g:i A", $dueTs),
                            "href" => "/campuscare-api/php-frontend/pages/events/events.php",
                        ];
                    }
                }

                $eventStmt->close();
            }
        } catch (Throwable $exception) {
            // Keep dashboard usable even when notification queries fail.
        }
    }
}

if ($notifySystem) {
    $notificationItems[] = [
        "ts" => time(),
        "icon" => "CC",
        "title" => "CampusCare",
        "body" => "Review your profile and settings to keep recommendations accurate.",
        "href" => "/campuscare-api/php-frontend/pages/users/settings.php",
    ];
}

usort($notificationItems, function ($a, $b) {
    return intval($a["ts"] ?? 0) <=> intval($b["ts"] ?? 0);
});

$notificationItems = array_slice($notificationItems, 0, 8);
$notificationCount = count($notificationItems);
?>
<div class="topbar-user topbar-user-modern">
    <details class="notify-menu">
        <summary class="notify-toggle" aria-label="Notifications">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6h-1V11a6 6 0 1 0-12 0v5H5a1 1 0 0 0 0 2h14a1 1 0 1 0 0-2Zm-3 0H8V11a4 4 0 1 1 8 0v5Z"></path>
            </svg>
            <?php if ($notificationCount > 0): ?>
                <span class="notify-badge"><?php echo intval($notificationCount); ?></span>
            <?php endif; ?>
        </summary>
        <div class="notify-dropdown" role="menu" aria-label="Notifications panel">
            <div class="notify-head">
                <p class="notify-title font-bold">Notifications</p>
            </div>

            <?php if (!$notificationsEnabled || !$notificationsInApp): ?>
                <div class="notify-empty">Notifications are turned off in Settings.</div>
            <?php elseif ($notificationCount === 0): ?>
                <div class="notify-empty">No updates available.</div>
            <?php else: ?>
                <?php foreach ($notificationItems as $item): ?>
                    <a href="<?php echo htmlspecialchars((string) ($item["href"] ?? "#")); ?>" class="notify-item">
                        <span class="notify-icon"><?php echo htmlspecialchars((string) ($item["icon"] ?? "CC")); ?></span>
                        <span class="notify-copy">
                            <span class="notify-item-title"><?php echo htmlspecialchars((string) ($item["title"] ?? "Update")); ?></span>
                            <span class="notify-item-body"><?php echo htmlspecialchars((string) ($item["body"] ?? "")); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="notify-footer">
                <a href="/campuscare-api/php-frontend/pages/users/settings.php">Notification Settings</a>
            </div>
        </div>
    </details>

    <div class="topbar-divider" aria-hidden="true"></div>
    <div class="profile-pill">
        <button class="profile-menu-toggle modern-profile-toggle" aria-label="Profile menu" aria-expanded="false" type="button">
            <span class="modern-avatar">
                <?php if ($topbarAvatarPath !== ""): ?>
                    <img src="<?php echo htmlspecialchars($topbarAvatarPath); ?>" alt="Profile avatar" class="modern-avatar-image">
                <?php else: ?>
                    <?php echo htmlspecialchars($topbarAvatarInitial); ?>
                <?php endif; ?>
            </span>
            <span class="modern-name"><?php echo htmlspecialchars($topbarFullName !== "" ? $topbarFullName : "User"); ?></span>
            <svg class="modern-caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M7 10l5 5 5-5z"></path>
            </svg>
        </button>

        <div class="profile-dropdown modern-profile-dropdown">
            <div class="modern-dropdown-head">
                <p class="modern-dropdown-role"><?php echo htmlspecialchars($topbarRole); ?></p>
                <p class="modern-dropdown-email"><?php echo htmlspecialchars($topbarEmail !== "" ? $topbarEmail : "no-email@campuscare.local"); ?></p>
            </div>
            <a href="/campuscare-api/php-frontend/pages/users/edit_profile.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4z"/></svg>
                <span>My Profile</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/users/settings.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.14 12.94a7.14 7.14 0 0 0 .05-.94 7.14 7.14 0 0 0-.05-.94l2.03-1.58a.49.49 0 0 0 .12-.63l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.28 7.28 0 0 0-1.63-.94L14.4 2.8a.49.49 0 0 0-.49-.4h-3.82a.49.49 0 0 0-.49.4l-.36 2.53a7.28 7.28 0 0 0-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.7 8.85a.49.49 0 0 0 .12.63l2.03 1.58a7.14 7.14 0 0 0-.05.94 7.14 7.14 0 0 0 .05.94L2.82 14.52a.49.49 0 0 0-.12.63l1.92 3.32a.5.5 0 0 0 .6.22l2.39-.96a7.28 7.28 0 0 0 1.63.94l.36 2.53a.49.49 0 0 0 .49.4h3.82a.49.49 0 0 0 .49-.4l.36-2.53a7.28 7.28 0 0 0 1.63-.94l2.39.96a.5.5 0 0 0 .6-.22l1.92-3.32a.49.49 0 0 0-.12-.63zM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5z"/></svg>
                <span>Account Management</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/dashboard/dashboard.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 13h8V3H3zm0 8h8v-6H3zm10 0h8V11h-8zm0-18v6h8V3z"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="/campuscare-api/php-frontend/pages/users/settings.php" class="profile-dropdown-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19.43 12.98a7.93 7.93 0 0 0 .07-.98 7.93 7.93 0 0 0-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.14 7.14 0 0 0-1.69-.98l-.38-2.65a.5.5 0 0 0-.49-.42h-4a.5.5 0 0 0-.49.42L9.13 5.07a7.14 7.14 0 0 0-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64L4.57 11a7.93 7.93 0 0 0-.07.98 7.93 7.93 0 0 0 .07.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.22l2.49-1a7.14 7.14 0 0 0 1.69.98l.38 2.65a.5.5 0 0 0 .49.42h4a.5.5 0 0 0 .49-.42l.38-2.65a7.14 7.14 0 0 0 1.69-.98l2.49 1a.5.5 0 0 0 .61-.22l2-3.46a.5.5 0 0 0-.12-.64zM12 15.5a3.5 3.5 0 1 1 3.5-3.5 3.5 3.5 0 0 1-3.5 3.5z"/></svg>
                <span>Settings</span>
            </a>
            <div class="profile-dropdown-divider"></div>
            <a href="/campuscare-api/php-frontend/pages/auth/logout.php" class="profile-dropdown-item logout-item">
                <svg class="dropdown-item-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 17v-2h4V9h-4V7h6v10zM6 19V5h7V3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7v-2zm12.59-7L16 9.41 17.41 8 22 12l-4.59 4-1.41-1.41z"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

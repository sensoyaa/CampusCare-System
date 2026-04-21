<?php
require_once __DIR__ . "/auth.php";

$role = normalizeRole($_SESSION["role"] ?? "Student");

$navItems = [
    "Student" => [
        ["Dashboard", "dashboard.php", "dashboard"],
        ["Book Appointment", "book_appointment.php", "calendar-plus"],
        ["My Schedule", "schedule.php", "calendar"],
        ["Mental Health Test", "mental_health_test.php", "brain"],
        ["Brown Bag Sessions", "events.php", "users"],
    ],
    "Administrator" => [
        ["Dashboard", "dashboard.php", "dashboard"],
        ["Manage Users", "manage_users.php", "user-plus"],
        ["Manage Appointments", "manage_appointments.php", "calendar"],
        ["Manage Events", "events.php", "users"],
        ["View Reports", "reports.php", "report"],
    ],
    "Counselor" => [
        ["Dashboard", "dashboard.php", "dashboard"],
        ["View Appointments", "schedule.php", "calendar"],
        ["Manage Schedule", "manage_schedule.php", "clock"],
        ["Session Feedback", "session_feedback.php", "message"],
    ],
    "Facilitator" => [
        ["Dashboard", "dashboard.php", "dashboard"],
        ["Manage Events", "events.php", "users"],
        ["View Participants", "view_participants.php", "eye"],
    ],
    "Instructor" => [
        ["Dashboard", "dashboard.php", "dashboard"],
        ["Student Status", "student_status.php", "eye"],
        ["View Events", "events.php", "calendar"],
    ],
];

function sidebarIconSvg($name)
{
    $icons = [
        "dashboard" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="2"/></svg>',
        "plus" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "calendar-plus" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10H21" stroke="currentColor" stroke-width="2"/><path d="M8 3V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16 3V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 13V18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9.5 15.5H14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "calendar" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="2"/><path d="M3 10H21" stroke="currentColor" stroke-width="2"/><path d="M8 3V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16 3V7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "clock" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 7V12L15.5 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        "settings" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 3.8H14L14.7 6.2C15.2 6.4 15.6 6.6 16 6.9L18.3 5.9L20.2 7.8L19.2 10.1C19.5 10.5 19.7 10.9 19.9 11.4L22.3 12.1V15.9L19.9 16.6C19.7 17.1 19.5 17.5 19.2 17.9L20.2 20.2L18.3 22.1L16 21.1C15.6 21.4 15.2 21.6 14.7 21.8L14 24.2H10L9.3 21.8C8.8 21.6 8.4 21.4 8 21.1L5.7 22.1L3.8 20.2L4.8 17.9C4.5 17.5 4.3 17.1 4.1 16.6L1.7 15.9V12.1L4.1 11.4C4.3 10.9 4.5 10.5 4.8 10.1L3.8 7.8L5.7 5.9L8 6.9C8.4 6.6 8.8 6.4 9.3 6.2L10 3.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="12" cy="14" r="2.7" stroke="currentColor" stroke-width="1.8"/></svg>',
        "message" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 6.5C5 5.12 6.12 4 7.5 4H16.5C17.88 4 19 5.12 19 6.5V13.5C19 14.88 17.88 16 16.5 16H9L5 19V6.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
        "users" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="9" r="3" stroke="currentColor" stroke-width="2"/><circle cx="17" cy="10" r="2" stroke="currentColor" stroke-width="2"/><path d="M3 19C3 16.79 5.24 15 8 15H10C12.76 15 15 16.79 15 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 18.5C15.39 17.11 16.68 16 18.2 16H18.8C20.57 16 22 17.43 22 19.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "brain" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 4.5C7.34 4.5 6 5.84 6 7.5V8.25C4.76 8.73 4 9.9 4 11.25C4 12.6 4.76 13.77 6 14.25V15C6 16.66 7.34 18 9 18H9.5V4.5H9Z" stroke="currentColor" stroke-width="2"/><path d="M15 4.5C16.66 4.5 18 5.84 18 7.5V8.25C19.24 8.73 20 9.9 20 11.25C20 12.6 19.24 13.77 18 14.25V15C18 16.66 16.66 18 15 18H14.5V4.5H15Z" stroke="currentColor" stroke-width="2"/><path d="M9.5 8H14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9.5 12H14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9.5 16H14.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "user-plus" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M3 19C3 16.79 5.24 15 8 15H10C12.76 15 15 16.79 15 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 8V14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 11H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "report" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 8H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 12H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M8 16H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "eye" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 12C4.2 8.5 7.4 6 12 6C16.6 6 19.8 8.5 21.5 12C19.8 15.5 16.6 18 12 18C7.4 18 4.2 15.5 2.5 12Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>',
        "user" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8" r="3.2" stroke="currentColor" stroke-width="2"/><path d="M5 19C5 16.24 7.24 14 10 14H14C16.76 14 19 16.24 19 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "edit" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 20L8.2 19.2L18.1 9.3C18.88 8.52 18.88 7.26 18.1 6.48L17.52 5.9C16.74 5.12 15.48 5.12 14.7 5.9L4.8 15.8L4 20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M13.8 6.8L17.2 10.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "search" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/><path d="M16 16L20.5 20.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "check-circle" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M8.5 12.3L10.8 14.5L15.5 9.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        "x-circle" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M9 9L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 9L9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "pin" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21C12 21 5 14.9 5 10.1C5 6.18 8.13 3 12 3C15.87 3 19 6.18 19 10.1C19 14.9 12 21 12 21Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="2"/></svg>',
        "trend" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 16L9 11L13 15L20 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 8H20V13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        "trash" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 7V5.5C9 4.67 9.67 4 10.5 4H13.5C14.33 4 15 4.67 15 5.5V7" stroke="currentColor" stroke-width="2"/><path d="M7 7L8 19C8.07 19.82 8.75 20.45 9.57 20.45H14.43C15.25 20.45 15.93 19.82 16 19L17 7" stroke="currentColor" stroke-width="2"/><path d="M10 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        "logout" => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4H6C4.9 4 4 4.9 4 6V18C4 19.1 4.9 20 6 20H10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 16L18 12L14 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 12H9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ];

    return $icons[$name] ?? $icons["dashboard"];
}

$currentPage = basename($_SERVER["PHP_SELF"]);
$items = $navItems[$role] ?? $navItems["Student"];
?>

<aside class="sidebar">
    <div class="brand">
        <img src="../images/logo.png" alt="CampusCare">
        <div>
            <h2>CampusCare</h2>
            <p>Balanced. Supported. Thriving.</p>
        </div>
    </div>

    <nav class="nav">
        <?php foreach ($items as $item): ?>
            <?php
                $title = $item[0];
                $url = $item[1];
                $icon = $item[2];
                $active = $currentPage === $url ? "active" : "";
            ?>
            <a href="<?php echo $url; ?>" class="<?php echo $active; ?>">
                <span class="nav-icon"><?php echo sidebarIconSvg($icon); ?></span>
                <span><?php echo htmlspecialchars($title); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <p>Role: <strong><?php echo htmlspecialchars($role); ?></strong></p>
        <a href="logout.php" class="logout">
            <span class="nav-icon"><?php echo sidebarIconSvg("logout"); ?></span>
            <span>Log Out</span>
        </a>
    </div>
</aside>
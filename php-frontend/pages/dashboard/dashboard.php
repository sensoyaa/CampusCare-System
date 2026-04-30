<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Dashboard";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "User";
$userId = intval($_SESSION["user_id"] ?? 0);

$pendingCount = 0;
$upcomingAppointments = [];
$counselorTodaySessions = 0;
$counselorPendingNotes = 0;
$counselorWeekSessions = 0;
$counselorTodayAppointments = [];
$adminTotalUsers = 0;
$adminTodayAppointments = 0;
$adminPendingApprovals = 0;
$adminEventsThisMonth = 0;
$facilitatorYourEvents = 0;
$facilitatorTotalParticipants = 0;
$facilitatorThisWeek = 0;
$facilitatorUpcomingSessions = [];
$instructorStudentsMonitored = 0;
$instructorAvailableEvents = 0;
$instructorStudentOverview = [];

if ($role === "Student" && $userId > 0) {
    $pendingStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM appointments
         WHERE user_id = ?
           AND COALESCE(NULLIF(status, ''), 'Pending') = 'Pending'"
    );
    $pendingStmt->bind_param("i", $userId);
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result()->fetch_assoc();
    $pendingCount = intval($pendingResult["total"] ?? 0);
    $pendingStmt->close();

    $apptStmt = $conn->prepare(
        "SELECT
            service,
            counselor,
            appointment_date,
            appointment_time,
            COALESCE(NULLIF(status, ''), 'Pending') AS status
         FROM appointments
         WHERE user_id = ?
           AND appointment_date >= CURDATE()
         ORDER BY appointment_date ASC, appointment_time ASC
         LIMIT 3"
    );
    $apptStmt->bind_param("i", $userId);
    $apptStmt->execute();
    $apptResult = $apptStmt->get_result();

    while ($row = $apptResult->fetch_assoc()) {
        $upcomingAppointments[] = $row;
    }

    $apptStmt->close();
} elseif ($role === "Counselor" && $userId > 0) {
        $todayStmt = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE counselor_id = ?
                     AND appointment_date = CURDATE()
                     AND COALESCE(NULLIF(status, ''), 'Pending') <> 'Cancelled'"
        );
        $todayStmt->bind_param("i", $userId);
        $todayStmt->execute();
        $todayResult = $todayStmt->get_result()->fetch_assoc();
        $counselorTodaySessions = intval($todayResult["total"] ?? 0);
        $todayStmt->close();

        $pendingNotesStmt = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM appointments a
                 LEFT JOIN session_feedback sf ON sf.appointment_id = a.id
                 WHERE a.counselor_id = ?
                     AND COALESCE(NULLIF(a.status, ''), 'Pending') = 'Approved'
                     AND (sf.notes IS NULL OR TRIM(sf.notes) = '')"
        );
        $pendingNotesStmt->bind_param("i", $userId);
        $pendingNotesStmt->execute();
        $pendingNotesResult = $pendingNotesStmt->get_result()->fetch_assoc();
        $counselorPendingNotes = intval($pendingNotesResult["total"] ?? 0);
        $pendingNotesStmt->close();

        $weekStmt = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE counselor_id = ?
                     AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)
                     AND COALESCE(NULLIF(status, ''), 'Pending') <> 'Cancelled'"
        );
        $weekStmt->bind_param("i", $userId);
        $weekStmt->execute();
        $weekResult = $weekStmt->get_result()->fetch_assoc();
        $counselorWeekSessions = intval($weekResult["total"] ?? 0);
        $weekStmt->close();

        $todayAppointmentsStmt = $conn->prepare(
                "SELECT
                        a.id,
                        a.service,
                        a.appointment_time,
                        COALESCE(NULLIF(a.status, ''), 'Pending') AS status,
                        u.full_name AS student_name,
                        u.student_id
                 FROM appointments a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.counselor_id = ?
                     AND a.appointment_date = CURDATE()
                     AND COALESCE(NULLIF(a.status, ''), 'Pending') <> 'Cancelled'
                 ORDER BY a.appointment_time ASC"
        );
        $todayAppointmentsStmt->bind_param("i", $userId);
        $todayAppointmentsStmt->execute();
        $todayAppointmentsResult = $todayAppointmentsStmt->get_result();

        while ($row = $todayAppointmentsResult->fetch_assoc()) {
                $counselorTodayAppointments[] = $row;
        }

        $todayAppointmentsStmt->close();
} elseif ($role === "Administrator") {
    $totalUsersResult = $conn->query("SELECT COUNT(*) AS total FROM users");
    if ($totalUsersResult && ($row = $totalUsersResult->fetch_assoc())) {
        $adminTotalUsers = intval($row["total"] ?? 0);
    }

    $todayAppointmentsResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM appointments
         WHERE appointment_date = CURDATE()"
    );
    if ($todayAppointmentsResult && ($row = $todayAppointmentsResult->fetch_assoc())) {
        $adminTodayAppointments = intval($row["total"] ?? 0);
    }

    $pendingApprovalsResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM appointments
         WHERE COALESCE(NULLIF(status, ''), 'Pending') = 'Pending'"
    );
    if ($pendingApprovalsResult && ($row = $pendingApprovalsResult->fetch_assoc())) {
        $adminPendingApprovals = intval($row["total"] ?? 0);
    }

    $eventsThisMonthResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM events
         WHERE MONTH(event_date) = MONTH(CURDATE())
           AND YEAR(event_date) = YEAR(CURDATE())"
    );
    if ($eventsThisMonthResult && ($row = $eventsThisMonthResult->fetch_assoc())) {
        $adminEventsThisMonth = intval($row["total"] ?? 0);
    }
} elseif ($role === "Facilitator") {
    $monthStart = date("Y-m-01");
    $monthEnd = date("Y-m-t");
    $weekStart = date("Y-m-d", strtotime("monday this week"));
    $weekEnd = date("Y-m-d", strtotime("sunday this week"));

    $eventsMonthStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM events
         WHERE event_date BETWEEN ? AND ?"
    );
    $eventsMonthStmt->bind_param("ss", $monthStart, $monthEnd);
    $eventsMonthStmt->execute();
    $eventsMonthResult = $eventsMonthStmt->get_result()->fetch_assoc();
    $facilitatorYourEvents = intval($eventsMonthResult["total"] ?? 0);
    $eventsMonthStmt->close();

    $participantsResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM mental_health_tests"
    );
    if ($participantsResult && ($row = $participantsResult->fetch_assoc())) {
        $facilitatorTotalParticipants = intval($row["total"] ?? 0);
    }

    $eventsWeekStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM events
         WHERE event_date BETWEEN ? AND ?"
    );
    $eventsWeekStmt->bind_param("ss", $weekStart, $weekEnd);
    $eventsWeekStmt->execute();
    $eventsWeekResult = $eventsWeekStmt->get_result()->fetch_assoc();
    $facilitatorThisWeek = intval($eventsWeekResult["total"] ?? 0);
    $eventsWeekStmt->close();

    $upcomingEventsStmt = $conn->prepare(
        "SELECT id, title, event_date, event_time
         FROM events
         WHERE event_date >= CURDATE()
         ORDER BY event_date ASC, event_time ASC
         LIMIT 5"
    );
    $upcomingEventsStmt->execute();
    $upcomingEventsResult = $upcomingEventsStmt->get_result();

    while ($row = $upcomingEventsResult->fetch_assoc()) {
        $facilitatorUpcomingSessions[] = [
            "id" => intval($row["id"] ?? 0),
            "title" => (string) ($row["title"] ?? "Event"),
            "date" => date(
                "M j, g:i A",
                strtotime((string) ($row["event_date"] ?? "") . " " . (string) ($row["event_time"] ?? "00:00:00"))
            ),
            "participants" => 0,
        ];
    }

    $upcomingEventsStmt->close();
} elseif ($role === "Instructor") {
    $studentsResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM users
         WHERE role = 'Student'"
    );
    if ($studentsResult && ($row = $studentsResult->fetch_assoc())) {
        $instructorStudentsMonitored = intval($row["total"] ?? 0);
    }

    $eventsResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM events
         WHERE event_date >= CURDATE()"
    );
    if ($eventsResult && ($row = $eventsResult->fetch_assoc())) {
        $instructorAvailableEvents = intval($row["total"] ?? 0);
    }

    $overviewResult = $conn->query(
        "SELECT
            u.full_name AS name,
            u.student_id,
            COUNT(a.id) AS sessions
         FROM users u
         LEFT JOIN appointments a ON u.id = a.user_id
         WHERE u.role = 'Student'
         GROUP BY u.id, u.full_name, u.student_id
         ORDER BY sessions DESC, u.full_name ASC
         LIMIT 5"
    );

    while ($overviewResult && ($row = $overviewResult->fetch_assoc())) {
        $sessions = intval($row["sessions"] ?? 0);
        $status = "No sessions";

        if ($sessions >= 5) {
            $status = "Follow-up";
        } elseif ($sessions > 0) {
            $status = "Active";
        }

        $instructorStudentOverview[] = [
            "name" => (string) ($row["name"] ?? "Student"),
            "sessions" => $sessions,
            "status" => $status,
        ];
    }
}

$announcements = [
    ["title" => "Mental Health Week", "date" => "Apr 28 - May 3", "dot" => "dot-gold"],
    ["title" => "Resume Workshop", "date" => "Apr 27, 2PM", "dot" => "dot-blue"],
    ["title" => "Student Org Fair", "date" => "May 5, 10AM", "dot" => "dot-teal"],
];

$quickActions = [
    [
        "title" => "Book Counseling",
        "path" => "/campuscare-api/php-frontend/pages/appointments/book_appointment.php?service=counseling",
        "cardClass" => "quick-light-blue quick-counseling-bg",
        "iconClass" => "quick-icon-blue",
        "iconText" => "B",
        "iconImage" => "/campuscare-api/php-frontend/assets/images/icons/counseling.png"
    ],
    [
        "title" => "Join Workshops",
        "path" => "/campuscare-api/php-frontend/pages/events/events.php",
        "cardClass" => "quick-light-green quick-workshop-bg",
        "iconClass" => "quick-icon-teal",
        "iconText" => "W",
        "iconImage" => "/campuscare-api/php-frontend/assets/images/icons/workshop.png"
    ],
    [
        "title" => "Mental Health Test",
        "path" => "/campuscare-api/php-frontend/pages/tests/mental_health_test.php",
        "cardClass" => "quick-light-gray quick-bg-3",
        "iconClass" => "quick-icon-teal",
        "iconText" => "M",
        "iconImage" => "/campuscare-api/php-frontend/assets/images/icons/Mental-Test.png"
    ],
    [
        "title" => "My Schedule",
        "path" => "/campuscare-api/php-frontend/pages/appointments/schedule.php",
        "cardClass" => "quick-light-blue quick-bg-4",
        "iconClass" => "quick-icon-gold",
        "iconText" => "S",
        "iconImage" => "/campuscare-api/php-frontend/assets/images/icons/sched.png"
    ],
];

$adminQuickActions = [
    [
        "title" => "Manage Users",
        "path" => "/campuscare-api/php-frontend/pages/users/manage_users.php",
        "icon" => "user-plus",
        "iconClass" => "quick-icon-blue",
    ],
    [
        "title" => "Manage Appointments",
        "path" => "/campuscare-api/php-frontend/pages/appointments/manage_appointments.php",
        "icon" => "calendar",
        "iconClass" => "quick-icon-blue",
    ],
    [
        "title" => "Manage Events",
        "path" => "/campuscare-api/php-frontend/pages/events/events.php",
        "icon" => "users",
        "iconClass" => "quick-icon-blue",
    ],
    [
        "title" => "View Reports",
        "path" => "/campuscare-api/php-frontend/pages/reports/reports.php",
        "icon" => "report",
        "iconClass" => "quick-icon-gold",
    ],
];

$facilitatorQuickActions = [
    [
        "title" => "Manage Events",
        "path" => "/campuscare-api/php-frontend/pages/events/events.php",
        "icon" => "calendar",
        "iconClass" => "blue",
    ],
    [
        "title" => "View Participants",
        "path" => "/campuscare-api/php-frontend/pages/events/view_participants.php",
        "icon" => "users",
        "iconClass" => "blue",
    ],
    [
        "title" => "Create Session",
        "path" => "/campuscare-api/php-frontend/pages/events/events.php?open=create",
        "icon" => "plus",
        "iconClass" => "gold",
    ],
];

$instructorQuickActions = [
    [
        "title" => "Student Status",
        "path" => "/campuscare-api/php-frontend/pages/users/student_status.php",
        "icon" => "eye",
        "iconClass" => "blue",
    ],
    [
        "title" => "View Events",
        "path" => "/campuscare-api/php-frontend/pages/events/events.php",
        "icon" => "calendar",
        "iconClass" => "blue",
    ],
];

function statusClass($status)
{
    if ($status === "Approved") {
        return "status-approved";
    }

    if ($status === "Cancelled" || $status === "Rejected") {
        return "status-cancelled";
    }

    return "status-pending";
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
        <div class="page-shell<?php echo $role === "Counselor" ? " counselor-shell" : ($role === "Administrator" ? " admin-shell" : ($role === "Facilitator" ? " facilitator-shell" : ($role === "Instructor" ? " instructor-shell" : ""))); ?>">
            <div class="dashboard-head">
                <div>
                    <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($fullName); ?>!</h1>
                    <p class="page-subtitle">
                        Logged in as
                        <span class="role-pill"><?php echo htmlspecialchars($role); ?></span>
                        &mdash; Here's what's happening today.
                    </p>
                </div>

                <div class="date-card">
                    <span class="date-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                    <div>
                        <small>Today</small>
                        <strong><?php echo date("F j, Y"); ?></strong>
                    </div>
                </div>
            </div>

            <?php if ($role === "Student"): ?>
                <section class="summary-grid">
                    <article class="summary-card">
                        <p>Upcoming Events</p>
                        <div class="summary-row">
                            <h3>
                                <span class="big summary-primary">3</span>
                                <span class="muted">this week</span>
                            </h3>
                            <span class="summary-arrow">&rarr;</span>
                        </div>
                    </article>

                    <article class="summary-card">
                        <p>Pending Requests</p>
                        <div class="summary-row">
                            <h3>
                                <span class="big summary-accent">2</span>
                                <span class="muted">new messages</span>
                            </h3>
                            <span class="summary-arrow">&rarr;</span>
                        </div>
                    </article>

                    <article class="summary-card">
                        <p>Your Apps</p>
                        <div class="summary-row">
                            <h3>
                                <span class="big summary-gold"><?php echo $pendingCount; ?></span>
                                <span class="muted">for approval</span>
                            </h3>
                            <span class="summary-arrow">&rarr;</span>
                        </div>
                    </article>
                </section>

                <section class="quick-layout">
                    <div>
                        <h2 class="quick-title">Quick Access</h2>

                        <div class="quick-grid">
                            <?php foreach ($quickActions as $action): ?>
                                <a href="<?php echo htmlspecialchars($action["path"]); ?>" class="quick-card <?php echo $action["cardClass"]; ?> roboto-regular">
                                    <span class="quick-icon <?php echo $action["iconClass"]; ?>">
                                        <?php if (!empty($action["iconImage"])): ?>
                                            <img src="<?php echo htmlspecialchars($action["iconImage"]); ?>" alt="<?php echo htmlspecialchars($action["title"]); ?>">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($action["iconText"]); ?>
                                        <?php endif; ?>
                                    </span>
                                    <h4><?php echo htmlspecialchars($action["title"]); ?></h4>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <aside class="announcement-card">
                        <h3 class="announcement-head">Announcements</h3>

                        <ul class="announcement-list">
                            <?php foreach ($announcements as $item): ?>
                                <li class="announcement-item">
                                    <span class="announcement-dot <?php echo $item["dot"]; ?>"></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item["title"]); ?></strong>
                                        <span><?php echo htmlspecialchars($item["date"]); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                </section>

                <section>
                    <div class="section-head">
                        <h2 class="section-title">Upcoming Appointments</h2>
                        <a href="/campuscare-api/php-frontend/pages/appointments/schedule.php" class="section-link">View All &rarr;</a>
                    </div>

                    <?php if (empty($upcomingAppointments)): ?>
                        <div class="appointment-card">
                            <div class="appointment-left">
                                <img src="/campuscare-api/php-frontend/assets/images/icons/Book-now.png" alt="Appointment" class="appointment-icon">
                                <div>
                                    <p class="appointment-title">No upcoming appointments yet</p>
                                    <p class="appointment-meta">Book your first counseling session to get started.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="appointment-list">
                            <?php foreach ($upcomingAppointments as $apt): ?>
                                <?php
                                    $status = $apt["status"] ?: "Pending";
                                    $formattedDate = date(
                                        "D, F j | g:i A",
                                        strtotime($apt["appointment_date"] . " " . $apt["appointment_time"])
                                    );
                                ?>

                                <article class="appointment-card">
                                    <div class="appointment-left">
                                        <img src="/campuscare-api/php-frontend/assets/images/icons/Book-now.png" alt="Appointment" class="appointment-icon">
                                        <div>
                                            <p class="appointment-title">
                                                <?php echo htmlspecialchars($apt["service"] . " with " . $apt["counselor"]); ?>
                                            </p>
                                            <p class="appointment-meta">
                                                <?php echo htmlspecialchars($formattedDate); ?> &bull; Guidance Office
                                            </p>
                                        </div>
                                    </div>

                                    <span class="status-pill <?php echo statusClass($status); ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($role === "Administrator"): ?>
                <section class="admin-stats">
                    <article class="admin-stat-card">
                        <div class="admin-stat-top">
                            <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("users"); ?></span>
                        </div>
                        <p class="admin-stat-value text-blue"><?php echo number_format($adminTotalUsers); ?></p>
                        <p class="admin-stat-label">Total Users</p>
                    </article>

                    <article class="admin-stat-card">
                        <div class="admin-stat-top">
                            <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("calendar"); ?></span>
                        </div>
                        <p class="admin-stat-value text-blue"><?php echo number_format($adminTodayAppointments); ?></p>
                        <p class="admin-stat-label">Today's Appointments</p>
                    </article>

                    <article class="admin-stat-card">
                        <div class="admin-stat-top">
                            <span class="admin-stat-icon text-gold"><?php echo sidebarIconSvg("settings"); ?></span>
                        </div>
                        <p class="admin-stat-value text-gold"><?php echo number_format($adminPendingApprovals); ?></p>
                        <p class="admin-stat-label">Pending Approvals</p>
                    </article>

                    <article class="admin-stat-card">
                        <div class="admin-stat-top">
                            <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("report"); ?></span>
                        </div>
                        <p class="admin-stat-value text-blue"><?php echo number_format($adminEventsThisMonth); ?></p>
                        <p class="admin-stat-label">Events This Month</p>
                    </article>
                </section>

                <section>
                    <h2 class="quick-title">Quick Actions</h2>
                    <div class="admin-quick-grid">
                        <?php foreach ($adminQuickActions as $action): ?>
                            <a href="<?php echo htmlspecialchars($action["path"]); ?>" class="admin-quick-card">
                                <span class="quick-icon <?php echo htmlspecialchars($action["iconClass"]); ?>"><?php echo sidebarIconSvg($action["icon"]); ?></span>
                                <h3><?php echo htmlspecialchars($action["title"]); ?></h3>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php elseif ($role === "Counselor"): ?>
                <section class="counselor-stats">
                    <article class="counselor-stat">
                        <span class="counselor-stat-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <div class="counselor-stat-value counselor-stat-blue"><?php echo $counselorTodaySessions; ?></div>
                        <p class="counselor-stat-label">Today's Sessions</p>
                    </article>

                    <article class="counselor-stat">
                        <span class="counselor-stat-icon"><?php echo sidebarIconSvg("message"); ?></span>
                        <div class="counselor-stat-value counselor-stat-gold"><?php echo $counselorPendingNotes; ?></div>
                        <p class="counselor-stat-label">Pending Notes</p>
                    </article>

                    <article class="counselor-stat">
                        <span class="counselor-stat-icon"><?php echo sidebarIconSvg("clock"); ?></span>
                        <div class="counselor-stat-value counselor-stat-blue"><?php echo $counselorWeekSessions; ?></div>
                        <p class="counselor-stat-label">This Week</p>
                    </article>
                </section>

                <section>
                    <h2 class="quick-title">Quick Actions</h2>

                    <div class="counselor-quick">
                        <a href="/campuscare-api/php-frontend/pages/appointments/schedule.php" class="counselor-quick-card">
                            <span class="counselor-quick-icon blue"><?php echo sidebarIconSvg("calendar"); ?></span>
                            <h3 class="counselor-quick-title">View Appointments</h3>
                        </a>

                        <a href="/campuscare-api/php-frontend/pages/appointments/manage_schedule.php" class="counselor-quick-card">
                            <span class="counselor-quick-icon gold"><?php echo sidebarIconSvg("clock"); ?></span>
                            <h3 class="counselor-quick-title">Manage Schedule</h3>
                        </a>

                        <a href="/campuscare-api/php-frontend/pages/reports/session_feedback.php" class="counselor-quick-card">
                            <span class="counselor-quick-icon blue"><?php echo sidebarIconSvg("message"); ?></span>
                            <h3 class="counselor-quick-title">Session Feedback</h3>
                        </a>
                    </div>
                </section>

                <section>
                    <h2 class="counselor-list-title">Today's Appointments</h2>

                    <?php if (empty($counselorTodayAppointments)): ?>
                        <article class="counselor-appointment-card">
                            <div class="counselor-appointment-left">
                                <span class="mini-user-icon"><?php echo sidebarIconSvg("user"); ?></span>
                                <div>
                                    <p class="counselor-appointment-name">No appointments scheduled today</p>
                                    <p class="counselor-appointment-meta">You're all clear for now.</p>
                                </div>
                            </div>

                            <span class="status-pill status-pending">Open</span>
                        </article>
                    <?php else: ?>
                        <div class="counselor-appointment-list">
                            <?php foreach ($counselorTodayAppointments as $appointment): ?>
                                <?php
                                    $studentName = trim((string) ($appointment["student_name"] ?? ""));
                                    if ($studentName === "") {
                                        $studentName = "Student #" . intval($appointment["id"]);
                                    }

                                    $studentIdLabel = trim((string) ($appointment["student_id"] ?? ""));
                                    if ($studentIdLabel === "") {
                                        $studentIdLabel = "N/A";
                                    }

                                    $timeLabel = date("g:i A", strtotime((string) ($appointment["appointment_time"] ?? "")));
                                    $sessionStatus = (string) ($appointment["status"] ?? "Pending");
                                ?>

                                <article class="counselor-appointment-card">
                                    <div class="counselor-appointment-left">
                                        <span class="mini-user-icon"><?php echo sidebarIconSvg("user"); ?></span>
                                        <div>
                                            <p class="counselor-appointment-name"><?php echo htmlspecialchars($studentName); ?></p>
                                            <p class="counselor-appointment-meta">
                                                ID: <?php echo htmlspecialchars($studentIdLabel); ?> &bull;
                                                <?php echo htmlspecialchars((string) ($appointment["service"] ?? "Counseling")); ?> &bull;
                                                <?php echo htmlspecialchars($timeLabel); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <span class="status-pill <?php echo statusClass($sessionStatus); ?>">
                                        <?php echo htmlspecialchars($sessionStatus); ?>
                                    </span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($role === "Facilitator"): ?>
                <section class="facilitator-stats">
                    <article class="facilitator-stat-card">
                        <span class="facilitator-stat-icon blue"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <p class="facilitator-stat-value blue"><?php echo number_format($facilitatorYourEvents); ?></p>
                        <p class="facilitator-stat-label">Your Events</p>
                    </article>

                    <article class="facilitator-stat-card">
                        <span class="facilitator-stat-icon blue"><?php echo sidebarIconSvg("users"); ?></span>
                        <p class="facilitator-stat-value blue"><?php echo number_format($facilitatorTotalParticipants); ?></p>
                        <p class="facilitator-stat-label">Total Participants</p>
                    </article>

                    <article class="facilitator-stat-card">
                        <span class="facilitator-stat-icon gold"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <p class="facilitator-stat-value gold"><?php echo number_format($facilitatorThisWeek); ?></p>
                        <p class="facilitator-stat-label">This Week</p>
                    </article>
                </section>

                <section class="facilitator-quick-grid">
                    <?php foreach ($facilitatorQuickActions as $action): ?>
                        <a href="<?php echo htmlspecialchars((string) ($action["path"] ?? "#")); ?>" class="facilitator-quick-card">
                            <span class="facilitator-quick-icon <?php echo htmlspecialchars((string) ($action["iconClass"] ?? "blue")); ?>">
                                <?php echo sidebarIconSvg((string) ($action["icon"] ?? "calendar")); ?>
                            </span>
                            <p class="facilitator-quick-title"><?php echo htmlspecialchars((string) ($action["title"] ?? "Action")); ?></p>
                        </a>
                    <?php endforeach; ?>
                </section>

                <section>
                    <h2 class="facilitator-section-title">Upcoming Sessions</h2>

                    <?php if (empty($facilitatorUpcomingSessions)): ?>
                        <article class="facilitator-session-card">
                            <div class="facilitator-session-left">
                                <span class="facilitator-session-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                                <div>
                                    <p class="facilitator-session-title">No upcoming sessions found.</p>
                                    <p class="facilitator-session-date">Create a session to get started.</p>
                                </div>
                            </div>
                        </article>
                    <?php else: ?>
                        <div class="facilitator-session-list">
                            <?php foreach ($facilitatorUpcomingSessions as $event): ?>
                                <article class="facilitator-session-card">
                                    <div class="facilitator-session-left">
                                        <span class="facilitator-session-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                                        <div>
                                            <p class="facilitator-session-title"><?php echo htmlspecialchars((string) ($event["title"] ?? "Session")); ?></p>
                                            <p class="facilitator-session-date"><?php echo htmlspecialchars((string) ($event["date"] ?? "")); ?></p>
                                        </div>
                                    </div>

                                    <span class="facilitator-attendees-pill"><?php echo intval($event["participants"] ?? 0); ?> attendees</span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php elseif ($role === "Instructor"): ?>
                <section class="instructor-stats">
                    <article class="instructor-stat-card">
                        <span class="instructor-stat-icon blue"><?php echo sidebarIconSvg("users"); ?></span>
                        <p class="instructor-stat-value blue"><?php echo number_format($instructorStudentsMonitored); ?></p>
                        <p class="instructor-stat-label">Students Monitored</p>
                    </article>

                    <article class="instructor-stat-card">
                        <span class="instructor-stat-icon blue"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <p class="instructor-stat-value blue"><?php echo number_format($instructorAvailableEvents); ?></p>
                        <p class="instructor-stat-label">Available Events</p>
                    </article>
                </section>

                <section class="instructor-quick-grid">
                    <?php foreach ($instructorQuickActions as $action): ?>
                        <a href="<?php echo htmlspecialchars((string) ($action["path"] ?? "#")); ?>" class="instructor-quick-card">
                            <span class="instructor-quick-icon <?php echo htmlspecialchars((string) ($action["iconClass"] ?? "blue")); ?>">
                                <?php echo sidebarIconSvg((string) ($action["icon"] ?? "eye")); ?>
                            </span>
                            <p class="instructor-quick-title"><?php echo htmlspecialchars((string) ($action["title"] ?? "Action")); ?></p>
                        </a>
                    <?php endforeach; ?>
                </section>

                <section>
                    <div class="instructor-overview-head">
                        <h2 class="instructor-overview-title">Student Participation Overview</h2>
                        <a href="/campuscare-api/php-frontend/pages/users/student_status.php" class="instructor-view-all">View All &rarr;</a>
                    </div>

                    <?php if (empty($instructorStudentOverview)): ?>
                        <article class="instructor-overview-item">
                            <div class="instructor-overview-left">
                                <span class="instructor-overview-avatar">S</span>
                                <div>
                                    <p class="instructor-overview-name">No students found</p>
                                    <p class="instructor-overview-meta">Student records will appear here.</p>
                                </div>
                            </div>
                        </article>
                    <?php else: ?>
                        <div class="instructor-overview-list">
                            <?php foreach ($instructorStudentOverview as $student): ?>
                                <?php
                                    $studentName = trim((string) ($student["name"] ?? "Student"));
                                    if ($studentName === "") {
                                        $studentName = "Student";
                                    }

                                    $sessions = intval($student["sessions"] ?? 0);
                                    $status = (string) ($student["status"] ?? "No sessions");
                                    $statusClass = "none";

                                    if ($status === "Active") {
                                        $statusClass = "active";
                                    } elseif ($status === "Follow-up") {
                                        $statusClass = "follow-up";
                                    }
                                ?>

                                <article class="instructor-overview-item">
                                    <div class="instructor-overview-left">
                                        <span class="instructor-overview-avatar"><?php echo htmlspecialchars(strtoupper(substr($studentName, 0, 1))); ?></span>
                                        <div>
                                            <p class="instructor-overview-name"><?php echo htmlspecialchars($studentName); ?></p>
                                            <p class="instructor-overview-meta"><?php echo $sessions; ?> counseling sessions</p>
                                        </div>
                                    </div>

                                    <span class="instructor-overview-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="grid grid-3">
                    <article class="card">
                        <h2 class="card-title">Dashboard Overview</h2>
                        <p class="page-subtitle">You are logged in as <?php echo htmlspecialchars($role); ?>.</p>
                    </article>

                    <article class="card">
                        <h2 class="card-title">Appointments</h2>
                        <p class="page-subtitle">Use the sidebar to manage appointments and schedules.</p>
                    </article>

                    <article class="card">
                        <h2 class="card-title">Reports</h2>
                        <p class="page-subtitle">View analytics and role-specific summaries.</p>
                    </article>
                </section>
            <?php endif; ?>
        </div>

        <a href="#" class="chat-fab chat-fab-icon" aria-label="Open chat"><?php echo sidebarIconSvg("message"); ?></a>
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


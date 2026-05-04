<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "System Reports";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "Administrator";

if ($role !== "Administrator") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

function relativeTimeLabel(int $timestamp): string
{
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 3600) {
        return "Just now";
    }

    if ($diff < 86400) {
        $hours = intdiv($diff, 3600);
        return $hours . " hour" . ($hours === 1 ? "" : "s") . " ago";
    }

    $days = intdiv($diff, 86400);
    return $days . " day" . ($days === 1 ? "" : "s") . " ago";
}

$totalUsers = 0;
$appointmentsThisMonth = 0;
$assessmentsTaken = 0;
$eventsThisMonth = 0;

if ($result = $conn->query("SELECT COUNT(*) AS total FROM users")) {
    if ($row = $result->fetch_assoc()) {
        $totalUsers = intval($row["total"] ?? 0);
    }
}

if ($result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE MONTH(appointment_date) = MONTH(CURDATE())
       AND YEAR(appointment_date) = YEAR(CURDATE())"
)) {
    if ($row = $result->fetch_assoc()) {
        $appointmentsThisMonth = intval($row["total"] ?? 0);
    }
}

if ($result = $conn->query("SELECT COUNT(*) AS total FROM mental_health_tests")) {
    if ($row = $result->fetch_assoc()) {
        $assessmentsTaken = intval($row["total"] ?? 0);
    }
}

if ($result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM events
     WHERE MONTH(event_date) = MONTH(CURDATE())
       AND YEAR(event_date) = YEAR(CURDATE())"
)) {
    if ($row = $result->fetch_assoc()) {
        $eventsThisMonth = intval($row["total"] ?? 0);
    }
}

$activity = [];

if ($result = $conn->query(
    "SELECT full_name, role, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 2"
)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string) ($row["created_at"] ?? ""));
        $activity[] = [
            "action" => "New user registered",
            "detail" => trim((string) ($row["full_name"] ?? "User")) . " - " . normalizeRole((string) ($row["role"] ?? "")),
            "time" => $timestamp > 0 ? $timestamp : time(),
        ];
    }
}

if ($result = $conn->query(
    "SELECT title, created_at
     FROM events
     ORDER BY created_at DESC
     LIMIT 2"
)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string) ($row["created_at"] ?? ""));
        $activity[] = [
            "action" => "Event created",
            "detail" => trim((string) ($row["title"] ?? "Event")),
            "time" => $timestamp > 0 ? $timestamp : time(),
        ];
    }
}

if ($result = $conn->query(
    "SELECT u.full_name AS student_name, a.counselor, a.created_at
     FROM appointments a
     INNER JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 2"
)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string) ($row["created_at"] ?? ""));
        $activity[] = [
            "action" => "Appointment booked",
            "detail" => trim((string) ($row["student_name"] ?? "Student")) . " with " . trim((string) ($row["counselor"] ?? "Counselor")),
            "time" => $timestamp > 0 ? $timestamp : time(),
        ];
    }
}

if ($result = $conn->query(
    "SELECT u.full_name AS student_name, m.created_at
     FROM mental_health_tests m
     INNER JOIN users u ON u.id = m.user_id
     ORDER BY m.created_at DESC
     LIMIT 2"
)) {
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string) ($row["created_at"] ?? ""));
        $activity[] = [
            "action" => "Assessment submitted",
            "detail" => trim((string) ($row["student_name"] ?? "Student")),
            "time" => $timestamp > 0 ? $timestamp : time(),
        ];
    }
}

usort($activity, function ($a, $b) {
    return intval($b["time"] ?? 0) <=> intval($a["time"] ?? 0);
});

$activity = array_slice($activity, 0, 6);

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
            <div class="reports-booking-head">
                <h1 class="page-title">System Reports</h1>
                <p class="page-subtitle">Overview of system activity and metrics</p>
            </div>

            <section class="admin-stats">
                <article class="admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("users"); ?></span>
                        <span class="admin-live-pill"><?php echo sidebarIconSvg("trend"); ?> Live</span>
                    </div>
                    <p class="admin-stat-value text-blue"><?php echo number_format($totalUsers); ?></p>
                    <p class="admin-stat-label">Total Users</p>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("calendar"); ?></span>
                        <span class="admin-live-pill"><?php echo sidebarIconSvg("trend"); ?> Live</span>
                    </div>
                    <p class="admin-stat-value text-blue"><?php echo number_format($appointmentsThisMonth); ?></p>
                    <p class="admin-stat-label">Appointments This Month</p>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon text-blue"><?php echo sidebarIconSvg("brain"); ?></span>
                        <span class="admin-live-pill"><?php echo sidebarIconSvg("trend"); ?> Live</span>
                    </div>
                    <p class="admin-stat-value text-blue"><?php echo number_format($assessmentsTaken); ?></p>
                    <p class="admin-stat-label">Assessments Taken</p>
                </article>

                <article class="admin-stat-card">
                    <div class="admin-stat-top">
                        <span class="admin-stat-icon text-gold"><?php echo sidebarIconSvg("report"); ?></span>
                        <span class="admin-live-pill"><?php echo sidebarIconSvg("trend"); ?> Live</span>
                    </div>
                    <p class="admin-stat-value text-gold"><?php echo number_format($eventsThisMonth); ?></p>
                    <p class="admin-stat-label">Events This Month</p>
                </article>
            </section>

            <section class="reports-activity-card">
                <div class="reports-activity-head">
                    <div>
                        <h2 class="quick-title reports-activity-title">Recent Activity</h2>
                        <p class="reports-activity-subtitle">Latest updates across users, appointments, events, and assessments</p>
                    </div>
                    <span class="reports-activity-count"><?php echo count($activity); ?> updates</span>
                </div>

                <?php if (empty($activity)): ?>
                    <div class="admin-users-empty" style="margin-top: 0;">No recent activity found.</div>
                <?php else: ?>
                    <div class="reports-activity-list">
                        <?php foreach ($activity as $item): ?>
                            <?php
                                $activityAction = (string) ($item["action"] ?? "Activity");
                                $activityType = "generic";

                                if ($activityAction === "New user registered") {
                                    $activityType = "user";
                                } elseif ($activityAction === "Event created") {
                                    $activityType = "event";
                                } elseif ($activityAction === "Appointment booked") {
                                    $activityType = "appointment";
                                } elseif ($activityAction === "Assessment submitted") {
                                    $activityType = "assessment";
                                }
                            ?>
                            <article class="activity-row activity-row-<?php echo htmlspecialchars($activityType); ?>">
                                <div class="activity-left">
                                    <span class="activity-dot activity-dot-<?php echo htmlspecialchars($activityType); ?>"></span>
                                    <div>
                                        <p class="strong activity-action"><?php echo htmlspecialchars($activityAction); ?></p>
                                        <p class="muted"><?php echo htmlspecialchars((string) ($item["detail"] ?? "")); ?></p>
                                    </div>
                                </div>
                                <span class="activity-time"><?php echo htmlspecialchars(relativeTimeLabel(intval($item["time"] ?? time()))); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<style>
    .reports-booking-head {
        padding: 30px 34px 20px;
        background: var(--primary);
        color: #fff;
        border-radius: 22px;
        margin-top: 15px;
        margin-bottom: 1rem;
        box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    }

    .reports-booking-head h1 {
        margin: 0 0 8px;
        font-size: 34px;
        color: #fff;
    }

    .reports-booking-head p {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, 0.9);
    }

    .reports-activity-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #dbe7f4;
        border-radius: 22px;
        padding: 22px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
        margin-top: 18px;
    }

    .reports-activity-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid #e5edf7;
    }

    .reports-activity-title {
        margin-bottom: 6px;
    }

    .reports-activity-subtitle {
        margin: 0;
        color: #6b7c93;
        font-size: 14px;
    }

    .reports-activity-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 999px;
        background: #e8f1fb;
        color: #2f6ea7;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    .reports-activity-list {
        display: grid;
        gap: 10px;
    }

    .activity-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 16px;
        border: 1px solid #e5edf7;
        border-radius: 16px;
        background: #fff;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .activity-row:hover {
        transform: translateY(-2px);
        border-color: #cfe1f3;
        box-shadow: 0 10px 22px rgba(47, 74, 97, 0.08);
    }

    .activity-left {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }

    .activity-dot {
        width: 11px;
        height: 11px;
        border-radius: 999px;
        margin-top: 7px;
        box-shadow: 0 0 0 6px rgba(75, 143, 196, 0.12);
    }

    .activity-dot-user {
        background: #4b8fc4;
    }

    .activity-dot-event {
        background: #16a34a;
        box-shadow: 0 0 0 6px rgba(22, 163, 74, 0.12);
    }

    .activity-dot-appointment {
        background: #f59e0b;
        box-shadow: 0 0 0 6px rgba(245, 158, 11, 0.12);
    }

    .activity-dot-assessment {
        background: #8b5cf6;
        box-shadow: 0 0 0 6px rgba(139, 92, 246, 0.12);
    }

    .activity-action {
        margin-bottom: 2px;
        font-size: 16px;
    }

    .activity-row .muted {
        color: #62748a;
    }

    .activity-time {
        flex: 0 0 auto;
        align-self: center;
        padding: 7px 10px;
        border-radius: 999px;
        background: #f2f7fc;
        color: #4f6780;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 640px) {
        .reports-activity-head,
        .activity-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .activity-time {
            align-self: flex-start;
        }
    }
</style>

</div>
<script>
(function () {

})();
</script>
</body>
</html>



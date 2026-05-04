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
                <h2 class="quick-title" style="margin-bottom: 16px;">Recent Activity</h2>

                <?php if (empty($activity)): ?>
                    <div class="admin-users-empty" style="margin-top: 0;">No recent activity found.</div>
                <?php else: ?>
                    <div class="reports-activity-list">
                        <?php foreach ($activity as $item): ?>
                            <article class="activity-row">
                                <div class="activity-left">
                                    <span class="activity-dot"></span>
                                    <div>
                                        <p class="strong"><?php echo htmlspecialchars((string) ($item["action"] ?? "Activity")); ?></p>
                                        <p class="muted"><?php echo htmlspecialchars((string) ($item["detail"] ?? "")); ?></p>
                                    </div>
                                </div>
                                <span class="muted"><?php echo htmlspecialchars(relativeTimeLabel(intval($item["time"] ?? time()))); ?></span>
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
</style>

</div>
<script>
(function () {

})();
</script>
</body>
</html>



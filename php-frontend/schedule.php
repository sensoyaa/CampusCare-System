<?php
require_once "includes/auth.php";
requireLogin();
require_once "includes/db.php";

$pageTitle = "Schedule";

$userId = intval($_SESSION["user_id"] ?? 0);
$role = normalizeRole($_SESSION["role"] ?? "Student");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_id"])) {
    $cancelId = intval($_POST["cancel_id"]);

    if ($role === "Counselor") {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'Cancelled' 
            WHERE id = ? AND counselor_id = ?
        ");
        $stmt->bind_param("ii", $cancelId, $userId);
    } else {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = 'Cancelled' 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $cancelId, $userId);
    }

    if ($stmt->execute()) {
        $success = "Appointment cancelled successfully.";
    } else {
        $error = "Failed to cancel appointment.";
    }

    $stmt->close();
}

$appointments = [];

if ($role === "Counselor") {
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.service,
            a.counselor,
            a.appointment_date,
            a.appointment_time,
            COALESCE(NULLIF(a.status, ''), 'Pending') AS status,
            u.full_name AS student_name,
            u.student_id
        FROM appointments a
        INNER JOIN users u ON a.user_id = u.id
        WHERE a.counselor_id = ?
          AND u.role = 'Student'
          AND COALESCE(NULLIF(a.status, ''), 'Pending') != 'Cancelled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->bind_param("i", $userId);
} else {
    $stmt = $conn->prepare("
        SELECT 
            id,
            service,
            counselor,
            appointment_date,
            appointment_time,
            COALESCE(NULLIF(status, ''), 'Pending') AS status
        FROM appointments
        WHERE user_id = ?
        ORDER BY appointment_date ASC, appointment_time ASC
    ");
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

$stmt->close();

require_once "includes/header.php";
require_once "includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <div class="topbar-user">
            <span>Hi, <?php echo htmlspecialchars($_SESSION["full_name"]); ?>!</span>
            <span class="avatar"><?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?></span>
        </div>
    </div>

    <div class="content">
        <div class="page-shell schedule-shell">
            <h1 class="page-title">
                <?php echo $role === "Counselor" ? "View Appointments" : "My Schedule"; ?>
            </h1>

            <p class="page-subtitle" style="margin-bottom: 24px;">
                <?php echo $role === "Counselor" ? "Appointments assigned to you" : "Your upcoming appointments"; ?>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($appointments)): ?>
                <div class="card" style="text-align: center; color: #5e6b75;">
                    No appointments found.
                </div>
            <?php else: ?>
                <div class="schedule-list">
                    <?php foreach ($appointments as $apt): ?>
                        <?php
                            $status = $apt["status"] ?: "Pending";
                            $statusClass = "status-pending";

                            if ($status === "Approved") {
                                $statusClass = "status-approved";
                            } elseif ($status === "Cancelled" || $status === "Rejected") {
                                $statusClass = "status-cancelled";
                            }

                            $title = "";
                            if ($role === "Counselor") {
                                $title = $apt["service"] . " with " . ($apt["student_name"] ?? "Student");
                            } else {
                                $title = $apt["service"] . " with " . $apt["counselor"];
                            }

                            $dateTime = date("D, F j \a\t g:i A", strtotime($apt["appointment_date"] . " " . $apt["appointment_time"]));
                        ?>

                        <article class="schedule-item">
                            <div class="schedule-left">
                                <span class="schedule-icon"><?php echo $role === "Counselor" ? sidebarIconSvg("user") : sidebarIconSvg("calendar"); ?></span>
                                <div>
                                    <h2 class="schedule-title"><?php echo htmlspecialchars($title); ?></h2>

                                    <?php if ($role === "Counselor" && isset($apt["student_id"])): ?>
                                        <p class="schedule-meta">Student ID: <?php echo htmlspecialchars($apt["student_id"]); ?></p>
                                    <?php endif; ?>

                                    <p class="schedule-meta"><?php echo htmlspecialchars($dateTime); ?></p>
                                    <p class="schedule-meta">Guidance Office</p>
                                </div>
                            </div>

                            <div class="schedule-actions">
                                <span class="status-pill <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>

                                <?php if ($status !== "Cancelled"): ?>
                                    <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
                                        <input type="hidden" name="cancel_id" value="<?php echo $apt["id"]; ?>">
                                        <button type="submit" class="icon-btn" aria-label="Cancel appointment">
                                            x
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

</div>
</body>
</html>
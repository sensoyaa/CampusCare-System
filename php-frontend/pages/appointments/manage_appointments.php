<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Manage Appointments";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "Administrator";

if ($role !== "Administrator") {
    header("Location: dashboard.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    if ($action === "update_status") {
        $appointmentId = intval($_POST["appointment_id"] ?? 0);
        $newStatus = trim((string) ($_POST["status"] ?? ""));
        $allowedStatuses = ["Pending", "Approved", "Cancelled", "Rejected"];

        if ($appointmentId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = "Invalid appointment update request.";
        } else {
            $updateStmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newStatus, $appointmentId);

            if ($updateStmt->execute()) {
                $success = "Appointment status updated to " . $newStatus . ".";
            } else {
                $error = "Failed to update appointment status.";
            }

            $updateStmt->close();
        }
    }
}

$search = trim((string) ($_GET["search"] ?? ""));
$appointments = [];

$result = $conn->query(
    "SELECT
        a.id,
        u.full_name AS student,
        COALESCE(NULLIF(a.counselor, ''), 'Counselor') AS counselor,
        COALESCE(NULLIF(a.service, ''), 'Counseling') AS type,
        a.appointment_date,
        a.appointment_time,
        COALESCE(NULLIF(a.status, ''), 'Pending') AS status
     FROM appointments a
     INNER JOIN users u ON u.id = a.user_id
     ORDER BY a.appointment_date ASC, a.appointment_time ASC"
);

while ($row = $result->fetch_assoc()) {
    if ($search !== "") {
        $haystack = strtolower((string) ($row["student"] ?? "") . " " . (string) ($row["counselor"] ?? ""));
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }

    $appointments[] = $row;
}

function adminStatusClass(string $status): string
{
    if ($status === "Approved") {
        return "status-approved";
    }

    if ($status === "Cancelled" || $status === "Rejected") {
        return "status-cancelled";
    }

    return "status-pending";
}

function adminStatusIcon(string $status): string
{
    if ($status === "Approved") {
        return "check-circle";
    }

    if ($status === "Cancelled" || $status === "Rejected") {
        return "x-circle";
    }

    return "clock";
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main class="main">
    <div class="topbar">
        <button class="menu-toggle" type="button" aria-label="Sidebar">
            <span class="menu-lines"></span>
        </button>

        <div class="topbar-user">
            <span>Hi, <?php echo htmlspecialchars($fullName); ?>!</span>
            <span class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></span>
        </div>
    </div>

    <div class="content">
        <div class="page-shell admin-shell">
            <div>
                <h1 class="page-title">Manage Appointments</h1>
                <p class="page-subtitle" style="margin-bottom: 18px;">View and control all bookings</p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="GET" class="admin-toolbar" style="margin-bottom: 18px;">
                <div class="admin-search-wrap admin-search-medium">
                    <span class="admin-search-icon"><?php echo sidebarIconSvg("search"); ?></span>
                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by student or counselor..."
                    >
                </div>
            </form>

            <?php if (empty($appointments)): ?>
                <div class="admin-users-empty">No appointments found.</div>
            <?php else: ?>
                <section class="admin-appointment-list">
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                            $status = trim((string) ($appointment["status"] ?? "Pending"));
                            $statusClass = adminStatusClass($status);
                            $statusIcon = adminStatusIcon($status);

                            $dateLabel = date(
                                "M j, g:i A",
                                strtotime((string) ($appointment["appointment_date"] ?? "") . " " . (string) ($appointment["appointment_time"] ?? ""))
                            );
                        ?>

                        <article class="admin-appointment-card">
                            <div class="admin-appointment-left">
                                <span class="schedule-icon"><?php echo sidebarIconSvg("calendar"); ?></span>
                                <div>
                                    <p class="strong"><?php echo htmlspecialchars((string) ($appointment["student"] ?? "Student")); ?></p>
                                    <p class="muted admin-appointment-meta">
                                        <?php echo htmlspecialchars((string) ($appointment["type"] ?? "Counseling")); ?>
                                        with <?php echo htmlspecialchars((string) ($appointment["counselor"] ?? "Counselor")); ?>
                                        - <?php echo htmlspecialchars($dateLabel); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="admin-appointment-actions">
                                <span class="status-pill <?php echo $statusClass; ?> admin-status-pill-icon">
                                    <span class="status-icon-inline"><?php echo sidebarIconSvg($statusIcon); ?></span>
                                    <?php echo htmlspecialchars($status); ?>
                                </span>

                                <?php if ($status === "Pending"): ?>
                                    <form
                                        method="POST"
                                        class="inline-form"
                                        data-confirm-title="Approve appointment"
                                        data-confirm-message="Approve this appointment request?"
                                        data-confirm-button="Approve"
                                    >
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo intval($appointment["id"]); ?>">
                                        <input type="hidden" name="status" value="Approved">
                                        <button type="submit" class="btn btn-sm">Approve</button>
                                    </form>

                                    <form
                                        method="POST"
                                        class="inline-form"
                                        data-confirm-title="Decline appointment"
                                        data-confirm-message="Decline this appointment request?"
                                        data-confirm-button="Decline"
                                        data-confirm-variant="danger"
                                    >
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo intval($appointment["id"]); ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" class="btn btn-outline btn-sm">Decline</button>
                                    </form>
                                <?php elseif ($status === "Approved"): ?>
                                    <form
                                        method="POST"
                                        class="inline-form"
                                        data-confirm-title="Cancel appointment"
                                        data-confirm-message="Cancel this approved appointment?"
                                        data-confirm-button="Cancel Appointment"
                                        data-confirm-variant="danger"
                                    >
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="appointment_id" value="<?php echo intval($appointment["id"]); ?>">
                                        <input type="hidden" name="status" value="Cancelled">
                                        <button type="submit" class="btn btn-outline btn-sm btn-danger-outline">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>
</body>
</html>


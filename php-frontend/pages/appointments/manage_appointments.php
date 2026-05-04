<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../../backend/config/mail.php";

$pageTitle = "Manage Appointments";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "Administrator";

if ($role !== "Administrator") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";

// Load active counselors up front so POST handlers can use them safely.
$activeCounselors = [];
$activeCounselorMap = [];
$cRes = $conn->query(
    "SELECT id, full_name, email FROM users WHERE role IN ('Counselor','Counsellor','Counselors') AND status = 'Active' ORDER BY full_name ASC"
);
if ($cRes) {
    while ($crow = $cRes->fetch_assoc()) {
        $activeCounselors[] = $crow;
        $activeCounselorMap[intval($crow['id'])] = $crow;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    // Bulk actions and assignment handling
            if ($action === "bulk_action") {
        $ids = $_POST["bulk_ids"] ?? [];
        $bulkType = trim((string) ($_POST["bulk_action_type"] ?? ""));
        if (!is_array($ids) || empty($ids)) {
            $error = "No appointments selected for bulk action.";
        } else {
                    // Admins are not allowed to reject/cancel appointments here.
                    $validBulk = ["assign"];
            if (!in_array($bulkType, $validBulk, true)) {
                $error = "Invalid bulk action.";
            } else {
                // prepare statements
                $updateStmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $assignStmt = $conn->prepare("UPDATE appointments SET counselor_id = ?, counselor = ? WHERE id = ?");
                $auditStmt = $conn->prepare("INSERT INTO appointment_audit (appointment_id, user_id, action, metadata) VALUES (?, ?, ?, ?)");

                    foreach ($ids as $aid) {
                        $aid = intval($aid);
                        if ($bulkType === 'assign') {
                            $targetCounselorId = intval($_POST['bulk_counselor_id'] ?? 0);
                            if ($targetCounselorId <= 0) continue;
                            $cName = $activeCounselorMap[$targetCounselorId]['full_name'] ?? '';
                            $assignStmt->bind_param("isi", $targetCounselorId, $cName, $aid);
                            $assignStmt->execute();
                            $meta = json_encode(['assigned_to' => $targetCounselorId]);
                            $auditStmt->bind_param("iiss", $aid, $_SESSION['user_id'], 'assigned', $meta);
                            $auditStmt->execute();
                        }
                    }

                if ($updateStmt) $updateStmt->close();
                if ($assignStmt) $assignStmt->close();
                if ($auditStmt) $auditStmt->close();

                $success = "Bulk action completed.";
            }
        }
    }

    if ($action === 'assign_counselor') {
        $appointmentId = intval($_POST['appointment_id'] ?? 0);
        $targetCounselorId = intval($_POST['counselor_id'] ?? 0);
        if ($appointmentId <= 0 || $targetCounselorId <= 0) {
            $error = 'Invalid assignment parameters.';
        } else {
            // load appointment datetime
            $aptStmt = $conn->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE id = ? LIMIT 1");
            $aptStmt->bind_param("i", $appointmentId);
            $aptStmt->execute();
            $apt = $aptStmt->get_result()->fetch_assoc();
            $aptStmt->close();

            $apptDate = $apt['appointment_date'] ?? null;
            $apptTime = $apt['appointment_time'] ?? null;

            // availability check: ensure counselor has availability for that day and time
            $dayName = date('l', strtotime($apptDate));
            $availStmt = $conn->prepare("SELECT start_time, end_time FROM counselor_availability WHERE counselor_id = ? AND day = ?");
            $availStmt->bind_param("is", $targetCounselorId, $dayName);
            $availStmt->execute();
            $availRes = $availStmt->get_result();
            $isAvailable = false;
            while ($r = $availRes->fetch_assoc()) {
                $start = strtotime($r['start_time']);
                $end = strtotime($r['end_time']);
                $slot = strtotime($apptTime);
                if ($slot >= $start && $slot <= $end) { $isAvailable = true; break; }
            }
            $availStmt->close();

            if (!$isAvailable) {
                $error = 'Selected counselor is not available at the appointment time.';
            } else {
                $cName = $activeCounselorMap[$targetCounselorId]['full_name'] ?? '';
                $cEmail = $activeCounselorMap[$targetCounselorId]['email'] ?? '';

                $uStmt = $conn->prepare("UPDATE appointments SET counselor_id = ?, counselor = ? WHERE id = ?");
                $uStmt->bind_param("isi", $targetCounselorId, $cName, $appointmentId);
                if ($uStmt->execute()) {
                    $success = 'Counselor assigned successfully.';
                    $auditStmt = $conn->prepare("INSERT INTO appointment_audit (appointment_id, user_id, action, metadata) VALUES (?, ?, ?, ?)");
                    $meta = json_encode(['assigned_to' => $targetCounselorId]);
                    $auditStmt->bind_param("iiss", $appointmentId, $_SESSION['user_id'], $action, $meta);
                    $auditStmt->execute();
                    $auditStmt->close();

                    // send notification email to counselor if email present
                    if (!empty($cEmail)) {
                        $studentName = '';
                        $sStmt = $conn->prepare("SELECT u.full_name FROM appointments a JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1");
                        $sStmt->bind_param("i", $appointmentId);
                        $sStmt->execute();
                        $sRow = $sStmt->get_result()->fetch_assoc();
                        $sStmt->close();
                        $studentName = $sRow['full_name'] ?? '';

                        $html = campuscare_email_template(
                            "New Appointment Assigned",
                            "A student appointment was assigned to you.",
                            "<p>Hello " . htmlspecialchars($cName) . ",</p><p>An appointment for <strong>" . htmlspecialchars($studentName) . "</strong> was assigned to you on " . htmlspecialchars($apptDate) . " at " . htmlspecialchars(date('g:i A', strtotime($apptTime))) . ".</p>",
                            []
                        );
                        send_smtp_mail($cEmail, $cName, "Appointment Assigned", $html, "");
                    }

                } else {
                    $error = 'Failed to assign counselor.';
                }
                $uStmt->close();
            }
        }
    }

    if ($action === 'add_note') {
        $appointmentId = intval($_POST['appointment_id'] ?? 0);
        $noteText = trim((string) ($_POST['note'] ?? ''));
        $isPrivate = isset($_POST['is_private']) ? 1 : 1;
        if ($appointmentId > 0 && $noteText !== '') {
            $nStmt = $conn->prepare("INSERT INTO appointment_notes (appointment_id, user_id, note, is_private) VALUES (?, ?, ?, ?)");
            $nStmt->bind_param("iisi", $appointmentId, $_SESSION['user_id'], $noteText, $isPrivate);
            if ($nStmt->execute()) {
                $success = 'Note added.';
            } else {
                $error = 'Failed to add note.';
            }
            $nStmt->close();
        } else {
            $error = 'Invalid note submission.';
        }
    }

    if ($action === "update_status") {
        $appointmentId = intval($_POST["appointment_id"] ?? 0);
        $newStatus = trim((string) ($_POST["status"] ?? ""));
        $allowedStatuses = ["Pending", "Approved", "Cancelled", "Rejected"];

        if ($appointmentId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $error = "Invalid appointment update request.";
        } elseif ($newStatus === "Approved") {
            // Only counselors should be able to approve appointments.
            $error = "Only counselors are allowed to approve appointments. Please ask the assigned counselor to approve this request.";
        } elseif ($newStatus === "Cancelled") {
            // Only the assigned counselor or the student may cancel appointments.
            $error = "Only the assigned counselor or the student may cancel appointments.";
        } elseif ($newStatus === "Rejected") {
            // Only the assigned counselor may reject/decline appointments.
            $error = "Only the assigned counselor may decline appointments.";
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
        a.counselor_id,
        a.user_id,
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

$appointmentTotals = [
    'total' => count($appointments),
    'pending' => 0,
    'approved' => 0,
    'cancelled' => 0,
    'rejected' => 0,
];

foreach ($appointments as $summaryAppointment) {
    $summaryStatus = trim((string) ($summaryAppointment['status'] ?? 'Pending'));

    if ($summaryStatus === 'Pending') {
        $appointmentTotals['pending']++;
    } elseif ($summaryStatus === 'Approved') {
        $appointmentTotals['approved']++;
    } elseif ($summaryStatus === 'Cancelled') {
        $appointmentTotals['cancelled']++;
    } elseif ($summaryStatus === 'Rejected') {
        $appointmentTotals['rejected']++;
    }
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

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell admin-shell">
            <div>
                <h1 class="page-title">Manage Appointments</h1>
                <p class="page-subtitle" style="margin-bottom: 18px;">View and control all bookings</p>
            </div>

            <section class="admin-appointment-stats">
                <article class="admin-appointment-stat-card">
                    <span class="admin-appointment-stat-label">Total</span>
                    <strong><?php echo number_format($appointmentTotals['total']); ?></strong>
                </article>
                <article class="admin-appointment-stat-card">
                    <span class="admin-appointment-stat-label">Pending</span>
                    <strong><?php echo number_format($appointmentTotals['pending']); ?></strong>
                </article>
                <article class="admin-appointment-stat-card">
                    <span class="admin-appointment-stat-label">Approved</span>
                    <strong><?php echo number_format($appointmentTotals['approved']); ?></strong>
                </article>
                <article class="admin-appointment-stat-card">
                    <span class="admin-appointment-stat-label">Needs Review</span>
                    <strong><?php echo number_format($appointmentTotals['pending'] + $appointmentTotals['rejected']); ?></strong>
                </article>
            </section>

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
                <!-- Bulk action toolbar -->
                <div class="admin-bulk-toolbar">
                    <div class="admin-bulk-left">
                        <button type="button" id="toggleSelectModeBtn" class="btn btn-outline">Select</button>
                        <button type="button" id="cancelSelectBtn" class="btn" style="display:none; margin-left:8px;">Cancel</button>
                    </div>

                    <div class="admin-bulk-right">
                    <select id="bulkActionType" class="admin-bulk-select">
                        <option value="">Bulk action...</option>
                        <option value="assign">Assign counselor</option>
                    </select>
                    <select id="bulkCounselorSelect" class="admin-bulk-select" style="display:none;">
                        <option value="0">Select counselor...</option>
                        <?php foreach ($activeCounselors as $ac): ?>
                            <option value="<?php echo intval($ac['id']); ?>"><?php echo htmlspecialchars($ac['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="bulkActionBtn" class="btn admin-bulk-apply">Apply</button>
                    </div>
                </div>

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

                        <article class="admin-appointment-card" onclick="window.location.href='/campuscare-api/php-frontend/pages/appointments/appointment_detail.php?id=<?php echo intval($appointment["id"]); ?>&return_to=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>';" style="cursor: pointer;">
                            <div class="admin-appointment-left">
                                <input type="checkbox" class="bulk-checkbox" data-id="<?php echo intval($appointment['id']); ?>" onclick="event.stopPropagation();" />
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

                            <div class="admin-appointment-actions" onclick="event.stopPropagation();">
                                <span class="status-pill <?php echo $statusClass; ?> admin-status-pill-icon">
                                    <span class="status-icon-inline"><?php echo sidebarIconSvg($statusIcon); ?></span>
                                    <?php echo htmlspecialchars($status); ?>
                                </span>

                                <?php if ($status === "Pending"): ?>
                                    <!-- Decline/Reject is only available to the assigned counselor via the Schedule view. -->
                                <?php endif; ?>

                                <!-- Assign/Reassign form (Admin) -->
                                <form method="POST" class="inline-form" style="margin-top:8px;">
                                    <input type="hidden" name="action" value="assign_counselor">
                                    <input type="hidden" name="appointment_id" value="<?php echo intval($appointment['id']); ?>">
                                    <select name="counselor_id" style="padding:6px; margin-right:6px;">
                                        <option value="0">Assign counselor...</option>
                                        <?php foreach ($activeCounselors as $c): ?>
                                            <option value="<?php echo intval($c['id']); ?>"><?php echo htmlspecialchars($c['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm">Assign</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>

                </div>
                <script>
                    /* Appointment card styling */
                    const style = document.createElement('style');
                    style.textContent = `
                        .admin-appointment-stats {
                            display: grid;
                            grid-template-columns: repeat(4, minmax(0, 1fr));
                            gap: 12px;
                            margin: 0 0 18px;
                        }

                        .admin-appointment-stat-card {
                            background: #fff;
                            border: 1px solid #e6edf5;
                            border-radius: 16px;
                            padding: 14px 16px;
                            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
                        }

                        .admin-appointment-stat-card strong {
                            display: block;
                            font-size: 22px;
                            color: #0f172a;
                            margin-top: 4px;
                        }

                        .admin-appointment-stat-label {
                            font-size: 12px;
                            font-weight: 700;
                            letter-spacing: 0.08em;
                            text-transform: uppercase;
                            color: #6b7280;
                        }

                        .admin-bulk-toolbar {
                            display: flex;
                            justify-content: flex-start;
                            align-items: center;
                            gap: 12px;
                            margin-bottom: 12px;
                            padding: 14px 16px;
                            border: 1px solid #e6edf5;
                            border-radius: 16px;
                            background: #f8fbff;
                            flex-wrap: nowrap;
                            overflow: visible;
                        }

                        .admin-bulk-left,
                        .admin-bulk-right {
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            flex-wrap: nowrap;
                        }

                        /* Select-all stays left; action controls float right */
                        .admin-bulk-left { margin-left: 0; }
                        .admin-bulk-right { margin-left: auto; }

                        .admin-bulk-select-all {
                            padding: 8px 14px;
                            border: 1px solid #cbd5e1;
                            border-radius: 10px;
                            background: #fff;
                            color: #334155;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            font-weight: 600;
                            user-select: none;
                            white-space: nowrap;
                        }

                        .admin-bulk-select-all input { margin-right: 8px; }

                        .admin-bulk-select-all span {
                            display: inline-block;
                            min-width: 120px;
                            width: auto;
                            white-space: nowrap;
                            overflow: visible;
                            text-overflow: unset;
                        }

                        .admin-bulk-select {
                            min-height: 38px;
                            padding: 8px 10px;
                            border: 1px solid #cbd5e1;
                            border-radius: 10px;
                            background: #fff;
                            color: #0f172a;
                        }

                        .admin-bulk-apply {
                            min-height: 38px;
                            padding-inline: 16px;
                        }

                        .admin-appointment-card {
                            transition: all 0.2s ease;
                            position: relative;
                        }

                        .admin-appointment-left {
                            display: flex;
                            align-items: center;
                            gap: 12px;
                        }

                        /* Card checkboxes are hidden until selection mode is active */
                        .bulk-checkbox {
                            width: 18px;
                            height: 18px;
                            margin: 0;
                            flex: 0 0 auto;
                            display: none;
                        }

                        /* When selection mode is enabled on body, show checkboxes */
                        body.selection-mode .bulk-checkbox {
                            display: inline-block;
                        }

                        .admin-appointment-card:hover {
                            box-shadow: 0 4px 16px rgba(0, 102, 204, 0.15) !important;
                            transform: translateY(-2px);
                        }

                        @media (max-width: 1024px) {
                            .admin-appointment-stats {
                                grid-template-columns: repeat(2, minmax(0, 1fr));
                            }

                            .admin-bulk-toolbar {
                                align-items: stretch;
                                flex-direction: column;
                            }

                            .admin-bulk-right {
                                width: 100%;
                            }

                            /* Reset margin on smaller screens */
                            .admin-bulk-left { margin-left: 0; }
                        }

                        @media (max-width: 640px) {
                            .admin-appointment-stats {
                                grid-template-columns: 1fr;
                            }

                            .admin-bulk-right {
                                flex-direction: column;
                                align-items: stretch;
                            }

                            .admin-bulk-select,
                            .admin-bulk-apply {
                                padding: 8px 14px;
                                width: 100%;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                </script>

                <script>
                // Bulk action helper + selection mode
                (function () {
                    const toggleBtn = document.getElementById('toggleSelectModeBtn');
                    const cancelBtn = document.getElementById('cancelSelectBtn');
                    const bulkActionType = document.getElementById('bulkActionType');
                    const bulkCounselorSelect = document.getElementById('bulkCounselorSelect');
                    const bulkActionBtn = document.getElementById('bulkActionBtn');

                    function setSelectionMode(on) {
                        if (on) {
                            document.body.classList.add('selection-mode');
                            cancelBtn.style.display = '';
                            toggleBtn.textContent = 'Select All';
                            document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = false);
                        } else {
                            document.body.classList.remove('selection-mode');
                            cancelBtn.style.display = 'none';
                            toggleBtn.textContent = 'Select';
                            document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = false);
                        }
                    }

                    function areAllChecked() {
                        const boxes = document.querySelectorAll('.bulk-checkbox');
                        if (!boxes.length) return false;
                        return Array.from(boxes).every(cb => cb.checked);
                    }

                    function toggleSelectAll() {
                        const all = areAllChecked();
                        document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = !all);
                        toggleBtn.textContent = all ? 'Select All' : 'Deselect All';
                    }

                    toggleBtn.addEventListener('click', function () {
                        if (!document.body.classList.contains('selection-mode')) {
                            setSelectionMode(true);
                        } else {
                            toggleSelectAll();
                        }
                    });

                    cancelBtn.addEventListener('click', function () {
                        setSelectionMode(false);
                    });

                    document.addEventListener('change', function (e) {
                        if (!e.target.classList) return;
                        if (!e.target.classList.contains('bulk-checkbox')) return;
                        toggleBtn.textContent = areAllChecked() ? 'Deselect All' : 'Select All';
                    });

                    bulkActionBtn.addEventListener('click', function () {
                        const type = bulkActionType.value;
                        if (!type) { alert('Choose a bulk action'); return; }
                        const checkboxes = document.querySelectorAll('.bulk-checkbox');
                        const ids = [];
                        checkboxes.forEach(function (cb) { if (cb.checked) ids.push(cb.getAttribute('data-id')); });
                        if (ids.length === 0) { alert('No rows selected'); return; }

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        const a = document.createElement('input'); a.name = 'action'; a.value = 'bulk_action'; form.appendChild(a);
                        const at = document.createElement('input'); at.name = 'bulk_action_type'; at.value = type; form.appendChild(at);
                        if (type === 'assign') {
                            const cid = bulkCounselorSelect.value;
                            if (!cid || cid === '0') { alert('Choose a counselor to assign'); return; }
                            const c = document.createElement('input'); c.name = 'bulk_counselor_id'; c.value = cid; form.appendChild(c);
                        }
                        ids.forEach(function (id) { var i = document.createElement('input'); i.name = 'bulk_ids[]'; i.value = id; form.appendChild(i); });
                        document.body.appendChild(form);
                        form.submit();
                    });

                })();
                </script>

<script>
(function () {

})();
</script>
</body>
</html>



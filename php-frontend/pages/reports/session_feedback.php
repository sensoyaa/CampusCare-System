<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";
$pageTitle = "Session Feedback";
// Session feedback page removed from navigation — redirect to dashboard
header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
exit();
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "Counselor";

if ($role !== "Counselor") {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$showModal = false;
$activeAppointmentId = 0;
$activeStudentId = 0;
$activeNotes = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    if ($action === "save_notes") {
        $activeAppointmentId = intval($_POST["appointment_id"] ?? 0);
        $activeStudentId = intval($_POST["student_id"] ?? 0);
        $activeNotes = trim((string) ($_POST["notes"] ?? ""));
        $showModal = true;

        if ($activeAppointmentId <= 0 || $activeNotes === "") {
            $error = "Appointment and notes are required.";
        } else {
            $verifyStmt = $conn->prepare(
                "SELECT user_id
                 FROM appointments
                 WHERE id = ?
                   AND counselor_id = ?
                   AND COALESCE(NULLIF(status, ''), 'Pending') <> 'Cancelled'
                 LIMIT 1"
            );
            $verifyStmt->bind_param("ii", $activeAppointmentId, $userId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result()->fetch_assoc();
            $verifyStmt->close();

            if (!$verifyResult) {
                $error = "Session not found or not assigned to you.";
            } else {
                $studentIdFromAppointment = intval($verifyResult["user_id"] ?? 0);
                if ($activeStudentId <= 0) {
                    $activeStudentId = $studentIdFromAppointment;
                }

                if ($studentIdFromAppointment <= 0 || $activeStudentId !== $studentIdFromAppointment) {
                    $error = "Invalid student reference.";
                } else {
                    $saveStmt = $conn->prepare(
                        "INSERT INTO session_feedback (appointment_id, counselor_id, student_id, notes)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            notes = VALUES(notes),
                            updated_at = CURRENT_TIMESTAMP"
                    );
                    $saveStmt->bind_param("iiis", $activeAppointmentId, $userId, $activeStudentId, $activeNotes);

                    if ($saveStmt->execute()) {
                        $success = "Session notes saved.";
                        $showModal = false;
                        $activeAppointmentId = 0;
                        $activeStudentId = 0;
                        $activeNotes = "";
                    } else {
                        $error = "Failed to save notes. Please try again.";
                    }

                    $saveStmt->close();
                }
            }
        }
    }
}

$sessions = [];

$listStmt = $conn->prepare(
    "SELECT
        a.id AS appointment_id,
        a.service,
        a.appointment_date,
        a.appointment_time,
        COALESCE(NULLIF(a.status, ''), 'Pending') AS status,
        u.id AS student_user_id,
        u.full_name AS student_name,
        u.student_id,
        sf.notes
     FROM appointments a
     INNER JOIN users u ON a.user_id = u.id
     LEFT JOIN session_feedback sf ON sf.appointment_id = a.id
     WHERE a.counselor_id = ?
       AND u.role = 'Student'
       AND COALESCE(NULLIF(a.status, ''), 'Pending') <> 'Cancelled'
     ORDER BY a.appointment_date DESC, a.appointment_time DESC"
);
$listStmt->bind_param("i", $userId);
$listStmt->execute();
$listResult = $listStmt->get_result();

while ($row = $listResult->fetch_assoc()) {
    $dateTime = (string) ($row["appointment_date"] ?? "") . " " . (string) ($row["appointment_time"] ?? "");
    $noteText = trim((string) ($row["notes"] ?? ""));

    $sessions[] = [
        "appointment_id" => intval($row["appointment_id"] ?? 0),
        "student_user_id" => intval($row["student_user_id"] ?? 0),
        "student_name" => trim((string) ($row["student_name"] ?? "")),
        "student_id" => trim((string) ($row["student_id"] ?? "")),
        "service" => trim((string) ($row["service"] ?? "Counseling")),
        "status" => trim((string) ($row["status"] ?? "Pending")),
        "date_label" => date("M j, Y g:i A", strtotime($dateTime)),
        "notes" => $noteText,
        "saved" => $noteText !== "",
    ];
}

$listStmt->close();

function feedbackStatusClass($status)
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
        <div class="page-shell counselor-shell">
            <div class="manage-head">
                <div>
                    <h1 class="page-title">Session Feedback</h1>
                    <p class="page-subtitle">Add and update notes for your student counseling sessions.</p>
                </div>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($sessions)): ?>
                <article class="feedback-item">
                    <div class="feedback-left">
                        <span class="mini-user-icon"><?php echo sidebarIconSvg("user"); ?></span>
                        <div>
                            <p class="feedback-name">No sessions available yet</p>
                            <p class="feedback-meta">Completed counseling sessions will appear here for notes.</p>
                        </div>
                    </div>
                </article>
            <?php else: ?>
                <div class="feedback-list">
                    <?php foreach ($sessions as $session): ?>
                        <?php
                            $studentName = $session["student_name"] !== "" ? $session["student_name"] : ("Student #" . $session["student_user_id"]);
                            $studentIdLabel = $session["student_id"] !== "" ? $session["student_id"] : "N/A";
                        ?>

                        <article class="feedback-item">
                            <div class="feedback-left">
                                <span class="mini-user-icon"><?php echo sidebarIconSvg("user"); ?></span>
                                <div>
                                    <p class="feedback-name"><?php echo htmlspecialchars($studentName); ?></p>
                                    <p class="feedback-meta">
                                        ID: <?php echo htmlspecialchars($studentIdLabel); ?> &bull;
                                        <?php echo htmlspecialchars($session["service"]); ?> &bull;
                                        <?php echo htmlspecialchars($session["date_label"]); ?>
                                    </p>

                                    <?php if ($session["saved"]): ?>
                                        <div class="feedback-note">
                                            <div class="feedback-note-label">Saved Notes</div>
                                            <?php echo nl2br(htmlspecialchars($session["notes"])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="feedback-right">
                                <span class="status-pill <?php echo feedbackStatusClass($session["status"]); ?>">
                                    <?php echo htmlspecialchars($session["status"]); ?>
                                </span>

                                <button
                                    type="button"
                                    class="feedback-action"
                                    data-open-feedback-modal
                                    data-appointment="<?php echo intval($session["appointment_id"]); ?>"
                                    data-student="<?php echo intval($session["student_user_id"]); ?>"
                                    data-student-name="<?php echo htmlspecialchars($studentName, ENT_QUOTES); ?>"
                                    data-notes="<?php echo htmlspecialchars(rawurlencode($session["notes"]), ENT_QUOTES); ?>"
                                >
                                    <?php echo sidebarIconSvg("edit"); ?>
                                    <?php echo $session["saved"] ? "Edit Notes" : "Add Notes"; ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="modal-overlay<?php echo $showModal ? " open" : ""; ?>" id="feedbackModal" aria-hidden="<?php echo $showModal ? "false" : "true"; ?>">
            <div class="modal-card">
                <div class="modal-head">
                    <h3 id="feedbackModalTitle">Session Notes</h3>
                    <button type="button" class="modal-close" data-close-feedback-modal aria-label="Close">&times;</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_notes">
                    <input type="hidden" name="appointment_id" id="feedbackAppointmentId" value="<?php echo $activeAppointmentId; ?>">
                    <input type="hidden" name="student_id" id="feedbackStudentId" value="<?php echo $activeStudentId; ?>">

                    <div class="form-group">
                        <label for="feedbackNotes">Notes</label>
                        <textarea id="feedbackNotes" name="notes" rows="7" required><?php echo htmlspecialchars($activeNotes); ?></textarea>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" data-close-feedback-modal>Cancel</button>
                        <button type="submit" class="btn">Save Notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    const modal = document.getElementById("feedbackModal");
    if (!modal) {
        return;
    }

    const title = document.getElementById("feedbackModalTitle");
    const appointmentInput = document.getElementById("feedbackAppointmentId");
    const studentInput = document.getElementById("feedbackStudentId");
    const notesInput = document.getElementById("feedbackNotes");

    const openButtons = document.querySelectorAll("[data-open-feedback-modal]");
    const closeButtons = modal.querySelectorAll("[data-close-feedback-modal]");

    function openModal() {
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    openButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const appointmentId = button.getAttribute("data-appointment") || "0";
            const studentId = button.getAttribute("data-student") || "0";
            const studentName = button.getAttribute("data-student-name") || "Student";
            const notes = decodeURIComponent(button.getAttribute("data-notes") || "");

            appointmentInput.value = appointmentId;
            studentInput.value = studentId;
            notesInput.value = notes;
            title.textContent = "Session Notes - " + studentName;

            openModal();
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModal();
        }
    });
})();
</script>

</div>
<script>
(function () {

})();
</script>
</body>
</html>



<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Manage Schedule";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "Counselor";

if (!in_array($role, ["Counselor", "Administrator"], true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
    exit();
}

$error = "";
$success = "";
$shouldOpenModal = false;
$days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
$timeOptions = [];

for ($minutes = 8 * 60; $minutes <= 18 * 60; $minutes += 30) {
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $timeOptions[] = date("g:i A", strtotime(sprintf("%02d:%02d", $hour, $minute)));
}

$formDay = "Monday";
$formStart = "9:00 AM";
$formEnd = "10:00 AM";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = trim((string) ($_POST["action"] ?? ""));

    if ($action === "add_slot") {
        $formDay = trim((string) ($_POST["day"] ?? ""));
        $formStart = trim((string) ($_POST["start_time"] ?? ""));
        $formEnd = trim((string) ($_POST["end_time"] ?? ""));
        $shouldOpenModal = true;

        if (!in_array($formDay, $days, true) || !in_array($formStart, $timeOptions, true) || !in_array($formEnd, $timeOptions, true)) {
            $error = "Please choose valid slot details.";
        } else {
            $startSql = date("H:i:s", strtotime($formStart));
            $endSql = date("H:i:s", strtotime($formEnd));

            if (strtotime("2000-01-01 " . $endSql) <= strtotime("2000-01-01 " . $startSql)) {
                $error = "End time must be later than start time.";
            } else {
                $overlapStmt = $conn->prepare(
                    "SELECT id
                     FROM counselor_availability
                     WHERE counselor_id = ?
                       AND day = ?
                       AND NOT (end_time <= ? OR start_time >= ?)
                     LIMIT 1"
                );
                $overlapStmt->bind_param("isss", $userId, $formDay, $startSql, $endSql);
                $overlapStmt->execute();
                $overlapResult = $overlapStmt->get_result();
                $hasOverlap = (bool) $overlapResult->fetch_assoc();
                $overlapStmt->close();

                if ($hasOverlap) {
                    $error = "This slot overlaps with an existing schedule.";
                } else {
                    $insertStmt = $conn->prepare(
                        "INSERT INTO counselor_availability (counselor_id, day, start_time, end_time)
                         VALUES (?, ?, ?, ?)"
                    );
                    $insertStmt->bind_param("isss", $userId, $formDay, $startSql, $endSql);

                    if ($insertStmt->execute()) {
                        $success = "Availability slot added.";
                        $shouldOpenModal = false;
                        $formDay = "Monday";
                        $formStart = "9:00 AM";
                        $formEnd = "10:00 AM";
                    } else {
                        $error = "Could not save slot. Please try again.";
                    }

                    $insertStmt->close();
                }
            }
        }
    } elseif ($action === "delete_slot") {
        $slotId = intval($_POST["slot_id"] ?? 0);

        if ($slotId > 0) {
            $deleteStmt = $conn->prepare(
                "DELETE FROM counselor_availability
                 WHERE id = ? AND counselor_id = ?"
            );
            $deleteStmt->bind_param("ii", $slotId, $userId);

            if ($deleteStmt->execute()) {
                $success = "Slot removed.";
            } else {
                $error = "Failed to remove slot.";
            }

            $deleteStmt->close();
        } else {
            $error = "Invalid slot selected.";
        }
    }
}
$schedules = [];

$scheduleStmt = $conn->prepare("
    SELECT id, day, start_time, end_time
    FROM counselor_availability
    WHERE counselor_id = ?
    ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
             start_time ASC
");
$scheduleStmt->bind_param("i", $userId);
$scheduleStmt->execute();
$result = $scheduleStmt->get_result();

while ($row = $result->fetch_assoc()) {
    $schedules[] = $row;
}

$scheduleStmt->close();

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
            <style>
                /* Booking head (copied from book_appointment) with no top margin */
                .booking-head {
                    padding: 1.75rem 1.75rem 1.6rem;
                    background: var(--primary);
                    color: #fff;
                    border-radius: 22px;
                    margin-top: 0;
                    margin-bottom: 1rem;
                    box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
                    padding: 30px 34px 20px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 20px;
                }

                .booking-head h1 {
                    margin: 0 0 8px;
                    font-size: 34px;
                    color: #fff;
                }

                .booking-head p {
                    margin: 0;
                    max-width: 680px;
                    color: rgba(255,255,255,0.9);
                }

                .slot-add-btn { background: #fff; color: var(--primary); white-space: nowrap; }
            </style>

            <div class="booking-head">
                <div>
                    <h1 class="page-title">Manage Schedule</h1>
                    <p class="page-subtitle">Set the time slots students can book with you.</p>
                </div>

                <button type="button" class="btn slot-add-btn" id="openSlotModal">
                    <?php echo sidebarIconSvg("calendar-plus"); ?>
                    Add Slot
                </button>
            </div>

            <?php if ($error !== ""): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($schedules)): ?>
                <article class="slot-card">
                    <div class="slot-card-left">
                        <span class="slot-card-icon"><?php echo sidebarIconSvg("clock"); ?></span>
                        <div>
                            <p class="slot-card-day">No availability slots yet</p>
                            <p class="slot-card-time">Click Add Slot to create your first available session window.</p>
                        </div>
                    </div>
                </article>
            <?php else: ?>
                <?php foreach ($schedules as $slot): ?>
                    <article class="slot-card">
                        <div class="slot-card-left">
                            <span class="slot-card-icon"><?php echo sidebarIconSvg("clock"); ?></span>
                            <div>
                                <p class="slot-card-day"><?php echo htmlspecialchars((string) $slot["day"]); ?></p>
                                <p class="slot-card-time">
                                    <?php
                                        $startLabel = date("g:i A", strtotime((string) $slot["start_time"]));
                                        $endLabel = date("g:i A", strtotime((string) $slot["end_time"]));
                                        echo htmlspecialchars($startLabel . " - " . $endLabel);
                                    ?>
                                </p>
                            </div>
                        </div>

                        <form
                            method="POST"
                            data-confirm-title="Remove schedule slot"
                            data-confirm-message="Remove this availability slot from your schedule?"
                            data-confirm-button="Remove Slot"
                            data-confirm-variant="danger"
                        >
                            <input type="hidden" name="action" value="delete_slot">
                            <input type="hidden" name="slot_id" value="<?php echo intval($slot["id"]); ?>">
                            <button type="submit" class="delete-icon-btn" aria-label="Delete slot">
                                <?php echo sidebarIconSvg("trash"); ?>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="modal-overlay<?php echo $shouldOpenModal ? " open" : ""; ?>" id="slotModal" aria-hidden="<?php echo $shouldOpenModal ? "false" : "true"; ?>">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Add Availability Slot</h3>
                    <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="add_slot">

                    <div class="modal-grid">
                        <div class="form-group">
                            <label for="slot-day">Day</label>
                            <select id="slot-day" name="day" required>
                                <?php foreach ($days as $day): ?>
                                    <option value="<?php echo htmlspecialchars($day); ?>" <?php echo $formDay === $day ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($day); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="slot-start">Start Time</label>
                            <select id="slot-start" name="start_time" required>
                                <?php foreach ($timeOptions as $time): ?>
                                    <option value="<?php echo htmlspecialchars($time); ?>" <?php echo $formStart === $time ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($time); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="slot-end">End Time</label>
                            <select id="slot-end" name="end_time" required>
                                <?php foreach ($timeOptions as $time): ?>
                                    <option value="<?php echo htmlspecialchars($time); ?>" <?php echo $formEnd === $time ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($time); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                        <button type="submit" class="btn">Save Slot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    const modal = document.getElementById("slotModal");
    const openButton = document.getElementById("openSlotModal");

    if (!modal || !openButton) {
        return;
    }

    const closeButtons = modal.querySelectorAll("[data-close-modal]");

    function openModal() {
        modal.classList.add("open");
        modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
        modal.classList.remove("open");
        modal.setAttribute("aria-hidden", "true");
    }

    openButton.addEventListener("click", openModal);

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


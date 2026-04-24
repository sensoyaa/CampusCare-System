<?php
require_once "includes/auth.php";
requireLogin();
require_once "includes/db.php";

$pageTitle = "Book Appointment";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

if ($role !== "Student") {
    header("Location: dashboard.php");
    exit();
}

$success = "";
$error = "";
$chosenService = trim($_POST["service"] ?? ($_GET["service"] ?? ""));
$chosenTime = trim($_POST["appointment_time"] ?? "");

function formatTimeToMysql($time) {
    return date("H:i:s", strtotime($time));
}

function formatService($service) {
    if ($service === "counseling") return "Counseling";
    if ($service === "psychological-testing") return "Psychological Testing";
    return $service;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service = trim($_POST["service"] ?? "");
    $counselor_id = intval($_POST["counselor_id"] ?? 0);
    $appointment_date = trim($_POST["appointment_date"] ?? "");
    $appointment_time = trim($_POST["appointment_time"] ?? "");

    $chosenService = $service;
    $chosenTime = $appointment_time;

    if ($service === "" || $counselor_id <= 0 || $appointment_date === "" || $appointment_time === "") {
        $error = "Please complete all fields.";
    } else {
        $counselorQuery = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
        $counselorQuery->bind_param("i", $counselor_id);
        $counselorQuery->execute();
        $counselorResult = $counselorQuery->get_result();

        if ($counselorResult->num_rows === 0) {
            $error = "Selected counselor not found.";
        } else {
            $counselor = $counselorResult->fetch_assoc()["full_name"];
            $finalService = formatService($service);
            $finalTime = formatTimeToMysql($appointment_time);
            $status = "Pending";

            $stmt = $conn->prepare("
                INSERT INTO appointments 
                (user_id, counselor_id, service, counselor, appointment_date, appointment_time, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisssss",
                $userId,
                $counselor_id,
                $finalService,
                $counselor,
                $appointment_date,
                $finalTime,
                $status
            );

            if ($stmt->execute()) {
                $formattedDate = date("F j, Y", strtotime($appointment_date));

                header("Location: confirmation.php?" . http_build_query([
                    "service" => $finalService,
                    "counselor" => $counselor,
                    "date" => $formattedDate,
                    "time" => $appointment_time,
                ]));
                exit();
            } else {
                $error = "Failed to book appointment.";
            }

            $stmt->close();
        }

        $counselorQuery->close();
    }
}

$counselors = [];
$counselorSql = "
    SELECT id, full_name 
    FROM users 
    WHERE role IN ('Counselor', 'Counsellor', 'Counselors') 
      AND status = 'Active'
    ORDER BY full_name ASC
";
$counselorResult = $conn->query($counselorSql);

while ($row = $counselorResult->fetch_assoc()) {
    $counselors[] = $row;
}

$selectedCounselor = intval($_GET["counselor_id"] ?? 0);
$selectedDate = trim($_GET["date"] ?? "");
$selectedMonth = trim($_GET["month"] ?? "");

if ($selectedMonth === "" || !preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    if ($selectedDate !== "") {
        $selectedMonth = date("Y-m", strtotime($selectedDate));
    } else {
        $selectedMonth = date("Y-m");
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($selectedCounselor <= 0) {
        $selectedCounselor = intval($_POST["counselor_id"] ?? 0);
    }

    if ($selectedDate === "") {
        $selectedDate = trim($_POST["appointment_date"] ?? "");
    }

    if ($selectedDate !== "") {
        $selectedMonth = date("Y-m", strtotime($selectedDate));
    }
}

$monthStart = strtotime($selectedMonth . "-01");
$prevMonth = date("Y-m", strtotime("-1 month", $monthStart));
$nextMonth = date("Y-m", strtotime("+1 month", $monthStart));
$daysInMonth = intval(date("t", $monthStart));
$firstWeekday = intval(date("w", $monthStart));
$today = date("Y-m-d");

$availableSlots = [];

if ($selectedCounselor > 0 && $selectedDate !== "") {
    $dayName = date("l", strtotime($selectedDate));

    $availabilityStmt = $conn->prepare("
        SELECT start_time, end_time 
        FROM counselor_availability
        WHERE counselor_id = ? AND day = ?
        ORDER BY start_time ASC
    ");
    $availabilityStmt->bind_param("is", $selectedCounselor, $dayName);
    $availabilityStmt->execute();
    $availabilityResult = $availabilityStmt->get_result();

    $allSlots = [
        "8:00 AM",
        "9:00 AM",
        "10:00 AM",
        "11:00 AM",
        "12:00 PM",
        "1:00 PM",
        "2:00 PM",
        "3:00 PM",
        "4:00 PM",
        "5:00 PM"
    ];

    while ($range = $availabilityResult->fetch_assoc()) {
        $from = strtotime($range["start_time"]);
        $to = strtotime($range["end_time"]);

        foreach ($allSlots as $slot) {
            $slotTime = strtotime($slot);

            if ($slotTime >= $from && $slotTime <= $to && !in_array($slot, $availableSlots)) {
                $availableSlots[] = $slot;
            }
        }
    }

    $availabilityStmt->close();
}

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
        <div class="page-shell book-shell">
            <h1 class="page-title">Book Appointment</h1>
            <p class="page-subtitle">Schedule a session with our guidance team</p>

            <div class="stepper">
                <div class="step-item <?php echo $chosenService !== "" ? "done" : ""; ?>" id="step-service-item">
                    <span class="step-dot <?php echo $chosenService !== "" ? "done" : ""; ?>" id="step-service-dot">1</span>
                    <span class="step-label">Service</span>
                </div>
                <div class="step-item <?php echo $selectedCounselor > 0 ? "done" : ""; ?>" id="step-counselor-item">
                    <span class="step-dot <?php echo $selectedCounselor > 0 ? "done" : ""; ?>" id="step-counselor-dot">2</span>
                    <span class="step-label">Counselor</span>
                </div>
                <div class="step-item <?php echo $selectedDate !== "" ? "done" : ""; ?>" id="step-date-item">
                    <span class="step-dot <?php echo $selectedDate !== "" ? "done" : ""; ?>" id="step-date-dot">3</span>
                    <span class="step-label">Date</span>
                </div>
                <div class="step-item <?php echo $chosenTime !== "" ? "done" : ""; ?>" id="step-time-item">
                    <span class="step-dot <?php echo $chosenTime !== "" ? "done" : ""; ?>" id="step-time-dot">4</span>
                    <span class="step-label">Time</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="booking-grid">
                <div class="grid">
                    <section class="card">
                        <h2 class="card-title">Service Type</h2>

                        <div class="form-group">
                            <label for="service">Service</label>
                            <select id="service" name="service" form="book-form" required>
                                <option value="" disabled hidden <?php echo $chosenService === "" ? "selected" : ""; ?>>Select service</option>
                                <option value="counseling" <?php echo $chosenService === "counseling" ? "selected" : ""; ?>>Counseling</option>
                                <option value="psychological-testing" <?php echo $chosenService === "psychological-testing" ? "selected" : ""; ?>>Psychological Testing</option>
                            </select>
                        </div>

                        <form method="GET" id="filters-form">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth); ?>">
                            <input type="hidden" name="service" id="service_filter_value" value="<?php echo htmlspecialchars($chosenService); ?>">

                            <?php if ($selectedDate !== ""): ?>
                                <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="counselor_id">Counselor</label>
                                <select id="counselor_id" name="counselor_id" required onchange="var serviceInput=document.getElementById('service');var hiddenService=document.getElementById('service_filter_value');if(serviceInput&&hiddenService){hiddenService.value=serviceInput.value;}this.form.submit();">
                                    <option value="" disabled hidden <?php echo $selectedCounselor <= 0 ? "selected" : ""; ?>>Choose active counselor</option>
                                    <?php foreach ($counselors as $c): ?>
                                        <option value="<?php echo $c["id"]; ?>" <?php echo $selectedCounselor == $c["id"] ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($c["full_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn-outline">Load counselor schedule</button>
                        </form>
                    </section>

                    <section class="card">
                        <h2 class="card-title">Time Slot</h2>

                        <form method="POST" id="book-form">
                            <input type="hidden" name="counselor_id" value="<?php echo htmlspecialchars($selectedCounselor); ?>">
                            <input type="hidden" name="appointment_date" id="appointment_date_input" value="<?php echo htmlspecialchars($selectedDate); ?>">

                            <?php if ($selectedCounselor <= 0 || $selectedDate === ""): ?>
                                <p class="empty-state">Choose a counselor and date first to see available time slots.</p>
                            <?php elseif (empty($availableSlots)): ?>
                                <p class="empty-state">No available slots for this counselor on this date.</p>
                            <?php else: ?>
                                <div class="slot-grid">
                                    <?php foreach ($availableSlots as $slot): ?>
                                        <label class="slot-option">
                                            <input
                                                type="radio"
                                                name="appointment_time"
                                                value="<?php echo htmlspecialchars($slot); ?>"
                                                <?php echo $chosenTime === $slot ? "checked" : ""; ?>
                                            >
                                            <span><?php echo htmlspecialchars($slot); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 16px;">
                                <button
                                    type="submit"
                                    class="btn btn-block"
                                    <?php echo empty($availableSlots) ? "disabled" : ""; ?>
                                >
                                    Confirm Booking
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

                <section class="card">
                    <h2 class="card-title">Select Date</h2>

                    <div class="calendar-nav">
                        <a
                            href="book_appointment.php?<?php echo http_build_query(["month" => $prevMonth, "counselor_id" => $selectedCounselor, "service" => $chosenService]); ?>"
                            aria-label="Previous month"
                        >&lt;</a>

                        <h3><?php echo date("F Y", $monthStart); ?></h3>

                        <a
                            href="book_appointment.php?<?php echo http_build_query(["month" => $nextMonth, "counselor_id" => $selectedCounselor, "service" => $chosenService]); ?>"
                            aria-label="Next month"
                        >&gt;</a>
                    </div>

                    <div class="calendar-week">
                        <span>Su</span>
                        <span>Mo</span>
                        <span>Tu</span>
                        <span>We</span>
                        <span>Th</span>
                        <span>Fr</span>
                        <span>Sa</span>
                    </div>

                    <div class="calendar-days">
                        <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
                            <span class="calendar-blank"></span>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                            <?php
                                $dayDate = $selectedMonth . "-" . str_pad((string) $day, 2, "0", STR_PAD_LEFT);
                                $dayWeek = intval(date("w", strtotime($dayDate)));
                                $isWeekend = ($dayWeek === 0 || $dayWeek === 6);
                                $isPast = ($dayDate < $today);
                                $isDisabled = $isWeekend || $isPast;

                                $classes = "calendar-day";

                                if ($isDisabled) {
                                    $classes .= " disabled";
                                }

                                if ($dayDate === $selectedDate) {
                                    $classes .= " selected";
                                }

                                $dateLink = "book_appointment.php?" . http_build_query([
                                    "month" => $selectedMonth,
                                    "counselor_id" => $selectedCounselor,
                                    "service" => $chosenService,
                                    "date" => $dayDate,
                                ]);
                            ?>

                            <?php if ($isDisabled): ?>
                                <span class="<?php echo $classes; ?>"><?php echo $day; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $dateLink; ?>" class="<?php echo $classes; ?>"><?php echo $day; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <?php if ($selectedDate !== ""): ?>
                        <p class="card-subtitle" style="margin-top: 14px; margin-bottom: 0;">
                            Selected: <strong><?php echo date("l, F j, Y", strtotime($selectedDate)); ?></strong>
                        </p>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

<script>
(function () {
    const serviceSelect = document.getElementById("service");
    const counselorSelect = document.getElementById("counselor_id");
    const serviceFilterInput = document.getElementById("service_filter_value");
    const appointmentDateInput = document.getElementById("appointment_date_input");
    const timeInputs = document.querySelectorAll("input[name='appointment_time']");

    const stepServiceItem = document.getElementById("step-service-item");
    const stepServiceDot = document.getElementById("step-service-dot");
    const stepCounselorItem = document.getElementById("step-counselor-item");
    const stepCounselorDot = document.getElementById("step-counselor-dot");
    const stepDateItem = document.getElementById("step-date-item");
    const stepDateDot = document.getElementById("step-date-dot");
    const stepTimeItem = document.getElementById("step-time-item");
    const stepTimeDot = document.getElementById("step-time-dot");

    function toggleDone(item, dot, done) {
        if (item) {
            item.classList.toggle("done", done);
        }

        if (dot) {
            dot.classList.toggle("done", done);
        }
    }

    function syncServiceFilterValue() {
        if (serviceSelect && serviceFilterInput) {
            serviceFilterInput.value = serviceSelect.value;
        }
    }

    function hasSelectedTime() {
        for (let i = 0; i < timeInputs.length; i += 1) {
            if (timeInputs[i].checked) {
                return true;
            }
        }

        return false;
    }

    function updateStepState() {
        const hasService = !!(serviceSelect && serviceSelect.value.trim() !== "");
        const hasCounselor = !!(counselorSelect && counselorSelect.value.trim() !== "");
        const hasDate = !!(appointmentDateInput && appointmentDateInput.value.trim() !== "");
        const hasTime = hasSelectedTime();

        toggleDone(stepServiceItem, stepServiceDot, hasService);
        toggleDone(stepCounselorItem, stepCounselorDot, hasCounselor);
        toggleDone(stepDateItem, stepDateDot, hasDate);
        toggleDone(stepTimeItem, stepTimeDot, hasTime);
    }

    if (serviceSelect) {
        serviceSelect.addEventListener("change", function () {
            syncServiceFilterValue();
            updateStepState();
        });
    }

    for (let i = 0; i < timeInputs.length; i += 1) {
        timeInputs[i].addEventListener("change", updateStepState);
    }

    syncServiceFilterValue();
    updateStepState();
})();
</script>

</div>
</body>
</html>
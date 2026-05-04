<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "College Events";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "User";

$canJoin = $role === "Student";
$canCreateEvents = in_array($role, ["Administrator", "Counselor"], true);

$error = "";
$successAction = trim((string) ($_GET["success"] ?? ""));
$successMessages = [
    "created" => "Event created successfully.",
    "updated" => "Event updated successfully.",
    "deleted" => "Event deleted successfully.",
];
$success = $successMessages[$successAction] ?? "";
$openCreateModal = $canCreateEvents && (trim((string) ($_GET["open"] ?? "")) === "create");
$returnTo = $_SERVER["REQUEST_URI"] ?? "/campuscare-api/php-frontend/pages/events/events.php";

// Get selected college from query params
$selectedCollege = isset($_GET["college"]) ? trim($_GET["college"]) : null;

if (!$selectedCollege) {
    header("Location: /campuscare-api/php-frontend/pages/events/events.php");
    exit;
}

function tableColumnExists(mysqli $conn, string $tableName, string $columnName): bool
{
    $databaseResult = $conn->query("SELECT DATABASE() AS database_name");
    $databaseRow = $databaseResult ? $databaseResult->fetch_assoc() : null;
    $databaseName = trim((string) ($databaseRow["database_name"] ?? ""));

    if ($databaseName === "") {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $databaseName, $tableName, $columnName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function eventDateTimeFromInput(string $value): ?string
{
    $value = trim($value);

    if ($value === "") {
        return null;
    }

    $dateTime = DateTime::createFromFormat("Y-m-d\\TH:i", $value);

    if (!$dateTime) {
        return null;
    }

    return $dateTime->format("Y-m-d H:i:s");
}

function eventHasEnded(mysqli $conn, int $eventId): bool
{
    $stmt = $conn->prepare("SELECT starts_at, ends_at FROM events WHERE id = ? LIMIT 1");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return false;
    }

    $startTime = !empty($row["starts_at"]) ? strtotime((string) $row["starts_at"]) : false;
    if (!$startTime) {
        return false;
    }

    $endTime = !empty($row["ends_at"]) ? strtotime((string) $row["ends_at"]) : ($startTime + (2 * 60 * 60));
    return $endTime <= time();
}

function collegeAliases(string $college): array
{
    static $aliasMap = [
        "College of Technology" => ["college of technology", "technology"],
        "College of Public Administration and Governance" => ["college of public administration and governance", "public administration and governance"],
        "College of Nursing" => ["college of nursing", "nursing"],
        "College of Medicine" => ["college of medicine", "medicine"],
        "College of Law" => ["college of law", "law"],
        "College of Education" => ["college of education", "education"],
        "College of Business" => ["college of business", "business"],
        "College of Arts and Sciences" => ["college of arts and sciences", "college of art and sciences", "arts and sciences", "art and sciences"],
    ];

    return $aliasMap[$college] ?? [strtolower(trim($college))];
}

$eventsHasCollege = tableColumnExists($conn, "events", "college");
$eventsHasStartsAt = tableColumnExists($conn, "events", "starts_at");
$eventsHasEndsAt = tableColumnExists($conn, "events", "ends_at");
$eventsHasCreatedBy = tableColumnExists($conn, "events", "created_by_user_id");
$eventsHasEventDate = tableColumnExists($conn, "events", "event_date");
$eventsHasEventTime = tableColumnExists($conn, "events", "event_time");
$eventsHasCategory = tableColumnExists($conn, "events", "category");
$eventsHasParticipantLimit = tableColumnExists($conn, "events", "participant_limit");
$usersHasCollege = tableColumnExists($conn, "users", "college");

if (!$eventsHasStartsAt) {
    $conn->query("ALTER TABLE events ADD COLUMN starts_at DATETIME DEFAULT NULL AFTER description");
    $eventsHasStartsAt = tableColumnExists($conn, "events", "starts_at");
}

if (!$eventsHasEndsAt) {
    $conn->query("ALTER TABLE events ADD COLUMN ends_at DATETIME DEFAULT NULL AFTER starts_at");
    $eventsHasEndsAt = tableColumnExists($conn, "events", "ends_at");
}

if (!$eventsHasCreatedBy) {
    $conn->query("ALTER TABLE events ADD COLUMN created_by_user_id INT(11) DEFAULT NULL AFTER ends_at");
    $eventsHasCreatedBy = tableColumnExists($conn, "events", "created_by_user_id");
}

if (!$eventsHasCollege) {
    $conn->query("ALTER TABLE events ADD COLUMN college VARCHAR(150) DEFAULT NULL AFTER created_by_user_id");
    $eventsHasCollege = tableColumnExists($conn, "events", "college");
}

if (!$eventsHasCategory) {
    $conn->query("ALTER TABLE events ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER description");
    $eventsHasCategory = tableColumnExists($conn, "events", "category");
}

if (!$eventsHasParticipantLimit) {
    $conn->query("ALTER TABLE events ADD COLUMN participant_limit INT(11) DEFAULT NULL AFTER college");
    $eventsHasParticipantLimit = tableColumnExists($conn, "events", "participant_limit");
}

// Create event_checkins table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS event_checkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_event_user (event_id, user_id),
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

if ($eventsHasStartsAt && $eventsHasEventDate && $eventsHasEventTime) {
    $conn->query("UPDATE events SET starts_at = TIMESTAMP(event_date, event_time) WHERE starts_at IS NULL AND event_date IS NOT NULL AND event_time IS NOT NULL");
}

if ($eventsHasCategory) {
    $conn->query("UPDATE events SET category = description WHERE (category IS NULL OR category = '') AND description IS NOT NULL AND description IN ('Brownbag','Wellness','Training','Forum','Workshop','Event')");
    $conn->query("UPDATE events SET description = NULL WHERE category = description AND description IN ('Brownbag','Wellness','Training','Forum','Workshop','Event')");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create" && $canCreateEvents) {
        $title = trim((string) ($_POST["title"] ?? ""));
        $category = trim((string) ($_POST["category"] ?? ""));
        $description = trim((string) ($_POST["description"] ?? ""));
        $location = trim((string) ($_POST["location"] ?? ""));
        $startsAt = eventDateTimeFromInput((string) ($_POST["starts_at"] ?? ""));
        $endsAt = eventDateTimeFromInput((string) ($_POST["ends_at"] ?? ""));
        $participantLimit = !empty($_POST["participant_limit"]) ? intval($_POST["participant_limit"]) : null;
        $eventDate = $startsAt !== null ? substr($startsAt, 0, 10) : null;
        $eventTime = $startsAt !== null ? substr($startsAt, 11, 8) : null;
        $openCreateModal = true;

        if ($title === "" || $category === "" || $location === "" || $startsAt === null) {
            $error = "Please fill in the event title, category, start date and location.";
        } elseif ($endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = "End date and time must be later than the start date and time.";
        } elseif ($eventsHasCollege && $eventsHasStartsAt && $eventsHasCreatedBy) {
            if ($eventsHasEventDate && $eventsHasEventTime) {
                $stmt = $conn->prepare("
                    INSERT INTO events (title, event_date, event_time, location, description, category, starts_at, ends_at, created_by_user_id, college, participant_limit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO events (title, description, category, location, starts_at, ends_at, created_by_user_id, college, participant_limit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            }

            if ($stmt) {
                if ($eventsHasEventDate && $eventsHasEventTime) {
                    $stmt->bind_param("ssssssssisi", $title, $eventDate, $eventTime, $location, $description, $category, $startsAt, $endsAt, $userId, $selectedCollege, $participantLimit);
                } else {
                    $stmt->bind_param("ssssssisi", $title, $description, $category, $location, $startsAt, $endsAt, $userId, $selectedCollege, $participantLimit);
                }

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: /campuscare-api/php-frontend/pages/events/view_college_events.php?college=" . urlencode($selectedCollege) . "&success=created");
                    exit;
                }

                $stmt->close();
            }

            $error = "Failed to create event.";
        } else {
            $error = "Events cannot be assigned to a college because the events table could not be updated.";
        }
    }

    if ($action === "update" && $canCreateEvents) {
        $eventId = intval($_POST["event_id"] ?? 0);
        $title = trim((string) ($_POST["title"] ?? ""));
        $category = trim((string) ($_POST["category"] ?? ""));
        $description = trim((string) ($_POST["description"] ?? ""));
        $location = trim((string) ($_POST["location"] ?? ""));
        $startsAt = eventDateTimeFromInput((string) ($_POST["starts_at"] ?? ""));
        $endsAt = eventDateTimeFromInput((string) ($_POST["ends_at"] ?? ""));
        $participantLimit = !empty($_POST["participant_limit"]) ? intval($_POST["participant_limit"]) : null;
        $eventDate = $startsAt !== null ? substr($startsAt, 0, 10) : null;
        $eventTime = $startsAt !== null ? substr($startsAt, 11, 8) : null;

        if ($eventId <= 0) {
            $error = "Invalid event ID.";
        } elseif (eventHasEnded($conn, $eventId)) {
            $error = "You cannot edit an event that has already ended.";
        } elseif ($title === "" || $category === "" || $location === "" || $startsAt === null) {
            $error = "Please fill in the event title, category, start date and location.";
        } elseif ($endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = "End date and time must be later than the start date and time.";
        } elseif ($eventsHasEventDate && $eventsHasEventTime) {
            $stmt = $conn->prepare("
                UPDATE events
                SET title = ?, event_date = ?, event_time = ?, location = ?, description = ?, category = ?, starts_at = ?, ends_at = ?, college = ?, participant_limit = ?
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param("sssssssssii", $title, $eventDate, $eventTime, $location, $description, $category, $startsAt, $endsAt, $selectedCollege, $participantLimit, $eventId);

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: /campuscare-api/php-frontend/pages/events/view_college_events.php?college=" . urlencode($selectedCollege) . "&success=updated");
                    exit;
                }

                $stmt->close();
            }

            $error = "Failed to update event.";
        }
    }

    if ($action === "delete" && $canCreateEvents) {
        $eventId = intval($_POST["event_id"] ?? 0);

        if ($eventId <= 0) {
            $error = "Invalid event ID.";
        } else {
            $participantStmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ?");

            if ($participantStmt) {
                $participantStmt->bind_param("i", $eventId);
                $participantStmt->execute();
                $participantStmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");

            if ($stmt) {
                $stmt->bind_param("i", $eventId);

                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: /campuscare-api/php-frontend/pages/events/view_college_events.php?college=" . urlencode($selectedCollege) . "&success=deleted");
                    exit;
                }

                $stmt->close();
            }

            $error = "Failed to delete event.";
        }
    }

    if ($action === "join" && $canJoin) {
        $event_id = intval($_POST["event_id"] ?? 0);

        if ($event_id <= 0) {
            $error = "Invalid event ID.";
        } else {
            $stmt = $conn->prepare("
                INSERT IGNORE INTO event_participants (event_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $event_id, $userId);

            if ($stmt->execute()) {
                $success = "You joined the event successfully.";
                // Refresh to update UI
                header("Refresh:0");
                exit;
            } else {
                $error = "Failed to join event.";
            }

            $stmt->close();
        }
    }

    if ($action === "unjoin" && $canJoin) {
        $event_id = intval($_POST["event_id"] ?? 0);

        if ($event_id <= 0) {
            $error = "Invalid event ID.";
        } else {
            $stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $event_id, $userId);

            if ($stmt->execute()) {
                $success = "You left the event successfully.";
                header("Refresh:0");
                exit;
            } else {
                $error = "Failed to leave event.";
            }

            $stmt->close();
        }
    }

    if ($action === "checkin" && $canJoin) {
        $event_id = intval($_POST["event_id"] ?? 0);

        if ($event_id <= 0) {
            $error = "Invalid event ID.";
        } else {
            // Check if user has joined the event
            $checkJoinStmt = $conn->prepare("SELECT event_id FROM event_participants WHERE event_id = ? AND user_id = ?");
            $checkJoinStmt->bind_param("ii", $event_id, $userId);
            $checkJoinStmt->execute();
            $joinResult = $checkJoinStmt->get_result();
            
            if ($joinResult->num_rows === 0) {
                $error = "You must join the event before checking in.";
            } else {
                // Check if check-in window is valid (event has started)
                $eventStmt = $conn->prepare("SELECT starts_at, ends_at FROM events WHERE id = ?");
                $eventStmt->bind_param("i", $event_id);
                $eventStmt->execute();
                $eventResult = $eventStmt->get_result();
                $eventRow = $eventResult->fetch_assoc();
                
                if ($eventRow) {
                    $eventStart = strtotime($eventRow["starts_at"]);
                    $eventEnd = !empty($eventRow["ends_at"]) ? strtotime($eventRow["ends_at"]) : ($eventStart + (2 * 60 * 60)); // 2 hours after start if no end time
                    $currentTime = time();
                    $checkInStart = $eventStart - (20 * 60); // 20 minutes before
                    $checkInEnd = $eventEnd;
                    
                    // Debug logging
                    error_log("Check-in Debug - Event Start: " . date("Y-m-d H:i:s", $eventStart));
                    error_log("Check-in Debug - Event End: " . date("Y-m-d H:i:s", $eventEnd));
                    error_log("Check-in Debug - Current Time: " . date("Y-m-d H:i:s", $currentTime));
                    error_log("Check-in Debug - Check-in Window: " . date("Y-m-d H:i:s", $checkInStart) . " to " . date("Y-m-d H:i:s", $checkInEnd));
                    error_log("Check-in Debug - Is Check-in Window: " . ($currentTime >= $checkInStart && $currentTime <= $checkInEnd ? "Yes" : "No"));
                    
                    if ($currentTime < $checkInStart) {
                        $error = "Check-in will be available 20 minutes before the event starts.";
                    } elseif ($currentTime > $checkInEnd) {
                        $error = "Check-in is no longer available for this event.";
                    } else {
                        // Check if already checked in
                        $checkCheckinStmt = $conn->prepare("SELECT id FROM event_checkins WHERE event_id = ? AND user_id = ?");
                        $checkCheckinStmt->bind_param("ii", $event_id, $userId);
                        $checkCheckinStmt->execute();
                        $checkinResult = $checkCheckinStmt->get_result();
                        
                        if ($checkinResult->num_rows > 0) {
                            $error = "You have already checked in to this event.";
                        } else {
                            // Perform check-in
                            $checkinStmt = $conn->prepare("INSERT INTO event_checkins (event_id, user_id) VALUES (?, ?)");
                            $checkinStmt->bind_param("ii", $event_id, $userId);
                            
                            if ($checkinStmt->execute()) {
                                $success = "You have successfully checked in to the event.";
                                header("Refresh:0");
                                exit;
                            } else {
                                $error = "Failed to check in to the event.";
                            }
                            
                            $checkinStmt->close();
                        }
                        
                        $checkCheckinStmt->close();
                    }
                } else {
                    $error = "Event not found.";
                }
                
                $eventStmt->close();
            }
            
            $checkJoinStmt->close();
        }
    }
}

// Get events from selected college
$events = [];
$collegeSelect = "''";
$eventCollegeWhere = "1 = 0";
$selectedCollegeAliases = collegeAliases($selectedCollege);
$aliasPlaceholders = implode(", ", array_fill(0, count($selectedCollegeAliases), "?"));

if ($eventsHasCollege && $usersHasCollege) {
    $collegeSelect = "COALESCE(NULLIF(e.college, ''), u.college)";
    $eventCollegeWhere = "LOWER(TRIM(COALESCE(NULLIF(e.college, ''), u.college, ''))) IN ({$aliasPlaceholders})";
} elseif ($eventsHasCollege) {
    $collegeSelect = "e.college";
    $eventCollegeWhere = "LOWER(TRIM(COALESCE(NULLIF(e.college, ''), ''))) IN ({$aliasPlaceholders})";
} elseif ($usersHasCollege) {
    $collegeSelect = "u.college";
    $eventCollegeWhere = "LOWER(TRIM(COALESCE(NULLIF(u.college, ''), ''))) IN ({$aliasPlaceholders})";
}
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.title,
        e.description,
        e.category,
        e.location,
        e.starts_at,
        e.ends_at,
        e.created_by_user_id,
        u.full_name AS created_by_name,
        {$collegeSelect} AS college,
        (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) AS participant_count,
        (SELECT COUNT(*) FROM event_checkins WHERE event_id = e.id) AS checkin_count
    FROM events e
    LEFT JOIN users u ON e.created_by_user_id = u.id
    WHERE {$eventCollegeWhere}
    ORDER BY e.starts_at ASC
");

if ($stmt) {
    $stmt->bind_param(str_repeat("s", count($selectedCollegeAliases)), ...$selectedCollegeAliases);
    $stmt->execute();
    $eventResult = $stmt->get_result();

    while ($row = $eventResult->fetch_assoc()) {
        $events[] = $row;
    }

    $stmt->close();
}

// Get user's joined events
$joinedEvents = [];
if ($canJoin) {
    $joinedStmt = $conn->prepare("SELECT event_id FROM event_participants WHERE user_id = ?");
    $joinedStmt->bind_param("i", $userId);
    $joinedStmt->execute();
    $joinedResult = $joinedStmt->get_result();

    while ($row = $joinedResult->fetch_assoc()) {
        $joinedEvents[] = intval($row["event_id"]);
    }

    $joinedStmt->close();
}

// Get user's checked-in events
$checkedInEvents = [];
if ($canJoin) {
    $checkedInStmt = $conn->prepare("SELECT event_id FROM event_checkins WHERE user_id = ?");
    $checkedInStmt->bind_param("i", $userId);
    $checkedInStmt->execute();
    $checkedInResult = $checkedInStmt->get_result();

    while ($row = $checkedInResult->fetch_assoc()) {
        $checkedInEvents[] = intval($row["event_id"]);
    }

    $checkedInStmt->close();
}

// Separate events into upcoming and ended
$upcomingEvents = [];
$endedEvents = [];
$currentTime = time();

foreach ($events as $event) {
    $eventEndTime = !empty($event["ends_at"]) ? strtotime($event["ends_at"]) : strtotime($event["starts_at"]);
    if ($eventEndTime >= $currentTime) {
        $upcomingEvents[] = $event;
    } else {
        $endedEvents[] = $event;
    }
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
        <div class="page-shell">
            <div class="dashboard-head">
                <div>
                    <h1 class="page-title">Events from <?php echo htmlspecialchars($selectedCollege); ?></h1>
                    <p class="page-subtitle" style="tab-size: 4; white-space: pre;">>Discover all available events</p>
                </div>
                <?php if (isset($_GET['debug_joins']) && $_GET['debug_joins'] == '1'): ?>
                    <div class="alert alert-info" style="margin-left:16px;">
                        <strong>Debug:</strong> Joined events for current user (id <?php echo intval($userId); ?>): <?php echo htmlspecialchars(implode(', ', $joinedEvents) ?: 'none'); ?>. Checked-in: <?php echo htmlspecialchars(implode(', ', $checkedInEvents) ?: 'none'); ?>.
                    </div>
                <?php endif; ?>
                <div class="events-head-actions">
                    <?php if ($canCreateEvents): ?>
                        <button type="button" class="btn" id="openCreateModal">
                            <?php echo sidebarIconSvg("plus"); ?>
                            Create Event
                        </button>
                    <?php endif; ?>
                    <a href="events.php" class="btn btn-outline">Back to Colleges</a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Events Display Section -->
            <section class="events-section">
                <?php if (empty($upcomingEvents) && empty($endedEvents)): ?>
                    <div class="empty-state">
                        <p>No events available from this college yet.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($upcomingEvents)): ?>
                        <div class="events-subsection">
                            <h3 class="events-subsection-title">Upcoming</h3>
                            <div class="events-list">
                                <?php foreach ($upcomingEvents as $event): ?>
                                    <?php
                                        $eventId = intval($event["id"]);
                                        $isJoined = in_array($eventId, $joinedEvents, true);
                                        $isCheckedIn = in_array($eventId, $checkedInEvents, true);
                                        $startDateTime = new DateTime($event["starts_at"]);
                                        $eventMonthLabel = $startDateTime->format("M");
                                        $eventDayLabel = $startDateTime->format("j");
                                        $dateStr = $startDateTime->format("M d, Y");
                                        $timeStr = $startDateTime->format("g:i A");
                                        $endTimeStr = !empty($event["ends_at"]) ? (new DateTime($event["ends_at"]))->format("g:i A") : "";
                                        $displayTime = $endTimeStr !== "" ? $timeStr . " - " . $endTimeStr : $timeStr;
                                        $eventCategory = trim((string) ($event["category"] ?? "")) !== "" ? (string) $event["category"] : "Event";
                                        $eventDescription = trim((string) ($event["description"] ?? ""));
                                        $hasEventStarted = time() >= strtotime($event["starts_at"]);
                                        
                                        // Debug: Add data attributes for troubleshooting
                                        $debugData = [
                                            'isJoined' => $isJoined,
                                            'isCheckedIn' => $isCheckedIn,
                                            'hasEventStarted' => $hasEventStarted,
                                            'canJoin' => $canJoin,
                                            'currentTime' => time(),
                                            'currentTimeFormatted' => date("Y-m-d H:i:s"),
                                            'eventStart' => strtotime($event["starts_at"]),
                                            'eventStartFormatted' => $event["starts_at"],
                                            'eventTitle' => $event["title"]
                                        ];
                                    ?>
                                    <article class="event-card"
                                        onclick="window.location.href='/campuscare-api/php-frontend/pages/events/event_detail.php?id=<?php echo $eventId; ?>&return_to=<?php echo rawurlencode($returnTo); ?>'"
                                        data-debug="<?php echo htmlspecialchars(json_encode($debugData)); ?>"
                                        data-event-start="<?php echo htmlspecialchars(date("c", strtotime($event["starts_at"]))); ?>"
                                        data-event-end="<?php echo !empty($event["ends_at"]) ? htmlspecialchars(date("c", strtotime($event["ends_at"]))) : ""; ?>"
                                        data-event-id="<?php echo $eventId; ?>"
                                        data-joined="<?php echo $isJoined ? '1' : '0'; ?>"
                                        data-checkedin="<?php echo $isCheckedIn ? '1' : '0'; ?>">
                                        <div class="event-card-date">
                                            <span class="event-card-date-month"><?php echo htmlspecialchars($eventMonthLabel); ?></span>
                                            <span class="event-card-date-day"><?php echo htmlspecialchars($eventDayLabel); ?></span>
                                        </div>
                                        <div class="event-card-main">
                                            <div class="event-card-header">
                                                <h3><?php echo htmlspecialchars($event["title"]); ?></h3>
                                                <span class="event-category"><?php echo htmlspecialchars($eventCategory); ?></span>
                                            </div>
                                            <div class="event-card-meta">
                                                <span class="meta-item"><?php echo sidebarIconSvg("clock"); ?> <?php echo htmlspecialchars($displayTime); ?></span>
                                                <span class="meta-item"><?php echo sidebarIconSvg("pin"); ?> <?php echo htmlspecialchars($event["location"]); ?></span>
                                            </div>
                                        </div>
                                        <div class="event-card-footer" onclick="event.stopPropagation()">
                                            <div class="event-card-action-stack">
                                                <?php if ($canJoin): ?>
                                                    <div class="event-card-actions-row">
                                                        <?php if ($isJoined): ?>
                                                            <?php if ($isCheckedIn): ?>
                                                                <div class="checkin-status-card">
                                                                    <span class="checkin-icon">✓</span>
                                                                    <span>Checked In</span>
                                                                </div>
                                                            <?php else: ?>
                                                                <form method="POST" class="event-card-action-form">
                                                                    <input type="hidden" name="action" value="checkin">
                                                                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                                    <button type="submit" class="btn btn-primary event-checkin-btn" style="display: none;">Check In</button>
                                                                </form>
                                                            <?php endif; ?>

                                                            <?php if (!$hasEventStarted): ?>
                                                                <form method="POST" class="event-card-action-form">
                                                                    <input type="hidden" name="action" value="unjoin">
                                                                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                                    <button type="submit" class="btn btn-outline event-join-btn event-unjoin-btn">Unjoin</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn joined-btn" disabled>Joined</button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <form method="POST" class="event-card-action-form">
                                                                <input type="hidden" name="action" value="join">
                                                                <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                                <button type="submit" class="btn event-join-btn">Join</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($canCreateEvents): ?>
                                                    <div class="event-manage-actions">
                                                        <button
                                                            type="button"
                                                            class="btn btn-outline event-manage-btn"
                                                            data-edit-event
                                                            data-event-id="<?php echo $eventId; ?>"
                                                            data-title="<?php echo htmlspecialchars((string) ($event["title"] ?? ""), ENT_QUOTES); ?>"
                                                            data-category="<?php echo htmlspecialchars($eventCategory, ENT_QUOTES); ?>"
                                                            data-description="<?php echo htmlspecialchars($eventDescription, ENT_QUOTES); ?>"
                                                            data-location="<?php echo htmlspecialchars((string) ($event["location"] ?? ""), ENT_QUOTES); ?>"
                                                            data-starts-at="<?php echo htmlspecialchars(date("Y-m-d\\TH:i", strtotime((string) ($event["starts_at"] ?? ""))), ENT_QUOTES); ?>"
                                                            data-ends-at="<?php echo !empty($event["ends_at"]) ? htmlspecialchars(date("Y-m-d\\TH:i", strtotime((string) $event["ends_at"])), ENT_QUOTES) : ""; ?>"
                                                        >Edit</button>
                                                        <form method="POST" class="event-card-action-form" data-confirm-title="Delete event" data-confirm-message="Delete this event permanently?" data-confirm-button="Delete Event" data-confirm-variant="danger">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                            <button type="submit" class="btn btn-outline event-manage-btn">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="status-badge available">View Details</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="event-card-attendees"><?php echo sidebarIconSvg("users"); ?> <?php echo intval($event["participant_count"]); ?> joined</span>
                                        </div>
                                    </article>
                        <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($endedEvents)): ?>
                        <div class="events-subsection">
                            <h3 class="events-subsection-title">Ended</h3>
                            <div class="events-list">
                                <?php foreach ($endedEvents as $event): ?>
                                    <?php
                                        $eventId = intval($event["id"]);
                                        $isJoined = in_array($eventId, $joinedEvents, true);
                                        $isCheckedIn = in_array($eventId, $checkedInEvents, true);
                                        $startDateTime = new DateTime($event["starts_at"]);
                                        $eventMonthLabel = $startDateTime->format("M");
                                        $eventDayLabel = $startDateTime->format("j");
                                        $dateStr = $startDateTime->format("M d, Y");
                                        $timeStr = $startDateTime->format("g:i A");
                                        $endTimeStr = !empty($event["ends_at"]) ? (new DateTime($event["ends_at"]))->format("g:i A") : "";
                                        $displayTime = $endTimeStr !== "" ? $timeStr . " - " . $endTimeStr : $timeStr;
                                        $eventCategory = trim((string) ($event["category"] ?? "")) !== "" ? (string) $event["category"] : "Event";
                                        $eventDescription = trim((string) ($event["description"] ?? ""));
                                        $hasEventStarted = time() >= strtotime($event["starts_at"]);
                                        
                                        // Debug: Add data attributes for troubleshooting
                                        $debugData = [
                                            'isJoined' => $isJoined,
                                            'isCheckedIn' => $isCheckedIn,
                                            'hasEventStarted' => $hasEventStarted,
                                            'canJoin' => $canJoin,
                                            'currentTime' => time(),
                                            'currentTimeFormatted' => date("Y-m-d H:i:s"),
                                            'eventStart' => strtotime($event["starts_at"]),
                                            'eventStartFormatted' => $event["starts_at"],
                                            'eventTitle' => $event["title"]
                                        ];
                                    ?>
                                    <article class="event-card"
                                        onclick="window.location.href='/campuscare-api/php-frontend/pages/events/event_detail.php?id=<?php echo $eventId; ?>&return_to=<?php echo rawurlencode($returnTo); ?>'"
                                        data-debug="<?php echo htmlspecialchars(json_encode($debugData)); ?>"
                                        data-event-start="<?php echo htmlspecialchars(date("c", strtotime($event["starts_at"]))); ?>"
                                        data-event-end="<?php echo !empty($event["ends_at"]) ? htmlspecialchars(date("c", strtotime($event["ends_at"]))) : ""; ?>"
                                        data-event-id="<?php echo $eventId; ?>"
                                        data-joined="<?php echo $isJoined ? '1' : '0'; ?>"
                                        data-checkedin="<?php echo $isCheckedIn ? '1' : '0'; ?>">
                                        <div class="event-card-date">
                                            <span class="event-card-date-month"><?php echo htmlspecialchars($eventMonthLabel); ?></span>
                                            <span class="event-card-date-day"><?php echo htmlspecialchars($eventDayLabel); ?></span>
                                        </div>
                                        <div class="event-card-main">
                                            <div class="event-card-header">
                                                <h3><?php echo htmlspecialchars($event["title"]); ?></h3>
                                                <span class="event-category"><?php echo htmlspecialchars($eventCategory); ?></span>
                                            </div>
                                            <div class="event-card-meta">
                                                <span class="meta-item"><?php echo sidebarIconSvg("clock"); ?> <?php echo htmlspecialchars($displayTime); ?></span>
                                                <span class="meta-item"><?php echo sidebarIconSvg("pin"); ?> <?php echo htmlspecialchars($event["location"]); ?></span>
                                            </div>
                                        </div>
                                        <div class="event-card-footer" onclick="event.stopPropagation()">
                                            <div class="event-card-action-stack">
                                                <?php if ($canJoin): ?>
                                                    <div class="event-card-actions-row">
                                                        <?php if ($isCheckedIn): ?>
                                                            <div class="checkin-status-card">
                                                                <span class="checkin-icon">✓</span>
                                                                <span>Checked In</span>
                                                            </div>
                                                        <?php elseif ($isJoined): ?>
                                                            <button type="button" class="btn joined-btn" disabled>Attended</button>
                                                        <?php else: ?>
                                                            <span class="status-badge muted">Event Ended</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($canCreateEvents): ?>
                                                    <div class="event-manage-actions">
                                                        <form method="POST" class="event-card-action-form" data-confirm-title="Delete event" data-confirm-message="Delete this event permanently?" data-confirm-button="Delete Event" data-confirm-variant="danger">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                            <button type="submit" class="btn btn-outline event-manage-btn">Delete</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="status-badge muted">Event Ended</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="event-card-attendees"><?php echo sidebarIconSvg("users"); ?> <?php echo intval($event["participant_count"]); ?> joined</span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php if ($canCreateEvents): ?>
<div id="createModal" class="modal-overlay<?php echo $openCreateModal ? " open" : ""; ?>">
    <div class="modal-card event-details-modal">
        <button class="modal-close" type="button" data-close-create-modal>&times;</button>
        <div class="event-modal-head">
            <h2 id="eventFormTitle">Create Event</h2>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="eventFormAction" value="create">
            <input type="hidden" name="event_id" id="eventFormId" value="">

            <div class="event-modal-body">
                <div class="form-group">
                    <label for="eventTitle">Title</label>
                    <input id="eventTitle" type="text" name="title" value="<?php echo htmlspecialchars((string) ($_POST["title"] ?? "")); ?>" placeholder="Event title" required>
                </div>

                <div class="form-group">
                    <label for="eventCategory">Category</label>
                    <input id="eventCategory" type="text" name="category" value="<?php echo htmlspecialchars((string) ($_POST["category"] ?? "")); ?>" placeholder="Brownbag session, seminar, forum..." required>
                </div>

                <div class="form-group">
                    <label for="eventDescription">Description</label>
                    <textarea id="eventDescription" name="description" rows="4" placeholder="Add event details, goals, reminders, or what participants should expect."><?php echo htmlspecialchars((string) ($_POST["description"] ?? "")); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="eventLocation">Location</label>
                    <input id="eventLocation" type="text" name="location" value="<?php echo htmlspecialchars((string) ($_POST["location"] ?? "")); ?>" placeholder="Room, hall, or online link" required>
                </div>

                <div class="form-group">
                    <label for="eventStartsAt">Starts</label>
                    <input id="eventStartsAt" type="datetime-local" name="starts_at" value="<?php echo htmlspecialchars((string) ($_POST["starts_at"] ?? "")); ?>" required>
                </div>

                <div class="form-group">
                    <label for="eventEndsAt">Ends</label>
                    <input id="eventEndsAt" type="datetime-local" name="ends_at" value="<?php echo htmlspecialchars((string) ($_POST["ends_at"] ?? "")); ?>">
                </div>

                <div class="form-group">
                    <label for="eventParticipantLimit">Participant Limit (Optional)</label>
                    <input id="eventParticipantLimit" type="number" name="participant_limit" value="<?php echo htmlspecialchars((string) ($_POST["participant_limit"] ?? "")); ?>" placeholder="Leave empty for unlimited" min="1">
                </div>
            </div>

            <div class="event-modal-footer">
                <button type="button" class="btn btn-outline" data-close-create-modal>Cancel</button>
                <button type="submit" class="btn" id="eventFormSubmit">Create Event</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Event Details Modal -->
<div id="eventModal" class="modal-overlay">
    <div class="modal-card event-details-modal">
        <button class="modal-close" type="button" onclick="closeEventModal()">&times;</button>
        <div id="eventModalContent">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Participants Modal -->
<div id="participantsModal" class="modal-overlay">
    <div class="modal-card event-details-modal">
        <button class="modal-close" type="button" onclick="closeParticipantsModal()">&times;</button>
        <div class="event-modal-head">
            <h2>Event Participants</h2>
        </div>
        <div id="participantsModalContent" class="event-modal-body">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<script>
(function () {
    const allEvents = <?php echo json_encode($events); ?>;
    const joinedEvents = <?php echo json_encode($joinedEvents); ?>;
    const checkedInEvents = <?php echo json_encode($checkedInEvents); ?>;
    const canJoin = <?php echo json_encode($canJoin); ?>;
    const userId = <?php echo json_encode($userId); ?>;
    const canCreateEvents = <?php echo json_encode($canCreateEvents); ?>;
    const userRole = <?php echo json_encode($role); ?>;
    const createModal = document.getElementById('createModal');
    const openCreateButton = document.getElementById('openCreateModal');
    const eventFormTitle = document.getElementById('eventFormTitle');
    const eventFormAction = document.getElementById('eventFormAction');
    const eventFormId = document.getElementById('eventFormId');
    const eventFormSubmit = document.getElementById('eventFormSubmit');
    const eventTitleInput = document.getElementById('eventTitle');
    const eventCategoryInput = document.getElementById('eventCategory');
    const eventDescriptionInput = document.getElementById('eventDescription');
    const eventLocationInput = document.getElementById('eventLocation');
    const eventStartsAtInput = document.getElementById('eventStartsAt');
    const eventEndsAtInput = document.getElementById('eventEndsAt');

    function setEventFormMode(mode, data = {}) {
        if (!createModal || !eventFormAction) return;

        const isEdit = mode === 'edit';
        eventFormTitle.textContent = isEdit ? 'Edit Event' : 'Create Event';
        eventFormSubmit.textContent = isEdit ? 'Save Changes' : 'Create Event';
        eventFormAction.value = isEdit ? 'update' : 'create';
        eventFormId.value = data.id || '';
        eventTitleInput.value = data.title || '';
        eventCategoryInput.value = data.category || '';
        eventDescriptionInput.value = data.description || '';
        eventLocationInput.value = data.location || '';
        eventStartsAtInput.value = data.startsAt || '';
        eventEndsAtInput.value = data.endsAt || '';
    }

    if (createModal && openCreateButton) {
        const closeCreateButtons = createModal.querySelectorAll('[data-close-create-modal]');

        openCreateButton.addEventListener('click', function () {
            setEventFormMode('create');
            createModal.classList.add('open');
        });

        closeCreateButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                createModal.classList.remove('open');
            });
        });

        createModal.addEventListener('click', function (e) {
            if (e.target === createModal) {
                createModal.classList.remove('open');
            }
        });
    }

    document.querySelectorAll('[data-edit-event]').forEach(function (button) {
        button.addEventListener('click', function () {
            setEventFormMode('edit', {
                id: button.getAttribute('data-event-id') || '',
                title: button.getAttribute('data-title') || '',
                category: button.getAttribute('data-category') || '',
                description: button.getAttribute('data-description') || '',
                location: button.getAttribute('data-location') || '',
                startsAt: button.getAttribute('data-starts-at') || '',
                endsAt: button.getAttribute('data-ends-at') || ''
            });
            createModal.classList.add('open');
        });
    });

    window.openEventModal = function (eventId) {
        const event = allEvents.find(e => parseInt(e.id) === eventId);
        if (!event) return;
        
        // Debug: Log event card debug data
        const eventCard = document.querySelector(`.event-card[data-event-id="${eventId}"]`) || 
                         document.querySelector(`.event-card[onclick*="${eventId}"]`);
        if (eventCard && eventCard.dataset.debug) {
            try {
                const debugData = JSON.parse(eventCard.dataset.debug);
                console.log('Event Card Debug Data:', debugData);
            } catch (e) {
                console.error('Error parsing debug data:', e);
            }
        }

        const startDate = new Date(event.starts_at);
        const endDate = event.ends_at ? new Date(event.ends_at) : null;
        
        const dateStr = startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        const timeStr = startDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const endTimeStr = endDate ? endDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'TBA';

        const isJoined = joinedEvents.includes(parseInt(event.id));
        const isCheckedIn = checkedInEvents.includes(parseInt(event.id));

        // Calculate check-in window (align with server rules)
        const eventStart = new Date(event.starts_at).getTime();
        const currentTime = Date.now();
        const checkInStart = eventStart - (20 * 60 * 1000); // 20 minutes before
        const eventEnd = event.ends_at ? new Date(event.ends_at).getTime() : (eventStart + (2 * 60 * 60 * 1000));
        const isCheckInWindow = currentTime >= checkInStart && currentTime <= eventEnd;
        const hasEventStarted = currentTime >= eventStart;

        let actionButtons = '';
        if (canJoin) {
            if (isCheckedIn) {
                actionButtons = `
                    <div class="checkin-status checked-in">
                        <span class="checkin-icon">✓</span>
                        <span>You have checked in</span>
                    </div>
                `;
            } else if (isJoined && isCheckInWindow) {
                actionButtons = `
                    <form method="POST" class="modal-form">
                        <input type="hidden" name="action" value="checkin">
                        <input type="hidden" name="event_id" value="${event.id}">
                        <button type="submit" class="btn btn-primary checkin-btn">Check In</button>
                    </form>
                `;
            }

            if (isJoined && !hasEventStarted) {
                actionButtons += `
                    <form method="POST" class="modal-form">
                        <input type="hidden" name="action" value="unjoin">
                        <input type="hidden" name="event_id" value="${event.id}">
                        <button type="submit" class="btn btn-outline event-join-btn">Unjoin Event</button>
                    </form>
                `;
            } else if (isJoined && hasEventStarted) {
                actionButtons += `
                    <button type="button" class="btn joined-btn" disabled>Joined</button>
                `;
            } else {
                actionButtons += `
                    <form method="POST" class="modal-form">
                        <input type="hidden" name="action" value="join">
                        <input type="hidden" name="event_id" value="${event.id}">
                        <button type="submit" class="btn event-join-btn">Join Event</button>
                    </form>
                `;
            }
        }

        const content = `
            <div class="event-modal-head">
                <h2>${event.title}</h2>
            </div>
            <div class="event-modal-body">
                <div class="event-detail-group">
                    <label>Category</label>
                    <p>${event.category || 'Event'}</p>
                </div>
                <div class="event-detail-group">
                    <label>Description</label>
                    <p>${event.description || 'No additional details provided.'}</p>
                </div>
                <div class="event-detail-group">
                    <label>Date & Time</label>
                    <p>${dateStr} from ${timeStr} to ${endTimeStr}</p>
                </div>
                <div class="event-detail-group">
                    <label>Location</label>
                    <p>${event.location}</p>
                </div>
                <div class="event-detail-group">
                    <label>Hosted by</label>
                    <p>${event.created_by_name || 'Campus'}</p>
                </div>
                <div class="event-detail-group">
                    <label>College</label>
                    <p>${event.college || 'N/A'}</p>
                </div>
                <div class="event-detail-group">
                    <label>Participants</label>
                    ${canCreateEvents ? `
                        <div class="participants-info">
                            <p>${event.participant_count} joined, ${event.checkin_count || 0} checked in</p>
                            <button type="button" class="btn btn-sm btn-outline view-participants-btn" data-event-id="${event.id}">View Participants</button>
                        </div>
                    ` : `<p>${event.participant_count} people joined</p>`}
                </div>
            </div>
            <div class="event-modal-footer">
                ${actionButtons}
            </div>
        `;

        document.getElementById('eventModalContent').innerHTML = content;
        document.getElementById('eventModal').classList.add('open');

        // Add event listener for view participants button
        const viewParticipantsBtn = document.querySelector('.view-participants-btn');
        if (viewParticipantsBtn) {
            viewParticipantsBtn.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                loadParticipants(eventId);
            });
        }
    };

    window.loadParticipants = function(eventId) {
        fetch(`/campuscare-api/php-frontend/api/get_event_participants.php?event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const participants = data.participants || [];
                    let content = '';
                    
                    if (participants.length === 0) {
                        content = `<p class="no-participants">No participants yet.</p>`;
                    } else {
                        content = `<div class="participants-list">`;
                        participants.forEach(participant => {
                            content += `
                                <div class="participant-item ${participant.checked_in ? 'checked-in' : ''}">
                                    <div class="participant-info">
                                        <span class="participant-name">${participant.full_name}</span>
                                        <span class="participant-role">${participant.role}</span>
                                    </div>
                                    ${participant.checked_in ? '<span class="checkin-badge">Checked In</span>' : '<span class="not-checked-in">Not Checked In</span>'}
                                </div>
                            `;
                        });
                        content += `</div>`;
                    }
                    
                    document.getElementById('participantsModalContent').innerHTML = content;
                    document.getElementById('participantsModal').classList.add('open');
                } else {
                    alert('Failed to load participants: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error loading participants:', error);
                alert('Failed to load participants. Please try again.');
            });
    };

    window.closeParticipantsModal = function() {
        document.getElementById('participantsModal').classList.remove('open');
    };

    window.closeEventModal = function () {
        document.getElementById('eventModal').classList.remove('open');
    };

    document.getElementById('eventModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeEventModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeEventModal();
            if (createModal) {
                createModal.classList.remove('open');
            }
        }
    });

    // Update check-in buttons based on browser time
    function updateCheckInButtons() {
        const currentTime = Date.now();
        const eventCards = document.querySelectorAll('.event-card');

        eventCards.forEach((card, index) => {
            const eventStart = card.getAttribute('data-event-start');
            if (!eventStart) return;

            console.log(`Card ${index} - Event start string:`, eventStart);
            const eventStartTime = new Date(eventStart).getTime();
            console.log(`Card ${index} - Event start timestamp:`, eventStartTime, new Date(eventStartTime));
            const eventEndAttr = card.getAttribute('data-event-end');
            const eventEndTime = eventEndAttr ? new Date(eventEndAttr).getTime() : (eventStartTime + (2 * 60 * 60 * 1000));
            console.log(`Card ${index} - Event end timestamp:`, eventEndTime, new Date(eventEndTime));

            const checkInStart = eventStartTime - (20 * 60 * 1000); // 20 minutes before
            const checkInEnd = eventEndTime; // until event ends
            const isCheckInWindow = currentTime >= checkInStart && currentTime <= checkInEnd;
            const hasEventStarted = currentTime >= eventStartTime;
            console.log(`Card ${index} - Current time:`, currentTime, new Date(currentTime));
            console.log(`Card ${index} - Check-in window:`, new Date(checkInStart), 'to', new Date(checkInEnd));
            console.log(`Card ${index} - Is check-in window:`, isCheckInWindow);
            console.log(`Card ${index} - Has event started:`, hasEventStarted);

            const joined = card.getAttribute('data-joined') === '1';
            const checkedIn = card.getAttribute('data-checkedin') === '1';

            // Buttons/elements
            const checkInBtn = card.querySelector('.event-checkin-btn');
            const checkInStatus = card.querySelector('.checkin-status-card');
            const joinBtn = card.querySelector('.event-join-btn');
            const actionStack = card.querySelector('.event-card-action-stack');

            // Show check-in button only when user joined and within window and not already checked in
            if (checkInBtn) {
                if (joined && !checkedIn && isCheckInWindow) {
                    checkInBtn.style.display = 'inline-block';
                } else {
                    checkInBtn.style.display = 'none';
                }
            }

            // If user already checked in, show the checkin status card
            if (checkInStatus) {
                checkInStatus.style.display = checkedIn ? 'flex' : 'none';
            }

            // Do not append a fake "Joined" marker. Only reflect server-side state.
            if (joinBtn) {
                // Only hide the Join button, not the Unjoin button or Joined button
                // Unjoin button has the "event-unjoin-btn" class
                // Joined button has the "joined-btn" class
                if (!joinBtn.classList.contains('event-unjoin-btn') && !joinBtn.classList.contains('joined-btn')) {
                    joinBtn.style.display = joined ? 'none' : 'inline-flex';
                }
            }

            // Ensure joined-status element (server-rendered) visibility is consistent
            // The joined-btn is only rendered by the server when the event has started
            // The joined-status is only rendered by the server when the event has started and user has checked in
            const joinedBtnElement = card.querySelector('.joined-btn');
            const joinedStatusElement = card.querySelector('.joined-status');
            
            // Don't hide the joined-btn - it's already conditionally rendered by the server
            // Only manage the joined-status element
            if (joinedStatusElement) {
                joinedStatusElement.style.display = joined ? 'inline-block' : 'none';
            }
        });
    }
    
    // Run on page load
    updateCheckInButtons();
    
    // Update every 30 seconds
    setInterval(updateCheckInButtons, 30000);
})();
</script>

<script>
(function () {

})();
</script>
</body>
</html>

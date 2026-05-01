<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "College Events";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "User";

$canJoin = in_array($role, ["Student", "Instructor", "Teacher"], true);
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

// Get selected college from query params
$selectedCollege = isset($_GET["college"]) ? trim($_GET["college"]) : null;

if (!$selectedCollege) {
    header("Location: /campuscare-api/php-frontend/pages/events/events.php");
    exit;
}

function eventColumnExists(mysqli $conn, string $columnName): bool
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
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $databaseName, $columnName);
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

$eventsHasCollege = eventColumnExists($conn, "college");
$eventsHasStartsAt = eventColumnExists($conn, "starts_at");
$eventsHasEndsAt = eventColumnExists($conn, "ends_at");
$eventsHasCreatedBy = eventColumnExists($conn, "created_by_user_id");
$eventsHasEventDate = eventColumnExists($conn, "event_date");
$eventsHasEventTime = eventColumnExists($conn, "event_time");
$eventsHasCategory = eventColumnExists($conn, "category");

if (!$eventsHasStartsAt) {
    $conn->query("ALTER TABLE events ADD COLUMN starts_at DATETIME DEFAULT NULL AFTER description");
    $eventsHasStartsAt = eventColumnExists($conn, "starts_at");
}

if (!$eventsHasEndsAt) {
    $conn->query("ALTER TABLE events ADD COLUMN ends_at DATETIME DEFAULT NULL AFTER starts_at");
    $eventsHasEndsAt = eventColumnExists($conn, "ends_at");
}

if (!$eventsHasCreatedBy) {
    $conn->query("ALTER TABLE events ADD COLUMN created_by_user_id INT(11) DEFAULT NULL AFTER ends_at");
    $eventsHasCreatedBy = eventColumnExists($conn, "created_by_user_id");
}

if (!$eventsHasCollege) {
    $conn->query("ALTER TABLE events ADD COLUMN college VARCHAR(150) DEFAULT NULL AFTER created_by_user_id");
    $eventsHasCollege = eventColumnExists($conn, "college");
}

if (!$eventsHasCategory) {
    $conn->query("ALTER TABLE events ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER description");
    $eventsHasCategory = eventColumnExists($conn, "category");
}

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
                    INSERT INTO events (title, event_date, event_time, location, description, category, starts_at, ends_at, created_by_user_id, college)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO events (title, description, category, location, starts_at, ends_at, created_by_user_id, college)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
            }

            if ($stmt) {
                if ($eventsHasEventDate && $eventsHasEventTime) {
                    $stmt->bind_param("ssssssssis", $title, $eventDate, $eventTime, $location, $description, $category, $startsAt, $endsAt, $userId, $selectedCollege);
                } else {
                    $stmt->bind_param("ssssssis", $title, $description, $category, $location, $startsAt, $endsAt, $userId, $selectedCollege);
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
        $eventDate = $startsAt !== null ? substr($startsAt, 0, 10) : null;
        $eventTime = $startsAt !== null ? substr($startsAt, 11, 8) : null;

        if ($eventId <= 0) {
            $error = "Invalid event ID.";
        } elseif ($title === "" || $category === "" || $location === "" || $startsAt === null) {
            $error = "Please fill in the event title, category, start date and location.";
        } elseif ($endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = "End date and time must be later than the start date and time.";
        } elseif ($eventsHasEventDate && $eventsHasEventTime) {
            $stmt = $conn->prepare("
                UPDATE events
                SET title = ?, event_date = ?, event_time = ?, location = ?, description = ?, category = ?, starts_at = ?, ends_at = ?, college = ?
                WHERE id = ?
            ");

            if ($stmt) {
                $stmt->bind_param("sssssssssi", $title, $eventDate, $eventTime, $location, $description, $category, $startsAt, $endsAt, $selectedCollege, $eventId);

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
}

// Get events from selected college
$events = [];
$collegeSelect = $eventsHasCollege ? "COALESCE(NULLIF(e.college, ''), u.college)" : "u.college";
$eventCollegeWhere = $eventsHasCollege ? "COALESCE(NULLIF(e.college, ''), u.college) = ?" : "u.college = ?";
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
        (SELECT COUNT(*) FROM event_participants WHERE event_id = e.id) AS participant_count
    FROM events e
    LEFT JOIN users u ON e.created_by_user_id = u.id
    WHERE {$eventCollegeWhere}
    ORDER BY e.starts_at ASC
");

if ($stmt) {
    $stmt->bind_param("s", $selectedCollege);
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
                    <p class="page-subtitle">Discover all available events</p>
                </div>
                <div class="events-head-actions">
                    <?php if ($canCreateEvents): ?>
                        <button type="button" class="btn" id="openCreateModal">
                            <?php echo sidebarIconSvg("plus"); ?>
                            Create Event
                        </button>
                    <?php endif; ?>
                    <a href="/campuscare-api/php-frontend/pages/events/events.php" class="btn btn-outline">Back to Colleges</a>
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
                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <p>No events available from this college yet.</p>
                    </div>
                <?php else: ?>
                    <div class="events-list">
                        <?php foreach ($events as $event): ?>
                            <?php
                                $eventId = intval($event["id"]);
                                $isJoined = in_array($eventId, $joinedEvents, true);
                                $startDateTime = new DateTime($event["starts_at"]);
                                $eventMonthLabel = $startDateTime->format("M");
                                $eventDayLabel = $startDateTime->format("j");
                                $dateStr = $startDateTime->format("M d, Y");
                                $timeStr = $startDateTime->format("g:i A");
                                $endTimeStr = !empty($event["ends_at"]) ? (new DateTime($event["ends_at"]))->format("g:i A") : "";
                                $displayTime = $endTimeStr !== "" ? $timeStr . " - " . $endTimeStr : $timeStr;
                                $eventCategory = trim((string) ($event["category"] ?? "")) !== "" ? (string) $event["category"] : "Event";
                                $eventDescription = trim((string) ($event["description"] ?? ""));
                            ?>
                            <article class="event-card" onclick="openEventModal(<?php echo $eventId; ?>)">
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
                                        <span><?php echo sidebarIconSvg("clock"); ?> <?php echo htmlspecialchars($displayTime); ?></span>
                                        <span><?php echo sidebarIconSvg("pin"); ?> <?php echo htmlspecialchars($event["location"]); ?></span>
                                        <?php if ($eventDescription !== ""): ?>
                                            <span><?php echo sidebarIconSvg("message"); ?> <?php echo htmlspecialchars($eventDescription); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="event-card-footer" onclick="event.stopPropagation()">
                                    <div class="event-card-action-stack">
                                        <?php if ($canJoin): ?>
                                            <?php if ($isJoined): ?>
                                                <form method="POST" class="event-card-action-form">
                                                    <input type="hidden" name="action" value="unjoin">
                                                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                    <button type="submit" class="btn btn-outline event-join-btn">Unjoin</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="event-card-action-form">
                                                    <input type="hidden" name="action" value="join">
                                                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                                                    <button type="submit" class="btn event-join-btn">Join</button>
                                                </form>
                                            <?php endif; ?>
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
                                        <span class="event-card-attendees"><?php echo sidebarIconSvg("users"); ?> <?php echo intval($event["participant_count"]); ?> joined</span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
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

<script>
(function () {
    const allEvents = <?php echo json_encode($events); ?>;
    const joinedEvents = <?php echo json_encode($joinedEvents); ?>;
    const canJoin = <?php echo json_encode($canJoin); ?>;
    const userId = <?php echo json_encode($userId); ?>;
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

        const startDate = new Date(event.starts_at);
        const endDate = event.ends_at ? new Date(event.ends_at) : null;
        
        const dateStr = startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        const timeStr = startDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const endTimeStr = endDate ? endDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : 'TBA';

        const isJoined = joinedEvents.includes(parseInt(event.id));

        let joinButton = '';
        if (canJoin) {
            if (isJoined) {
                joinButton = `
                    <form method="POST" class="modal-form">
                        <input type="hidden" name="action" value="unjoin">
                        <input type="hidden" name="event_id" value="${event.id}">
                        <button type="submit" class="btn btn-outline">Unjoin Event</button>
                    </form>
                `;
            } else {
                joinButton = `
                    <form method="POST" class="modal-form">
                        <input type="hidden" name="action" value="join">
                        <input type="hidden" name="event_id" value="${event.id}">
                        <button type="submit" class="btn btn-primary">Join Event</button>
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
                    <p>${event.participant_count} people joined</p>
                </div>
            </div>
            <div class="event-modal-footer">
                ${joinButton}
            </div>
        `;

        document.getElementById('eventModalContent').innerHTML = content;
        document.getElementById('eventModal').classList.add('open');
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
})();
</script>

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

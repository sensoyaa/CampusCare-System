<?php
require_once "includes/auth.php";
requireLogin();
require_once "includes/db.php";

$pageTitle = "Brown Bag Sessions";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);
$fullName = $_SESSION["full_name"] ?? "User";
$isFacilitatorView = $role === "Facilitator";
$isAdminView = $role === "Administrator";
$isInstructorView = $role === "Instructor";

$canManage = in_array($role, ["Administrator", "Facilitator"]);
$canJoin = $role === "Student";

$error = "";
$success = "";
$openCreateModal = $canManage && (trim((string) ($_GET["open"] ?? "")) === "create");

function categoryBadgeClass($category) {
    if ($category === "Wellness") {
        return "badge-green";
    }

    if ($category === "Training") {
        return "badge-teal";
    }

    return "badge-primary";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create" && $canManage) {
        $title = trim($_POST["title"] ?? "");
        $event_date = trim($_POST["event_date"] ?? "");
        $event_time = trim($_POST["event_time"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $description = trim($_POST["category"] ?? "Brownbag");

        if ($title === "" || $event_date === "" || $event_time === "" || $location === "") {
            $error = "Please fill in all fields.";
            $openCreateModal = true;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO events (title, event_date, event_time, location, description)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $title, $event_date, $event_time, $location, $description);

            if ($stmt->execute()) {
                $success = "Event created successfully.";
                $openCreateModal = false;
            } else {
                $error = "Failed to create event.";
                $openCreateModal = true;
            }

            $stmt->close();
        }
    }

    if ($action === "delete" && $canManage) {
        $event_id = intval($_POST["event_id"] ?? 0);

        if ($event_id <= 0) {
            $error = "Invalid event ID.";
        } else {
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            $stmt->bind_param("i", $event_id);

            if ($stmt->execute()) {
                $success = "Event deleted successfully.";
            } else {
                $error = "Failed to delete event.";
            }

            $stmt->close();
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
            } else {
                $error = "Failed to join event.";
            }

            $stmt->close();
        }
    }
}

$events = [];

$result = $conn->query("
    SELECT 
        e.id,
        e.title,
        e.event_date,
        e.event_time,
        e.location,
        COALESCE(NULLIF(e.description, ''), 'Brownbag') AS category,
        COUNT(ep.id) AS participant_count
    FROM events e
    LEFT JOIN event_participants ep ON e.id = ep.event_id
    GROUP BY e.id, e.title, e.event_date, e.event_time, e.location, e.description
    ORDER BY e.event_date ASC, e.event_time ASC
");

while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

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

require_once "includes/header.php";
require_once "includes/sidebar.php";
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
        <div class="page-shell events-shell<?php echo $isAdminView ? " admin-shell" : ""; ?><?php echo $isFacilitatorView ? " facilitator-events-shell" : ""; ?><?php echo $isInstructorView ? " instructor-events-shell" : ""; ?>">
            <div class="manage-head<?php echo $isFacilitatorView ? " facilitator-events-head" : ""; ?><?php echo $isInstructorView ? " instructor-events-head" : ""; ?>">
                <div>
                    <h1 class="page-title<?php echo $isFacilitatorView ? " facilitator-events-title" : ""; ?><?php echo $isInstructorView ? " instructor-events-title" : ""; ?>">Brown Bag Sessions</h1>

                    <?php if (!$isFacilitatorView && !$isInstructorView): ?>
                        <p class="page-subtitle">
                            <?php echo $canManage ? "Create and manage campus events" : "View campus sessions and wellness events"; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($canManage): ?>
                    <button type="button" class="btn slot-add-btn<?php echo $isFacilitatorView ? " facilitator-create-btn" : ""; ?>" id="openCreateModal">
                        <?php echo sidebarIconSvg("plus"); ?>
                        Create Event
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($events)): ?>
                <div class="admin-users-empty">No events yet.</div>
            <?php else: ?>
                <section class="<?php echo $isFacilitatorView ? "facilitator-event-list" : ($isInstructorView ? "instructor-event-list" : "admin-event-list"); ?>">
                    <?php foreach ($events as $event): ?>
                        <?php
                            $category = trim((string) ($event["category"] ?? "Brownbag"));
                            $badgeClass = categoryBadgeClass($category);
                            $dateTime = date("F j, Y \\a\\t g:i A", strtotime((string) ($event["event_date"] ?? "") . " " . (string) ($event["event_time"] ?? "")));
                            $joined = in_array(intval($event["id"]), $joinedEvents, true);
                        ?>

                        <article class="<?php echo $isFacilitatorView ? "facilitator-event-card" : ($isInstructorView ? "instructor-event-card" : "admin-event-card"); ?>">
                            <div class="admin-event-left">
                                <span class="admin-event-icon"><?php echo sidebarIconSvg("calendar"); ?></span>

                                <div>
                                    <h2 class="admin-event-title"><?php echo htmlspecialchars((string) ($event["title"] ?? "Event")); ?></h2>
                                    <p class="admin-event-meta"><span><?php echo sidebarIconSvg("calendar"); ?></span><?php echo htmlspecialchars($dateTime); ?></p>
                                    <p class="admin-event-meta"><span><?php echo sidebarIconSvg("pin"); ?></span><?php echo htmlspecialchars((string) ($event["location"] ?? "TBA")); ?></p>
                                </div>
                            </div>

                            <div class="admin-event-actions">
                                <span class="<?php echo $isFacilitatorView ? "facilitator-category-pill" : ($isInstructorView ? "instructor-category-pill" : "admin-role-pill " . $badgeClass); ?>"><?php echo htmlspecialchars($category); ?></span>

                                <?php if ($canJoin): ?>
                                    <?php if ($joined): ?>
                                        <span class="admin-status-pill active">Joined</span>
                                    <?php else: ?>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="join">
                                            <input type="hidden" name="event_id" value="<?php echo intval($event["id"]); ?>">
                                            <button type="submit" class="btn btn-sm">Join</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($canManage): ?>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this event?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="event_id" value="<?php echo intval($event["id"]); ?>">
                                        <button type="submit" class="row-icon-btn danger" aria-label="Delete event">
                                            <?php echo sidebarIconSvg("trash"); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ($isFacilitatorView || $isInstructorView): ?>
                <a href="#" class="chat-fab chat-fab-icon" aria-label="Open chat"><?php echo sidebarIconSvg("message"); ?></a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($canManage): ?>
<div id="createModal" class="modal-overlay<?php echo $openCreateModal ? " open" : ""; ?>">
    <div class="modal-card<?php echo $isFacilitatorView ? " facilitator-event-modal" : ""; ?>">
        <div class="modal-head">
            <h3>Create New Event</h3>
            <button type="button" class="modal-close" data-close-create-modal aria-label="Close">&times;</button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars((string) ($_POST["title"] ?? "")); ?>" placeholder="Event title" required>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" name="event_date" value="<?php echo htmlspecialchars((string) ($_POST["event_date"] ?? "")); ?>" required>
            </div>

            <div class="form-group">
                <label>Time</label>
                <input type="time" name="event_time" value="<?php echo htmlspecialchars((string) ($_POST["event_time"] ?? "")); ?>" required>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars((string) ($_POST["location"] ?? "")); ?>" placeholder="e.g. Room 201" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="Brownbag" <?php echo (($_POST["category"] ?? "Brownbag") === "Brownbag") ? "selected" : ""; ?>>Brownbag</option>
                    <option value="Wellness" <?php echo (($_POST["category"] ?? "") === "Wellness") ? "selected" : ""; ?>>Wellness</option>
                    <option value="Training" <?php echo (($_POST["category"] ?? "") === "Training") ? "selected" : ""; ?>>Training</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-create-modal>Cancel</button>
                <button type="submit" class="btn">Create Event</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const modal = document.getElementById("createModal");
    const openButton = document.getElementById("openCreateModal");

    if (!modal || !openButton) {
        return;
    }

    const closeButtons = modal.querySelectorAll("[data-close-create-modal]");

    function openModal() {
        modal.classList.add("open");
    }

    function closeModal() {
        modal.classList.remove("open");
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
</body>
</html>
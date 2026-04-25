<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Event Participants";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "User";

if (!in_array($role, ["Facilitator", "Administrator"], true)) {
    header("Location: dashboard.php");
    exit();
}

$search = trim((string) ($_GET["search"] ?? ""));
$events = [];

$sql = "
    SELECT
        e.id AS event_id,
        e.title,
        e.event_date,
        e.event_time,
        u.id AS user_id,
        u.full_name,
        u.student_id
    FROM events e
    LEFT JOIN event_participants ep ON e.id = ep.event_id
    LEFT JOIN users u ON ep.user_id = u.id
    ORDER BY e.event_date ASC, e.event_time ASC, e.title ASC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $eventId = intval($row["event_id"] ?? 0);

    if (!isset($events[$eventId])) {
        $dateValue = (string) ($row["event_date"] ?? "") . " " . (string) ($row["event_time"] ?? "00:00:00");

        $events[$eventId] = [
            "id" => $eventId,
            "title" => (string) ($row["title"] ?? "Event"),
            "date" => date("M j, Y", strtotime($dateValue)),
            "participants" => [],
        ];
    }

    if (!empty($row["user_id"])) {
        $events[$eventId]["participants"][] = [
            "name" => (string) ($row["full_name"] ?? "Student"),
            "id" => (string) (($row["student_id"] ?? "") !== "" ? $row["student_id"] : "N/A"),
        ];
    }
}

$events = array_values($events);

if ($search !== "") {
    $needle = strtolower($search);
    $filteredEvents = [];

    foreach ($events as $event) {
        $title = strtolower((string) ($event["title"] ?? ""));
        $titleMatch = strpos($title, $needle) !== false;

        $participants = is_array($event["participants"] ?? null) ? $event["participants"] : [];
        $filteredParticipants = array_values(array_filter($participants, function ($participant) use ($needle) {
            $name = strtolower((string) ($participant["name"] ?? ""));
            $id = strtolower((string) ($participant["id"] ?? ""));

            return strpos($name, $needle) !== false || strpos($id, $needle) !== false;
        }));

        if ($titleMatch || !empty($filteredParticipants)) {
            if (!$titleMatch) {
                $event["participants"] = $filteredParticipants;
            }

            $filteredEvents[] = $event;
        }
    }

    $events = $filteredEvents;
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
            <button class="profile-menu-toggle" aria-label="Profile menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M7 10l5 5 5-5z"></path>
                </svg>
            </button>
            <div class="profile-dropdown">
                <a href="../users/edit_profile.php" class="profile-dropdown-item">Edit Profile</a>
                <a href="../users/settings.php" class="profile-dropdown-item">Settings</a>

                <a href="../auth/logout.php" class="profile-dropdown-item logout-item">Logout</a>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="page-shell facilitator-shell">
            <div class="facilitator-participants-shell">
                <h1 class="page-title">Event Participants</h1>
                <p class="page-subtitle" style="margin-bottom: 24px;">View real student attendees for your sessions</p>

                <form method="GET" class="admin-search-wrap facilitator-search-wrap">
                    <span class="admin-search-icon"><?php echo sidebarIconSvg("search"); ?></span>
                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search participants or events..."
                    >
                </form>

                <?php if (empty($events)): ?>
                    <div class="admin-users-empty">No events or participants found.</div>
                <?php else: ?>
                    <section class="facilitator-participants-list">
                        <?php foreach ($events as $event): ?>
                            <?php
                                $eventTitle = (string) ($event["title"] ?? "Event");
                                $eventDate = (string) ($event["date"] ?? "");
                                $participants = is_array($event["participants"] ?? null) ? $event["participants"] : [];
                            ?>

                            <article class="facilitator-participant-event-card">
                                <div class="facilitator-participant-event-head">
                                    <div class="facilitator-participant-event-title-wrap">
                                        <span class="facilitator-participant-event-icon"><?php echo sidebarIconSvg("users"); ?></span>
                                        <div>
                                            <h2 class="facilitator-participant-event-title"><?php echo htmlspecialchars($eventTitle); ?></h2>
                                            <p class="facilitator-participant-event-meta">
                                                <?php echo htmlspecialchars($eventDate); ?> • <?php echo count($participants); ?> attendees
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($participants)): ?>
                                    <div class="facilitator-empty-row">No participants yet.</div>
                                <?php else: ?>
                                    <div class="facilitator-participant-list">
                                        <?php foreach ($participants as $participant): ?>
                                            <?php
                                                $participantName = (string) ($participant["name"] ?? "Student");
                                                $participantId = (string) ($participant["id"] ?? "N/A");
                                            ?>

                                            <div class="facilitator-participant-row">
                                                <span class="facilitator-participant-avatar"><?php echo strtoupper(substr($participantName, 0, 1)); ?></span>
                                                <span class="facilitator-participant-name"><?php echo htmlspecialchars($participantName); ?></span>
                                                <span class="facilitator-participant-id"><?php echo htmlspecialchars($participantId); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </div>

            <a href="#" class="chat-fab chat-fab-icon" aria-label="Open chat"><?php echo sidebarIconSvg("message"); ?></a>
        </div>
    </div>
</main>

</div>
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

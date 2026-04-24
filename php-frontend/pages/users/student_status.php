<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Student Participation";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "User";

if (!in_array($role, ["Instructor", "Administrator"], true)) {
    header("Location: dashboard.php");
    exit();
}

$search = trim((string) ($_GET["search"] ?? ""));
$students = [];

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.student_id,
        COUNT(a.id) AS sessions,
        MAX(a.appointment_date) AS last_visit
    FROM users u
    LEFT JOIN appointments a ON u.id = a.user_id
    WHERE u.role = 'Student'
    GROUP BY u.id, u.full_name, u.student_id
    ORDER BY u.full_name ASC
";

$result = $conn->query($sql);

while ($result && ($row = $result->fetch_assoc())) {
    $name = trim((string) ($row["full_name"] ?? "Student"));
    $studentId = trim((string) ($row["student_id"] ?? ""));

    if ($studentId === "") {
        $studentId = (string) intval($row["id"] ?? 0);
    }

    if ($search !== "") {
        $needle = strtolower($search);
        if (strpos(strtolower($name), $needle) === false && strpos(strtolower($studentId), $needle) === false) {
            continue;
        }
    }

    $sessions = intval($row["sessions"] ?? 0);
    $lastVisitRaw = trim((string) ($row["last_visit"] ?? ""));
    $lastVisit = $lastVisitRaw !== "" ? date("M j", strtotime($lastVisitRaw)) : "-";

    $status = "No sessions";
    if ($sessions >= 5) {
        $status = "Follow-up";
    } elseif ($sessions > 0) {
        $status = "Active";
    }

    $students[] = [
        "name" => $name,
        "id" => $studentId,
        "sessions" => $sessions,
        "last_visit" => $lastVisit,
        "status" => $status,
    ];
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
        <div class="page-shell instructor-status-shell">
            <h1 class="page-title">Student Participation</h1>
            <p class="page-subtitle" style="margin-bottom: 18px;">
                Check student counseling participation and event attendance
            </p>

            <form method="GET" class="admin-search-wrap instructor-status-search">
                <span class="admin-search-icon"><?php echo sidebarIconSvg("search"); ?></span>
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search student..."
                >
            </form>

            <section class="instructor-status-table">
                <div class="instructor-status-head">
                    <span>STUDENT</span>
                    <span>ID</span>
                    <span>SESSIONS</span>
                    <span>LAST VISIT</span>
                    <span>STATUS</span>
                </div>

                <?php if (empty($students)): ?>
                    <div class="instructor-status-empty">No users found.</div>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <?php
                            $status = (string) ($student["status"] ?? "No sessions");
                            $statusClass = "none";
                            $statusIcon = "x-circle";

                            if ($status === "Active") {
                                $statusClass = "active";
                                $statusIcon = "check-circle";
                            } elseif ($status === "Follow-up") {
                                $statusClass = "follow-up";
                                $statusIcon = "clock";
                            }
                        ?>

                        <article class="instructor-status-row">
                            <span class="instructor-status-name"><?php echo htmlspecialchars((string) ($student["name"] ?? "Student")); ?></span>
                            <span class="instructor-status-id"><?php echo htmlspecialchars((string) ($student["id"] ?? "")); ?></span>
                            <span class="instructor-status-sessions"><?php echo intval($student["sessions"] ?? 0); ?></span>
                            <span class="instructor-status-visit"><?php echo htmlspecialchars((string) ($student["last_visit"] ?? "-")); ?></span>
                            <span class="instructor-status-pill <?php echo $statusClass; ?>">
                                <span class="instructor-status-pill-icon"><?php echo sidebarIconSvg($statusIcon); ?></span>
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <a href="#" class="chat-fab chat-fab-icon" aria-label="Open chat"><?php echo sidebarIconSvg("message"); ?></a>
    </div>
</main>

</div>
</body>
</html>


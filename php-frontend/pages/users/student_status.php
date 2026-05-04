<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Student Participation";
$role = normalizeRole($_SESSION["role"] ?? "Student");
$fullName = $_SESSION["full_name"] ?? "User";

if (!in_array($role, ["Instructor", "Administrator"], true)) {
    header("Location: /campuscare-api/php-frontend/pages/dashboard/dashboard.php");
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

        <?php require_once __DIR__ . "/../../includes/topbar_user_dropdown.php"; ?>
    </div>

    <div class="content">
        <div class="page-shell instructor-status-shell">
            <div class="status-booking-head">
                <h1 class="page-title">Student Participation</h1>
                <p class="page-subtitle">Check student counseling participation and event attendance</p>
            </div>

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

<style>
    .status-booking-head {
        padding: 30px 34px 20px;
        background: var(--primary);
        color: #fff;
        border-radius: 22px;
        margin-bottom: 1rem;
        box-shadow: 0 16px 32px rgba(61, 108, 150, 0.18);
    }

    .status-booking-head h1 {
        margin: 0 0 8px;
        font-size: 34px;
        color: #fff;
    }

    .status-booking-head p {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, 0.9);
    }
</style>

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



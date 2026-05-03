<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();
require_once __DIR__ . "/../../includes/db.php";

$pageTitle = "Campus Events";

$role = normalizeRole($_SESSION["role"] ?? "Student");
$userId = intval($_SESSION["user_id"] ?? 0);

$colleges = []; // College column missing from events table

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
                    <h1 class="page-title">Campus Events</h1>
                    <p class="page-subtitle" style="color:black;">Discover workshops, brown bag sessions, forums and more</p>
                </div>
            </div>

            <!-- College Selection Section -->
            <section class="events-section">
                <h2 class="events-section-title">Select a College</h2>
    <div class="empty-state">
    <h3>Events Page</h3>
    <p>No college filtering (missing DB column). <a href="/campuscare-api/php-frontend/pages/events/view_college_events.php?college=All">View All Events</a></p>
</div>
            </section>
        </div>
    </div>
</main>

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
</html>
</html>


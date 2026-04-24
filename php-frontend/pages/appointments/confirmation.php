<?php
require_once __DIR__ . "/../../includes/auth.php";
requireLogin();

$pageTitle = "Booking Confirmation";

$service = trim((string) ($_GET["service"] ?? ""));
$counselor = trim((string) ($_GET["counselor"] ?? ""));
$dateLabel = trim((string) ($_GET["date"] ?? ""));
$timeLabel = trim((string) ($_GET["time"] ?? ""));
$fullName = $_SESSION["full_name"] ?? "User";

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
        <div class="page-shell" style="max-width: 760px;">
            <section class="card" style="max-width: 620px; margin: 0 auto; text-align: center;">
                <div
                    style="width: 96px; height: 96px; border-radius: 999px; background: #4d8fc5; color: #fff; margin: 0 auto 20px; display: inline-flex; align-items: center; justify-content: center;"
                    aria-hidden="true"
                >
                    <span style="width: 46px; height: 46px; display: inline-flex; align-items: center; justify-content: center;">
                        <?php echo sidebarIconSvg("check-circle"); ?>
                    </span>
                </div>

                <h1 class="page-title" style="font-size: 34px; margin-bottom: 8px;">Booking Confirmed!</h1>
                <p class="page-subtitle" style="margin-bottom: 18px;">Your appointment has been successfully scheduled.</p>

                <?php if ($service !== "" || $counselor !== "" || $dateLabel !== "" || $timeLabel !== ""): ?>
                    <div class="feedback-note" style="text-align: left; margin-bottom: 20px;">
                        <?php if ($service !== ""): ?>
                            <p style="margin-bottom: 6px;"><strong>Service:</strong> <?php echo htmlspecialchars($service); ?></p>
                        <?php endif; ?>

                        <?php if ($counselor !== ""): ?>
                            <p style="margin-bottom: 6px;"><strong>Counselor:</strong> <?php echo htmlspecialchars($counselor); ?></p>
                        <?php endif; ?>

                        <?php if ($dateLabel !== ""): ?>
                            <p style="margin-bottom: 6px;"><strong>Date:</strong> <?php echo htmlspecialchars($dateLabel); ?></p>
                        <?php endif; ?>

                        <?php if ($timeLabel !== ""): ?>
                            <p><strong>Time:</strong> <?php echo htmlspecialchars($timeLabel); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="schedule.php" class="btn btn-outline">View Schedule</a>
                    <a href="dashboard.php" class="btn">Back to Dashboard</a>
                </div>
            </section>
        </div>

        <a href="#" class="chat-fab" aria-label="Open chat">?</a>
    </div>
</main>

</div>
</body>
</html>


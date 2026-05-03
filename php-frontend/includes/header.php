<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? "CampusCare";
$userName = $_SESSION["full_name"] ?? "User";
$darkModeCookie = strtolower(trim((string) ($_COOKIE["campuscare_dark_mode"] ?? "")));
$darkModeEnabled = in_array($darkModeCookie, ["true", "1", "yes", "on"], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | CampusCare</title>
    <link rel="icon" type="image/png" href="/campuscare-api/php-frontend/assets/images/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/campuscare-api/php-frontend/assets/style.css">
<<<<<<< HEAD
    <link rel="stylesheet" href="/campuscare-api/php-frontend/assets/compact.css">
    <link rel="stylesheet" href="/campuscare-api/php-frontend/assets/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
=======
    <?php if (basename($_SERVER["PHP_SELF"]) === "event_detail.php"): ?>
    <link rel="stylesheet" href="/campuscare-api/php-frontend/css/event_detail.css">
    <?php endif; ?>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Enhanced Alert System -->
    <script src="/campuscare-api/php-frontend/assets/alerts.js" defer></script>
>>>>>>> c79be1e8f52de80c4f5e3be267a3e85c90d9e051
    <script>
    (function () {
        try {
            var localTheme = localStorage.getItem("campuscare_dark_mode");
            var isDark = null;

            if (localTheme === "true" || localTheme === "1") {
                isDark = true;
            } else if (localTheme === "false" || localTheme === "0") {
                isDark = false;
            }

            if (isDark === null) {
                var cookieMatch = document.cookie.match(/(?:^|; )campuscare_dark_mode=([^;]*)/);
                var cookieValue = cookieMatch ? decodeURIComponent(cookieMatch[1]).toLowerCase() : "";
                isDark = (cookieValue === "true" || cookieValue === "1" || cookieValue === "yes" || cookieValue === "on");
            }

            window.__campuscareDarkMode = !!isDark;
        } catch (error) {
            window.__campuscareDarkMode = false;
        }
    })();
    </script>
</head>
<body class="<?php echo $darkModeEnabled ? "theme-dark" : ""; ?>">
<script>
(function () {
    if (window.__campuscareDarkMode) {
        document.body.classList.add("theme-dark");
    } else {
        document.body.classList.remove("theme-dark");
    }
})();
</script>
<div class="system-confirm-overlay" id="systemConfirmOverlay" aria-hidden="true">
    <div class="system-confirm-card" role="dialog" aria-modal="true" aria-labelledby="systemConfirmTitle">
        <div class="system-confirm-icon" id="systemConfirmIcon">?</div>
        <div class="system-confirm-copy">
            <h3 id="systemConfirmTitle">Please confirm</h3>
            <p id="systemConfirmMessage">Are you sure you want to continue?</p>
        </div>
        <div class="system-confirm-actions">
            <button type="button" class="btn btn-outline" id="systemConfirmCancel">Cancel</button>
            <button type="button" class="btn" id="systemConfirmProceed">Continue</button>
        </div>
    </div>
</div>
<script>
(function () {
    const confirmOverlay = document.getElementById("systemConfirmOverlay");
    const confirmTitle = document.getElementById("systemConfirmTitle");
    const confirmMessage = document.getElementById("systemConfirmMessage");
    const confirmIcon = document.getElementById("systemConfirmIcon");
    const confirmCancel = document.getElementById("systemConfirmCancel");
    const confirmProceed = document.getElementById("systemConfirmProceed");
    let pendingAction = null;

    if (!confirmOverlay || !confirmTitle || !confirmMessage || !confirmIcon || !confirmCancel || !confirmProceed) {
        return;
    }

    function closeConfirm() {
        confirmOverlay.classList.remove("open");
        confirmOverlay.setAttribute("aria-hidden", "true");
        pendingAction = null;
    }

    function openConfirm(config) {
        confirmTitle.textContent = config.title;
        confirmMessage.textContent = config.message;
        confirmProceed.textContent = config.buttonLabel;
        confirmIcon.textContent = config.variant === "danger" ? "!" : "?";
        confirmProceed.classList.toggle("btn-danger-solid", config.variant === "danger");
        confirmOverlay.classList.add("open");
        confirmOverlay.setAttribute("aria-hidden", "false");
    }

    document.addEventListener("submit", function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute("data-confirm-message")) {
            return;
        }

        if (form.dataset.confirmed === "true") {
            form.dataset.confirmed = "false";
            return;
        }

        event.preventDefault();
        pendingAction = function () {
            form.dataset.confirmed = "true";
            form.submit();
        };
        openConfirm({
            title: form.getAttribute("data-confirm-title") || "Please confirm",
            message: form.getAttribute("data-confirm-message") || "Are you sure you want to continue?",
            buttonLabel: form.getAttribute("data-confirm-button") || "Continue",
            variant: form.getAttribute("data-confirm-variant") || "default"
        });
    });

    document.addEventListener("click", function (event) {
        const link = event.target.closest("a[data-confirm-message]");

        if (!link) {
            return;
        }

        event.preventDefault();
        pendingAction = function () {
            window.location.href = link.getAttribute("href");
        };
        openConfirm({
            title: link.getAttribute("data-confirm-title") || "Please confirm",
            message: link.getAttribute("data-confirm-message") || "Are you sure you want to continue?",
            buttonLabel: link.getAttribute("data-confirm-button") || "Continue",
            variant: link.getAttribute("data-confirm-variant") || "default"
        });
    });

    confirmCancel.addEventListener("click", closeConfirm);
    confirmOverlay.addEventListener("click", function (event) {
        if (event.target === confirmOverlay) {
            closeConfirm();
        }
    });
    confirmProceed.addEventListener("click", function () {
        const action = pendingAction;
        closeConfirm();
        if (action) {
            action();
        }
    });

    document.addEventListener("click", function (e) {
        const toggleBtn = e.target.closest(".menu-toggle");
        if (toggleBtn) {
            const appDiv = document.querySelector(".app");
            if (appDiv) {
                const isCollapsed = appDiv.classList.toggle("sidebar-collapsed");
                localStorage.setItem("campuscare_sidebar", isCollapsed ? "collapsed" : "open");
            }
        }
    });
})();
</script>
<div class="app" id="mainAppDiv">
<script>
    if (localStorage.getItem("campuscare_sidebar") === "collapsed") {
        document.getElementById("mainAppDiv").classList.add("sidebar-collapsed");
    }
</script>

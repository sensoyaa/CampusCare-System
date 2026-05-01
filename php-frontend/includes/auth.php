<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION["user_id"]);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /campuscare-api/landingpage.php");
        exit();
    }

    $allowedTimeouts = [15, 30, 60, 120];
    $cookieTimeout = intval($_COOKIE["campuscare_idle_timeout"] ?? 60);
    $timeoutMinutes = in_array($cookieTimeout, $allowedTimeouts, true) ? $cookieTimeout : 60;
    $timeoutSeconds = $timeoutMinutes * 60;

    $lastActivityAt = intval($_SESSION["last_activity_at"] ?? 0);

    if ($lastActivityAt > 0 && (time() - $lastActivityAt) > $timeoutSeconds) {
        session_unset();
        session_destroy();
        header("Location: /campuscare-api/php-frontend/pages/auth/index.php?expired=1");
        exit();
    }

    $_SESSION["last_activity_at"] = time();
}

function normalizeRole($role) {
    if ($role === "Counsellor" || $role === "Counselor" || $role === "Counselors") {
        return "Counselor";
    }

    return $role;
}
?>
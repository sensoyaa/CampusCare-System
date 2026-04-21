<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION["user_id"]);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit();
    }
}

function normalizeRole($role) {
    if ($role === "Counsellor" || $role === "Counselor" || $role === "Counselors") {
        return "Counselor";
    }

    return $role;
}
?>
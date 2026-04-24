<?php
session_start();
require_once __DIR__ . "/../../includes/google_oauth.php";

function redirect_with_google_error(string $message): void
{
    $_SESSION["oauth_error"] = $message;
    header("Location: index.php");
    exit();
}

$config = campuscare_google_oauth_config();

if (!($config["is_configured"] ?? false)) {
    redirect_with_google_error("Google sign-in is not configured. Please check GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI.");
}

try {
    $state = bin2hex(random_bytes(16));
} catch (Exception $exception) {
    $state = md5(uniqid((string) mt_rand(), true));
}

$_SESSION["google_oauth_state"] = $state;
$_SESSION["google_oauth_state_expires_at"] = time() + 600;

$query = http_build_query(
    [
        "client_id" => $config["client_id"],
        "redirect_uri" => $config["redirect_uri"],
        "response_type" => "code",
        "scope" => "openid email profile",
        "state" => $state,
        "access_type" => "online",
        "prompt" => "select_account",
    ],
    "",
    "&",
    PHP_QUERY_RFC3986
);

header("Location: https://accounts.google.com/o/oauth2/v2/auth?" . $query);
exit();


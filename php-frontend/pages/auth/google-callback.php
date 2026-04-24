<?php
session_start();
require_once __DIR__ . "/../../includes/db.php";
require_once __DIR__ . "/../../includes/google_oauth.php";

function google_redirect_with_error(string $message): void
{
    $_SESSION["oauth_error"] = $message;
    header("Location: index.php");
    exit();
}

function google_random_token(int $bytes = 16): string
{
    try {
        return bin2hex(random_bytes($bytes));
    } catch (Exception $exception) {
        return md5(uniqid((string) mt_rand(), true));
    }
}

function google_string_ends_with(string $value, string $suffix): bool
{
    $suffixLength = strlen($suffix);

    if ($suffixLength === 0) {
        return true;
    }

    if (strlen($value) < $suffixLength) {
        return false;
    }

    return substr($value, -$suffixLength) === $suffix;
}

function google_email_matches_domain(string $email, string $requiredDomain): bool
{
    $atPosition = strrpos($email, "@");

    if ($atPosition === false) {
        return false;
    }

    $emailDomain = strtolower(trim(substr($email, $atPosition + 1)));
    $requiredDomain = strtolower(ltrim(trim($requiredDomain), "."));

    if ($emailDomain === "" || $requiredDomain === "") {
        return false;
    }

    if ($emailDomain === $requiredDomain) {
        return true;
    }

    return google_string_ends_with($emailDomain, "." . $requiredDomain);
}

function google_is_student_role(string $role): bool
{
    return strcasecmp(trim($role), "Student") === 0;
}

function google_http_post_form(string $url, array $fields): array
{
    $postFields = http_build_query($fields);
    $body = false;
    $statusCode = 0;

    if (function_exists("curl_init")) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded"],
        ]);

        $body = curl_exec($ch);

        if ($body !== false) {
            $statusCode = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        }

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ["success" => false, "message" => "Unable to contact Google token endpoint: " . $error];
        }

        curl_close($ch);
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => $postFields,
                "timeout" => 15,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return ["success" => false, "message" => "Unable to contact Google token endpoint."];
        }

        if (isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $matches) === 1) {
                $statusCode = intval($matches[1]);
            }
        }
    }

    $decoded = json_decode((string) $body, true);

    if (!is_array($decoded)) {
        return ["success" => false, "message" => "Google token response was not valid JSON."];
    }

    if ($statusCode >= 400 || isset($decoded["error"])) {
        $message = trim((string) ($decoded["error_description"] ?? $decoded["error"] ?? "Google token exchange failed."));
        return ["success" => false, "message" => $message, "json" => $decoded];
    }

    return ["success" => true, "json" => $decoded];
}

function google_http_get_json(string $url, array $headers = []): array
{
    $body = false;
    $statusCode = 0;

    if (function_exists("curl_init")) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $body = curl_exec($ch);

        if ($body !== false) {
            $statusCode = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        }

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ["success" => false, "message" => "Unable to contact Google userinfo endpoint: " . $error];
        }

        curl_close($ch);
    } else {
        $headerText = "";

        if (!empty($headers)) {
            $headerText = implode("\r\n", $headers) . "\r\n";
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => $headerText,
                "timeout" => 15,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return ["success" => false, "message" => "Unable to contact Google userinfo endpoint."];
        }

        if (isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $matches) === 1) {
                $statusCode = intval($matches[1]);
            }
        }
    }

    $decoded = json_decode((string) $body, true);

    if (!is_array($decoded)) {
        return ["success" => false, "message" => "Google user profile response was not valid JSON."];
    }

    if ($statusCode >= 400 || isset($decoded["error"])) {
        $message = trim((string) ($decoded["error_description"] ?? $decoded["error"] ?? "Google profile lookup failed."));
        return ["success" => false, "message" => $message, "json" => $decoded];
    }

    return ["success" => true, "json" => $decoded];
}

function google_generate_student_id(mysqli $conn): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = "GOOG-" . strtoupper(substr(google_random_token(6), 0, 10));
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE student_id = ? LIMIT 1");

        if (!$checkStmt) {
            return $candidate;
        }

        $checkStmt->bind_param("s", $candidate);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if (!$exists) {
            return $candidate;
        }
    }

    return "GOOG-" . date("YmdHis");
}

$config = campuscare_google_oauth_config();

if (!($config["is_configured"] ?? false)) {
    google_redirect_with_error("Google sign-in is not configured on the server.");
}

if (isset($_GET["error"])) {
    $oauthError = trim((string) $_GET["error"]);
    $oauthError = str_replace("_", " ", $oauthError);
    google_redirect_with_error("Google sign-in was cancelled or denied: " . $oauthError);
}

$state = trim((string) ($_GET["state"] ?? ""));
$savedState = trim((string) ($_SESSION["google_oauth_state"] ?? ""));
$stateExpiresAt = intval($_SESSION["google_oauth_state_expires_at"] ?? 0);

unset($_SESSION["google_oauth_state"], $_SESSION["google_oauth_state_expires_at"]);

if (
    $state === "" ||
    $savedState === "" ||
    !hash_equals($savedState, $state) ||
    $stateExpiresAt < time()
) {
    google_redirect_with_error("Google sign-in state validation failed. Please try again.");
}

$code = trim((string) ($_GET["code"] ?? ""));

if ($code === "") {
    google_redirect_with_error("Google sign-in did not return an authorization code.");
}

$tokenResponse = google_http_post_form(
    "https://oauth2.googleapis.com/token",
    [
        "code" => $code,
        "client_id" => $config["client_id"],
        "client_secret" => $config["client_secret"],
        "redirect_uri" => $config["redirect_uri"],
        "grant_type" => "authorization_code",
    ]
);

if (!($tokenResponse["success"] ?? false)) {
    google_redirect_with_error("Google token exchange failed: " . ($tokenResponse["message"] ?? "Unknown error."));
}

$tokenData = $tokenResponse["json"] ?? [];
$accessToken = trim((string) ($tokenData["access_token"] ?? ""));

if ($accessToken === "") {
    google_redirect_with_error("Google sign-in did not return an access token.");
}

$profileResponse = google_http_get_json(
    "https://openidconnect.googleapis.com/v1/userinfo",
    ["Authorization: Bearer " . $accessToken]
);

if (!($profileResponse["success"] ?? false)) {
    google_redirect_with_error("Unable to fetch Google profile: " . ($profileResponse["message"] ?? "Unknown error."));
}

$profile = $profileResponse["json"] ?? [];
$email = strtolower(trim((string) ($profile["email"] ?? "")));
$emailVerified = (bool) ($profile["email_verified"] ?? false);
$fullName = trim((string) ($profile["name"] ?? ""));

if ($email === "") {
    google_redirect_with_error("Google profile did not include an email address.");
}

if (!$emailVerified) {
    google_redirect_with_error("Your Google email address is not verified.");
}

$isStudentDomain = google_email_matches_domain($email, ".student.buksu.edu.ph");
$isBuksuDomain = google_email_matches_domain($email, ".buksu.edu.ph");
$isNonStudentDomain = $isBuksuDomain && !$isStudentDomain;

if (!$isStudentDomain && !$isNonStudentDomain) {
    google_redirect_with_error("Google sign-in is restricted to .student.buksu.edu.ph and .buksu.edu.ph email domains.");
}

if ($fullName === "") {
    $fullName = trim((string) ($profile["given_name"] ?? ""));

    if ($fullName === "") {
        $fullName = "CampusCare User";
    }
}

$lookupStmt = $conn->prepare(
    "SELECT id, full_name, student_id, email, role, status
     FROM users
     WHERE email = ?
     LIMIT 1"
);

if (!$lookupStmt) {
    google_redirect_with_error("Unable to prepare account lookup for Google sign-in.");
}

$lookupStmt->bind_param("s", $email);
$lookupStmt->execute();
$result = $lookupStmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $lookupStmt->close();

    $status = trim((string) ($user["status"] ?? ""));
    $userRole = trim((string) ($user["role"] ?? "Student"));

    if (strcasecmp($status, "Active") !== 0) {
        google_redirect_with_error("Your account is not active.");
    }

    if (google_is_student_role($userRole) && !$isStudentDomain) {
        google_redirect_with_error("Student accounts must sign in using an email ending in .student.buksu.edu.ph.");
    }

    if (!google_is_student_role($userRole) && !$isNonStudentDomain) {
        google_redirect_with_error("Non-student roles must sign in using an email ending in .buksu.edu.ph.");
    }
} else {
    $lookupStmt->close();

    if (!$isStudentDomain) {
        google_redirect_with_error("Only student emails ending in .student.buksu.edu.ph can auto-register with Google sign-in. Non-student accounts must be pre-created and use .buksu.edu.ph.");
    }

    $studentId = google_generate_student_id($conn);
    $placeholderPassword = password_hash(google_random_token(24), PASSWORD_DEFAULT);
    $role = "Student";
    $status = "Active";

    $insertStmt = $conn->prepare(
        "INSERT INTO users (full_name, student_id, email, password, role, status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$insertStmt) {
        google_redirect_with_error("Unable to create a new CampusCare account from Google sign-in.");
    }

    $insertStmt->bind_param("ssssss", $fullName, $studentId, $email, $placeholderPassword, $role, $status);

    if (!$insertStmt->execute()) {
        $insertError = intval($insertStmt->errno);
        $insertStmt->close();
        google_redirect_with_error(
            $insertError === 1062
                ? "Email must be unique. Duplicate email is not allowed."
                : "Failed to create your CampusCare account from Google sign-in."
        );
    }

    $newUserId = intval($insertStmt->insert_id);
    $insertStmt->close();

    $user = [
        "id" => $newUserId,
        "full_name" => $fullName,
        "student_id" => $studentId,
        "email" => $email,
        "role" => $role,
        "status" => $status,
    ];
}

$_SESSION["user_id"] = intval($user["id"] ?? 0);
$_SESSION["full_name"] = (string) ($user["full_name"] ?? $fullName);
$_SESSION["student_id"] = (string) ($user["student_id"] ?? "");
$_SESSION["email"] = (string) ($user["email"] ?? $email);
$_SESSION["role"] = (string) ($user["role"] ?? "Student");

header("Location: dashboard.php");
exit();


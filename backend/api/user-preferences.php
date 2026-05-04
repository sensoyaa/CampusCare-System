<?php
/**
 * User Preferences API
 * Handles saving and retrieving user preferences from database
 * 
 * Endpoints:
 * POST /api/user-preferences/save - Save user preferences
 * GET /api/user-preferences/get - Get user preferences
 */

require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../config/cors.php";

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Validate authentication
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = intval($_SESSION["user_id"]);

// Route requests
if ($method === "POST" && strpos($path, "save") !== false) {
    handleSavePreferences($conn, $userId);
} elseif ($method === "GET" && strpos($path, "get") !== false) {
    handleGetPreferences($conn, $userId);
} elseif ($method === "POST" && strpos($path, "apply-dark-mode") !== false) {
    handleApplyDarkMode($conn, $userId);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Endpoint not found"]);
    exit;
}

/**
 * Save user preferences to database
 */
function handleSavePreferences($conn, $userId)
{
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

    $darkModeEnabled = isset($data["dark_mode_enabled"]) ? (int)$data["dark_mode_enabled"] : 0;
    $notificationsEnabled = isset($data["notifications_enabled"]) ? (int)$data["notifications_enabled"] : 1;
    $notificationsInApp = isset($data["notifications_in_app"]) ? (int)$data["notifications_in_app"] : 1;
    $notificationsEmail = isset($data["notifications_email"]) ? (int)$data["notifications_email"] : 0;
    $notifyAppointments = isset($data["notify_appointments"]) ? (int)$data["notify_appointments"] : 1;
    $notifyEvents = isset($data["notify_events"]) ? (int)$data["notify_events"] : 1;
    $notifySystem = isset($data["notify_system"]) ? (int)$data["notify_system"] : 1;
    $notificationTiming = isset($data["notification_timing"]) ? sanitizeNotificationTiming($data["notification_timing"]) : "24h";
    
    $privacyProfileVisible = isset($data["privacy_profile_visible"]) ? (int)$data["privacy_profile_visible"] : 1;
    $privacyDataSharing = isset($data["privacy_data_sharing"]) ? (int)$data["privacy_data_sharing"] : 0;
    
    $sessionIdleTimeoutMinutes = isset($data["session_idle_timeout_minutes"]) ? sanitizeIdleTimeout($data["session_idle_timeout_minutes"]) : 60;
    $trustedBrowserEnabled = isset($data["trusted_browser_enabled"]) ? (int)$data["trusted_browser_enabled"] : 0;

    // Check if preferences exist
    $checkStmt = $conn->prepare("SELECT id FROM user_preferences WHERE user_id = ? LIMIT 1");
    if (!$checkStmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
        return;
    }

    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $exists = $result->num_rows > 0;
    $checkStmt->close();

    if ($exists) {
        // Update existing preferences
        $sql = "UPDATE user_preferences SET 
                dark_mode_enabled = ?,
                notifications_enabled = ?,
                notifications_in_app = ?,
                notifications_email = ?,
                notify_appointments = ?,
                notify_events = ?,
                notify_system = ?,
                notification_timing = ?,
                privacy_profile_visible = ?,
                privacy_data_sharing = ?,
                session_idle_timeout_minutes = ?,
                trusted_browser_enabled = ?
                WHERE user_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Database error"]);
            return;
        }

        $stmt->bind_param(
            "iiiiiiissiiiii",
            $darkModeEnabled,
            $notificationsEnabled,
            $notificationsInApp,
            $notificationsEmail,
            $notifyAppointments,
            $notifyEvents,
            $notifySystem,
            $notificationTiming,
            $privacyProfileVisible,
            $privacyDataSharing,
            $sessionIdleTimeoutMinutes,
            $trustedBrowserEnabled,
            $userId
        );
    } else {
        // Insert new preferences
        $sql = "INSERT INTO user_preferences (
                user_id, dark_mode_enabled, notifications_enabled, notifications_in_app,
                notifications_email, notify_appointments, notify_events, notify_system,
                notification_timing, privacy_profile_visible, privacy_data_sharing,
                session_idle_timeout_minutes, trusted_browser_enabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Database error"]);
            return;
        }

        $stmt->bind_param(
            "iiiiiiissiiiii",
            $userId,
            $darkModeEnabled,
            $notificationsEnabled,
            $notificationsInApp,
            $notificationsEmail,
            $notifyAppointments,
            $notifyEvents,
            $notifySystem,
            $notificationTiming,
            $privacyProfileVisible,
            $privacyDataSharing,
            $sessionIdleTimeoutMinutes,
            $trustedBrowserEnabled
        );
    }

    if ($stmt->execute()) {
        // Store in session for immediate use
        $_SESSION["user_preferences"] = [
            "dark_mode_enabled" => $darkModeEnabled,
            "notifications_enabled" => $notificationsEnabled,
            "notifications_in_app" => $notificationsInApp,
            "notifications_email" => $notificationsEmail,
            "notify_appointments" => $notifyAppointments,
            "notify_events" => $notifyEvents,
            "notify_system" => $notifySystem,
            "notification_timing" => $notificationTiming,
            "privacy_profile_visible" => $privacyProfileVisible,
            "privacy_data_sharing" => $privacyDataSharing,
            "session_idle_timeout_minutes" => $sessionIdleTimeoutMinutes,
            "trusted_browser_enabled" => $trustedBrowserEnabled
        ];

        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Preferences saved successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to save preferences"]);
    }

    $stmt->close();
}

/**
 * Get user preferences from database
 */
function handleGetPreferences($conn, $userId)
{
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
        return;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $prefs = $result->fetch_assoc();
    $stmt->close();

    if ($prefs) {
        // Return preferences (exclude database IDs)
        unset($prefs["id"]);
        unset($prefs["user_id"]);
        http_response_code(200);
        echo json_encode($prefs);
    } else {
        // Return default preferences
        http_response_code(200);
        echo json_encode([
            "dark_mode_enabled" => 0,
            "notifications_enabled" => 1,
            "notifications_in_app" => 1,
            "notifications_email" => 0,
            "notify_appointments" => 1,
            "notify_events" => 1,
            "notify_system" => 1,
            "notification_timing" => "24h",
            "privacy_profile_visible" => 1,
            "privacy_data_sharing" => 0,
            "session_idle_timeout_minutes" => 60,
            "trusted_browser_enabled" => 0
        ]);
    }
}

/**
 * Apply dark mode globally
 */
function handleApplyDarkMode($conn, $userId)
{
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $enabled = isset($data["enabled"]) ? (int)$data["enabled"] : 0;

    $stmt = $conn->prepare("UPDATE user_preferences SET dark_mode_enabled = ? WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Database error"]);
        return;
    }

    $stmt->bind_param("ii", $enabled, $userId);
    if ($stmt->execute()) {
        $_SESSION["user_preferences"]["dark_mode_enabled"] = $enabled;
        http_response_code(200);
        echo json_encode(["success" => true, "dark_mode_enabled" => $enabled]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update dark mode"]);
    }

    $stmt->close();
}

/**
 * Sanitize notification timing value
 */
function sanitizeNotificationTiming($value)
{
    $allowed = ["15m", "1h", "24h", "3d"];
    return in_array($value, $allowed, true) ? $value : "24h";
}

/**
 * Sanitize idle timeout value
 */
function sanitizeIdleTimeout($value)
{
    $allowed = [15, 30, 60, 120, 0]; // 0 for "Off"
    $intVal = intval($value);
    return in_array($intVal, $allowed, true) ? $intVal : 60;
}
?>

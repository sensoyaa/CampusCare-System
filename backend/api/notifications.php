<?php
/**
 * Notifications API
 * Handles fetching, marking as read, and managing user notifications
 * 
 * Endpoints:
 * GET /backend/api/notifications.php - Get all unread notifications
 * POST /backend/api/notifications.php?action=mark-read - Mark notification as read
 * POST /backend/api/notifications.php?action=mark-all-read - Mark all as read
 * POST /backend/api/notifications.php?action=delete - Delete a notification
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../config/cors.php";

header("Content-Type: application/json");

// Check authentication
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized", "notifications" => []]);
    exit;
}

$userId = intval($_SESSION["user_id"]);
$method = $_SERVER["REQUEST_METHOD"];
$action = isset($_GET["action"]) ? trim($_GET["action"]) : "get";

try {
    if ($method === "GET" && $action === "get") {
        getNotifications($conn, $userId);
    } elseif ($method === "POST" && $action === "mark-read") {
        markNotificationRead($conn, $userId);
    } elseif ($method === "POST" && $action === "mark-all-read") {
        markAllNotificationsRead($conn, $userId);
    } elseif ($method === "POST" && $action === "delete") {
        deleteNotification($conn, $userId);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error", "message" => $e->getMessage()]);
}

/**
 * Get all unread notifications for user
 */
function getNotifications($conn, $userId)
{
    $stmt = $conn->prepare("
        SELECT 
            id,
            type,
            title,
            message,
            action_url,
            is_read,
            created_at,
            read_at
        FROM user_notifications
        WHERE user_id = ? AND is_archived = 0
        ORDER BY created_at DESC
        LIMIT 50
    ");

    if (!$stmt) {
        // If table is missing or query can't prepare, keep UI functional with empty state.
        echo json_encode([
            "success" => true,
            "notifications" => [],
            "unreadCount" => 0,
            "totalCount" => 0
        ]);
        return;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    $unreadCount = 0;

    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            "id" => intval($row["id"]),
            "type" => $row["type"],
            "title" => $row["title"],
            "message" => $row["message"],
            "actionUrl" => $row["action_url"],
            "isRead" => intval($row["is_read"]),
            "createdAt" => $row["created_at"],
            "readAt" => $row["read_at"]
        ];

        if (intval($row["is_read"]) === 0) {
            $unreadCount++;
        }
    }

    $stmt->close();

    echo json_encode([
        "success" => true,
        "notifications" => $notifications,
        "unreadCount" => $unreadCount,
        "totalCount" => count($notifications)
    ]);
}

/**
 * Mark single notification as read
 */
function markNotificationRead($conn, $userId)
{
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $notificationId = intval($data["notification_id"] ?? 0);

    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid notification ID"]);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE user_notifications
        SET is_read = 1, read_at = NOW()
        WHERE id = ? AND user_id = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Update error"]);
        return;
    }

    $stmt->bind_param("ii", $notificationId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success" => $success,
        "message" => $success ? "Notification marked as read" : "Failed to mark notification"
    ]);
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead($conn, $userId)
{
    $stmt = $conn->prepare("
        UPDATE user_notifications
        SET is_read = 1, read_at = NOW()
        WHERE user_id = ? AND is_read = 0
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Update error"]);
        return;
    }

    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        "success" => $success,
        "message" => $success ? "All notifications marked as read" : "Failed to mark notifications",
        "affectedRows" => $affected
    ]);
}

/**
 * Delete/archive a notification
 */
function deleteNotification($conn, $userId)
{
    $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
    $notificationId = intval($data["notification_id"] ?? 0);

    if ($notificationId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid notification ID"]);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE user_notifications
        SET is_archived = 1
        WHERE id = ? AND user_id = ?
    ");

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Delete error"]);
        return;
    }

    $stmt->bind_param("ii", $notificationId, $userId);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        "success" => $success,
        "message" => $success ? "Notification deleted" : "Failed to delete notification"
    ]);
}
?>

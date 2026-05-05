<?php
require_once __DIR__ . "/../config/cors.php";
require_once __DIR__ . "/../config/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data["id"] ?? 0);

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user ID."
    ]);
    exit();
}

// Start transaction for cascading deletes
$conn->begin_transaction();

try {
    // Check and delete from mental_health_test_answers if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_test_answers'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM mental_health_test_answers 
             WHERE attempt_id IN (
                SELECT id FROM mental_health_test_attempts WHERE user_id = ?
             )"
        );
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check and delete from mental_health_test_attempts if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_test_attempts'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM mental_health_test_attempts WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check and delete from mental_health_tests if table has user_id
    $checkTable = $conn->query("SHOW TABLES LIKE 'mental_health_tests'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM mental_health_tests LIKE 'user_id'");
        if ($checkColumn && $checkColumn->num_rows > 0) {
            $stmt = $conn->prepare("DELETE FROM mental_health_tests WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Delete appointments
    $stmt = $conn->prepare("DELETE FROM appointments WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Check and delete from event_participants if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'event_participants'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM event_participants WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check and delete from user_notifications if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM user_notifications WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check and delete from user_sessions if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check and delete from user_preferences if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_preferences'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Finally, delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "User deleted successfully."
    ]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Failed to delete user: " . $e->getMessage()
    ]);
}

$conn->close();
?>

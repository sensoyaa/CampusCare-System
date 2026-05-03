<?php
require_once "php-frontend/includes/db.php";

// Create event_comments table
$sql = "CREATE TABLE IF NOT EXISTS `event_comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For nested comments',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql) === TRUE) {
    echo "Table event_comments created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'event_comments'");
if ($result->num_rows > 0) {
    echo "Table event_comments exists in the database.<br>";

    // Show table structure
    echo "<br>Table structure:<br>";
    $columns = $conn->query("DESCRIBE event_comments");
    while ($row = $columns->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Table event_comments does not exist.<br>";
}

$conn->close();
?>

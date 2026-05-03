<?php
require_once "php-frontend/includes/db.php";

echo "<h2>Database Tables Verification</h2>";

// Check event_checkins table
$result = $conn->query("SHOW TABLES LIKE 'event_checkins'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ event_checkins table exists</p>";

    // Show table structure
    echo "<h3>event_checkins structure:</h3>";
    $columns = $conn->query("DESCRIBE event_checkins");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ event_checkins table does not exist</p>";
}

// Check event_feedback table
$result = $conn->query("SHOW TABLES LIKE 'event_feedback'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ event_feedback table exists</p>";

    // Show table structure
    echo "<h3>event_feedback structure:</h3>";
    $columns = $conn->query("DESCRIBE event_feedback");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ event_feedback table does not exist</p>";
}

// Check event_comments table
$result = $conn->query("SHOW TABLES LIKE 'event_comments'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'>✓ event_comments table exists</p>";

    // Show table structure
    echo "<h3>event_comments structure:</h3>";
    $columns = $conn->query("DESCRIBE event_comments");
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>✗ event_comments table does not exist</p>";
}

// Check events table for new columns
$result = $conn->query("DESCRIBE events");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

echo "<h3>Events table columns:</h3>";
echo "<ul>";
foreach ($columns as $column) {
    echo "<li>" . htmlspecialchars($column) . "</li>";
}
echo "</ul>";

// Check if new columns exist
$newColumns = ['category', 'image_url', 'max_participants'];
echo "<h3>New columns check:</h3>";
foreach ($newColumns as $column) {
    if (in_array($column, $columns)) {
        echo "<p style='color: green;'>✓ $column column exists in events table</p>";
    } else {
        echo "<p style='color: red;'>✗ $column column does not exist in events table</p>";
    }
}

$conn->close();
?>

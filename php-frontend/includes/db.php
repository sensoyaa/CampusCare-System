<?php
// Set timezone to Philippines
date_default_timezone_set("Asia/Manila");

$host = "localhost";
$user = "root";
$password = "";
$database = "campuscare_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
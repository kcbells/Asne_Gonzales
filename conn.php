<?php
// Enable detailed error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Database connection settings
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'register_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Set charset
$conn->set_charset('utf8mb4');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
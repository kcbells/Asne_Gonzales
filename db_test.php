<?php
// Connection details
$db_host = 'localhost';   // the server where MySQL is running (usually localhost)
$db_user = 'root';        // the MySQL username (default in XAMPP/WAMP is root)
$db_pass = '';            // the MySQL password (empty by default in XAMPP/WAMP)
$db_name = 'register_db'; // the database you created in MySQL

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Set character encoding
$conn->set_charset('utf8mb4');
?>
<?php
// Include connection file
include 'db_connect.php';

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize inputs
    $name     = $conn->real_escape_string($_POST['name']);
    $email    = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // hash password
    $address  = $conn->real_escape_string($_POST['address']);
    $city     = $conn->real_escape_string($_POST['city']);
    $state    = $conn->real_escape_string($_POST['state']);
    $zip      = $conn->real_escape_string($_POST['zip']);

    // Insert into database
    $sql = "INSERT INTO users (name, email, password, address, city, state, zip) 
            VALUES ('$name', '$email', '$password', '$address', '$city', '$state', '$zip')";

    if ($conn->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Registration successful!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
$conn->close();
?>
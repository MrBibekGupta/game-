
<?php

$host = "localhost";
$username = "root";
$password = "";
$database = "banana_game";

// Create database connection
$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set UTF-8
$conn->set_charset("utf8mb4");

?>

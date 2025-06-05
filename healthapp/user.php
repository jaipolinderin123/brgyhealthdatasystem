<?php

$servername = "localhost";
$username = "admin";
$password = "healthdata123";
$database = "healthdata";

// Create a new MySQLi connection
$conn = new mysqli($servername, $username, $password, $database);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create a table for storing user data if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'users' created successfully or already exists.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

// Function to store user data in the database
function storeUserData($name, $email, $password)
{
    global $conn;

    // Hash the password before storing it
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Prepare an SQL statement to insert user data
    $stmt = $conn->prepare("INSERT INTO users (name, username, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $username, $hashedPassword);

    // Execute the statement and check for errors
    if ($stmt->execute()) {
        echo "New user created successfully.\n";
    } else {
        echo "Error: " . $stmt->error . "\n";
    }

    // Close the statement
    $stmt->close();
}

// Example usage of the storeUserData function
storeUserData("John Doe", "johndoe_123", "password123");

// Close the connection when you're done
$conn->close();
?>
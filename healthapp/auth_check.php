<?php
// auth_check.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection that can be included in other files
try {
    $conn = new PDO("mysql:host=localhost;dbname=healthdata", "admin", "healthdata123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
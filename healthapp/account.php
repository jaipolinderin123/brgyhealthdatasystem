<?php
// Start session at the very beginning
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
try {
    $conn = new PDO("mysql:host=sql112.ezyro.com;dbname=ezyro_39081039_healthdata", "ezyro_39081039", "healthdata12345");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize error array
$_SESSION['errors'] = [];

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current user data
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_user) {
            $_SESSION['errors'][] = "User account not found";
            header("Location: edit_account.php");
            exit();
        }
    } catch(PDOException $e) {
        $_SESSION['errors'][] = "Error fetching user data: " . $e->getMessage();
        header("Location: edit_account.php");
        exit();
    }

    // Validate current password
    if (!password_verify($_POST['current_password'], $current_user['password'])) {
        $_SESSION['errors'][] = "Current password is incorrect";
    }

    // Validate full name
    if (empty(trim($_POST['fullname']))) {
        $_SESSION['errors'][] = "Full name is required";
    }

    // Validate username
    if (empty(trim($_POST['username']))) {
        $_SESSION['errors'][] = "Username is required";
    } else {
        // Check if username is already taken by another user
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $stmt->bindParam(':username', $_POST['username']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $_SESSION['errors'][] = "Username is already taken";
            }
        } catch(PDOException $e) {
            $_SESSION['errors'][] = "Error checking username availability";
        }
    }

    // Validate new password if provided
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 8) {
            $_SESSION['errors'][] = "New password must be at least 8 characters long";
        }
        
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['errors'][] = "New passwords do not match";
        }
    }

    // If no errors, update the account
    if (empty($_SESSION['errors'])) {
        try {
            // Prepare base update query
            $query = "UPDATE users SET fullname = :fullname, username = :username";
            $params = [
                ':fullname' => trim($_POST['fullname']),
                ':username' => trim($_POST['username']),
                ':user_id' => $_SESSION['user_id']
            ];

            // Add password to update if new password was provided
            if (!empty($_POST['new_password'])) {
                $query .= ", password = :password";
                $params[':password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }

            $query .= " WHERE id = :user_id";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($params);

            // Set success message
            $_SESSION['success_message'] = "Account information updated successfully!";
            
            // Redirect back to edit page
            header("Location: edit_account.php");
            exit();
        } catch(PDOException $e) {
            $_SESSION['errors'][] = "Error updating account: " . $e->getMessage();
            header("Location: edit_account.php");
            exit();
        }
    } else {
        // Redirect back with errors
        header("Location: edit_account.php");
        exit();
    }
} else {
    // Not a POST request, redirect to edit page
    header("Location: edit_account.php");
    exit();
}
?>
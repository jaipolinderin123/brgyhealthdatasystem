<?php
// index.php for Barangay Health Data System

// Start session
session_start();

// Check if user is already logged in, redirect to dashboard if so
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple input sanitization
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Example DB connection (update with actual DB credentials and logic)
        $conn = new mysqli('localhost', 'root', '', 'brgyhealthdatasystem');

        if ($conn->connect_error) {
            die("Database connection failed: " . $conn->connect_error);
        }

        // Prevent SQL injection - use prepared statements
        $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $password_hash);
            $stmt->fetch();

            if (password_verify($password, $password_hash)) {
                // Set session and redirect
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Health Data System - Login</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Simple inline style for demonstration */
        body { font-family: Arial, sans-serif; background: #f0f4f8; }
        .login-container {
            width: 350px; margin: 100px auto; padding: 30px 25px;
            background: #fff; border-radius: 10px; box-shadow: 0 0 12px #ccc;
        }
        .login-container h2 { text-align: center; margin-bottom: 20px; }
        .login-container .error { color: red; text-align: center; }
        .login-container input[type="text"], .login-container input[type="password"] {
            width: 100%; padding: 10px; margin: 8px 0 15px; border: 1px solid #ccc; border-radius: 5px;
        }
        .login-container input[type="submit"] {
            width: 100%; padding: 10px; background: #3498db; color: #fff;
            border: none; border-radius: 5px; font-size: 16px; cursor: pointer;
        }
        .login-container input[type="submit"]:hover {
            background: #217dbb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Barangay Health Data System</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <input type="submit" value="Login">
        </form>
        <!-- Optionally, add links for registration or password reset here -->
    </div>
</body>
</html>

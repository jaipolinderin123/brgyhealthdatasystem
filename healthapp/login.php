<?php

session_start();

// Database connection
try {
    $conn = new PDO("mysql:host=localhost;dbname=healthdata", "admin", "healthdata123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['signup'])) {
        // Signup logic
        $fullname = $_POST['fullname'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $message = "Username already exists!";
                $message_type = "error";
            } else {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (fullname, username, password) VALUES (:fullname, :username, :password)");
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                $stmt->execute();
                
                $message = "Account created successfully!";
                $message_type = "success";
            }
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    } 
    elseif (isset($_POST['login'])) {
        // Login logic
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    header("Location: homepage.php");
                    exit();
                } else {
                    $message = "Invalid password!";
                    $message_type = "error";
                }
            } else {
                $message = "Username not found!";
                $message_type = "error";
            }
        } catch(PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #d1f2e7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .main-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }
        .welcome-section {
            margin-bottom: 30px;
        }
        .welcome-section h1 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
        }
        .form-container h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            text-transform: uppercase;
            font-size: 1.3rem;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 1rem;
        }
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            font-size: 0.9rem;
        }
        .remember-me {
            display: flex;
            align-items: center;
            color: #7f8c8d;
        }
        .remember-me input {
            margin-right: 8px;
        }
        .forgot-password {
            color: #3498db;
            text-decoration: none;
        }
        .form-button {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        .form-button:hover {
            background-color: #2980b9;
        }
        .switch-form {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        .switch-form a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .hidden {
            display: none;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Welcome section - shown only for login -->
        <div class="welcome-section" id="welcomeHeader">
            <h1>Welcome!</h1>
            <img src="images/image_clean_no_bg.png" alt="Barangay Seal" class="logo">
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <div class="form-container" id="loginForm">
            <h2>USER LOGIN</h2>
            <form method="POST" action="">
                <input type="hidden" name="login" value="1">
                <div class="form-group">
                    <input type="text" id="loginUsername" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" id="loginPassword" name="password" placeholder="Password" required>
                </div>
                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="rememberMe">
                        <label for="rememberMe">Remember Me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot Password?</a>
                </div>
                <button type="submit" class="form-button">Login</button>
            </form>
            <div class="switch-form">
                Don't have an account? <a href="#" onclick="showSignup()">Sign Up</a>
            </div>
        </div>
        
        <!-- Sign Up Form -->
        <div class="form-container hidden" id="signupForm">
            <img src="images/image_clean_no_bg.png" alt="Barangay Seal" class="logo" style="margin-bottom: 30px;">
            <h2>CREATE ACCOUNT</h2>
            <form method="POST" action="">
                <input type="hidden" name="signup" value="1">
                <div class="form-group">
                    <input type="text" id="fullname" name="fullname" placeholder="Full Name" required>
                </div>
                <div class="form-group">
                    <input type="text" id="signupUsername" name="username" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <input type="password" id="signupPassword" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <input type="password" id="confirmPassword" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="form-button">Sign Up</button>
            </form>
            <div class="switch-form">
                Already have an account? <a href="#" onclick="showLogin()">Log In</a>
            </div>
        </div>
    </div>

    <script>
        function showSignup() {
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('signupForm').classList.remove('hidden');
            document.getElementById('welcomeHeader').classList.add('hidden');
        }

        function showLogin() {
            document.getElementById('signupForm').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('welcomeHeader').classList.remove('hidden');
        }

        // Client-side password match validation
        document.querySelector('form[name="signup"]')?.addEventListener('submit', function(event) {
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                alert("Passwords don't match!");
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
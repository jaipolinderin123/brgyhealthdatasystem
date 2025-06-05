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

// Get current user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User not found in database, log them out
        session_destroy();
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Check for success/error messages from account.php
$success_message = '';
$errors = [];

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .title-page {
            background-color: #007bff;
            color: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: bold;
            color: #2c3e50;
        }
        .btn-primary {
            background-color: #007bff;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
        }
        .btn-primary:hover {
            background-color: #0069d9;
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        /* Sidebar styles */
        .sidebar {
            height: 100%;
            width: 250px;
            position: fixed;
            z-index: 1;
            top: 0;
            left: 0;
            background-color: #343a40;
            overflow-x: hidden;
            padding-top: 20px;
            color: white;
        }
        .sidebar-header {
            padding: 10px 15px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            border-bottom: 1px solid #4b545c;
            margin-bottom: 10px;
        }
        .sidebar a {
            padding: 8px 10px;
            text-decoration: none;
            font-size: 1rem;
            color: #d1d1d1;
            display: block;
            transition: 0.3s;
        }
        .sidebar a:hover {
            color: white;
            background-color: #495057;
        }
        .sidebar .active {
            color: white;
            background-color: #007bff;
        }
        .sidebar-section {
            margin-bottom: 15px;
            border-bottom: 1px solid #4b545c;
            padding-bottom: 10px;
        }
        .sidebar-section-title {
            padding: 10px 15px;
            font-size: 0.9rem;
            color: #9a9da0;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 5px;
            background-color: #2c3e50;
            text-align: center;
            font-size: 0.8rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar a {float: left;}
            .main-content {margin-left: 0;}
            .sidebar-footer {position: relative;}
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="/healthapp/images/image_clean_no_bg.png" alt="Healthcare Logo" style="height: 150px; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto;">
            <i class=></i>Healthcare Data System
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Navigation</div>
            <a href="homepage.php">
                <i class="bi bi-house-door"></i> Home
            </a>
            <a href="dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="servicesrecords.php">
                <i class="bi bi-clipboard-data"></i> Service Records
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Analytics</div>
            <a href="healthdatavisual.php">
                <i class="bi bi-bar-chart-line"></i> Data Visualizations
            </a>
            <a href="clusteringresults.php">
                <i class="bi bi-diagram-3"></i> Services Clustering Results
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="account.php" class="active">
                <i class="bi bi-person-gear"></i> My Account
            </a>
            <a href="about.php">
                <i class="bi bi-info-circle"></i> About
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" style="color: white;">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
            <div style="margin-top: 3px; color: #b8c7ce; font-size: 0.7rem;">
                &copy; <?php echo date('Y'); ?>Predictive Healthcare Data System
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="title-page">
                <h1><i class="bi bi-person-gear"></i> Edit Account Information</h1>
                <p>Update your personal details and account information</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="account.php" method="post">
                <div class="form-group">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" 
                           value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password (for verification)</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                    <small class="text-muted">You must enter your current password to make any changes</small>
                </div>

                <div class="form-group">
                    <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" class="form-control" id="new_password" name="new_password">
                    <small class="text-muted">Must be at least 8 characters long</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// dashboard.php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
try {
    $conn = new PDO("mysql:host=localhost;dbname=healthdata", "admin", "healthdata123");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
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
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .welcome {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .user-info {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-btn:hover {
            background-color: #c0392b;
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
            padding: 10px 15px;
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
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar a {float: left;}
            .main-content {margin-left: 0;}
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="/healthapp/images/image_clean_no_bg.png" alt="Healthcare Logo" style="height: 50px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">
            <i class="bi bi-heart-pulse"></i> Predictive Healthcare Data System
        </div>
        <a href="homepage.php">
            <i class="bi bi-house-door"></i> Homepage
        </a>
        <a href="dashboard.php" class="active">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="servicesrecords.php">
            <i class="bi bi-clipboard-data"></i> Service Records
        </a>
        <a href="healthdatavisuals.php">
            <i class="bi bi-bar-chart-line"></i> Health Data Visuals
        </a>
        <a href="clusteringresults.php">
            <i class="bi bi-diagram-3"></i> Clustering Results
        </a>
        <a href="predictivemodeling.php">
            <i class="bi bi-cpu"></i> Predictive Modeling
        </a>
        <a href="accountmanager.php">
            <i class="bi bi-person-gear"></i> Account Manager
        </a>
        <div style="border-top: 1px solid #4b545c; margin: 15px 0;"></div>
        <a href="about.php">
            <i class="bi bi-info-circle"></i> About
        </a>
        <a href="logout.php">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1 class="welcome">Welcome, <?php echo htmlspecialchars($user['fullname']); ?>!</h1>
            
            <div class="user-info">
                <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Account created:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
            </div>
            
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
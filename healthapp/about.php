<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About the System | Barangay Bagumbayan Health Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #2c3e50;
        }
        h1 {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            margin-top: 25px;
            color: #2980b9;
        }
        .highlight {
            background-color: #eaf2f8;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin: 20px 0;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-style: italic;
            color: #7f8c8d;
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
        .sidebar-section {
            margin-bottom: 15px;
            border-bottom: 1px solid #4b545c;
            padding-bottom: 10px;
        }
        .sidebar-section-title {
            padding: 8px 10px;
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
            padding: 10px;
            background-color: #2c3e50;
            text-align: center;
            font-size: 0.8rem;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
            <a href="account.php">
                <i class="bi bi-person-gear"></i> My Account
            </a>
            <a href="about.php" class="active">
                <i class="bi bi-info-circle"></i> About
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" style="color: white;">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
            <div style="margin-top: 3px; color: #b8c7ce; font-size: 0.7rem;">
                &copy; <?php echo date('Y'); ?> Predictive Healthcare Data System
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1>System Overview</h1>
            <p>The system is part of the study titled <strong>"Predictive Modeling for Healthcare Resource Utilization in Barangay Bagumbayan Health Center Using K-Means Clustering"</strong>. It was developed by Joedelyn Polines Henderin, a 4th year Computer Science student at Southern Baptist College, located in M'lang, North Cotabato.</p>
            
            <p>This system was created to assist in the digitalization and efficient handling of healthcare services data at Barangay Bagumbayan. Through the use of K-Means clustering, it aims to help healthcare professionals manage data more effectively and make informed decisions based on patterns and trends in healthcare service utilization.</p>
            
            <h2>Purpose of the System</h2>
            <p>The system is designed to:</p>
            <ul>
                <li>Support healthcare professionals in organizing and analyzing large volumes of health data.</li>
                <li>Identify service gaps or inefficiencies in the delivery of healthcare within the barangay.</li>
                <li>Recognize well-performing areas to maintain and enhance effective health service delivery.</li>
            </ul>
            
            <h2>Key Features</h2>
            <div class="highlight">
                <ul>
                    <li><strong>K-Means Clustering Algorithm:</strong> Used to classify health service data into meaningful clusters, enabling pattern recognition.</li>
                    <li><strong>Predictive Modeling:</strong> Helps forecast future needs and prioritize resource allocation.</li>
                    <li><strong>Visual Analytics:</strong> Includes data visualizations such as PCA scatter plots, pie charts, and the Elbow Method to aid understanding.</li>
                    <li><strong>Data Management Tools:</strong> Simplifies the handling of healthcare records and statistics for easy access and reporting.</li>
                </ul>
            </div>
            
            <h2>Impact</h2>
            <p>This system promotes data-driven decision-making and contributes to the modernization of healthcare data management at the community level. By helping barangay health officials to spot trends and gaps, it can lead to more responsive and efficient healthcare service delivery in Barangay Bagumbayan.</p>
            
            <div class="footer">
                <p>Barangay Bagumbayan Health Center &copy; <?php echo date("Y"); ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
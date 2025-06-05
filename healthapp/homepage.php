<?php
// Start session and initialize username
session_start();
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';

// Define image path - adjust this to match your exact file location
$imageRelativePath = 'images/image_clean_no_bg.png';
$imageAbsolutePath = __DIR__ . DIRECTORY_SEPARATOR . $imageRelativePath;
$imageExists = file_exists($imageAbsolutePath);

// Page configuration
$pageTitle = "Healthcare Data System";
$welcomeMessage = "Data-driven insights for better patient care";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #d1f2e7; /* Changed to match login page */
            color: #2d3748;
            padding: 2rem 1rem;
            text-align: center;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
        }
        
        .logo-img {
            width: 120px;
            height: 120px;
            object-fit: contain;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 0 auto 1rem;
            display: block;
        }
        
        .logo-placeholder {
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e2e8f0;
            border-radius: 50%;
            margin: 0 auto 1rem;
        }
        
        .logo-placeholder i {
            font-size: 2rem;
            color: #15803d;
        }
        
        .logo-error {
            color: #dc2626;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        h1 {
            color: #15803d;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }

        .welcome-greeting {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .description {
            max-width: 600px;
            margin: 0 auto 2rem;
            line-height: 1.6;
            color: #4a5568;
        }

        .cta-section {
            margin: 3rem 0;
        }

        .explore-btn {
            display: inline-block;
            background-color: #15803d;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin: 2rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            font-size: 1.1rem;
        }

        .explore-btn:hover {
            background-color: #22c55e;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .explore-btn i {
            margin-left: 8px;
        }

        .feature-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem auto;
            max-width: 1000px;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .card i {
            font-size: 2rem;
            color: #22c55e;
            margin-bottom: 1rem;
        }

        .card h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #15803d;
        }

        @media (max-width: 768px) {
            .logo-img, .logo-placeholder {
                width: 100px;
                height: 100px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .feature-cards {
                grid-template-columns: 1fr;
            }
            
            .welcome-greeting {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                <?php if ($imageExists): ?>
                    <img src="<?php echo htmlspecialchars($imageRelativePath); ?>" alt="Healthcare Logo" class="logo-img">
                <?php else: ?>
                    <div class="logo-error">
                        System logo not found at: <?php echo htmlspecialchars($imageAbsolutePath); ?>
                    </div>
                    <div class="logo-placeholder">
                        <i class="fas fa-hospital"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        
        <!-- Separated Welcome Greeting -->
        <p class="welcome-greeting">Welcome, <?php echo htmlspecialchars($username); ?>!</p>
        
        <!-- Description Section -->
        <p class="description">
            <?php echo htmlspecialchars($welcomeMessage); ?><br>
            Our platform provides advanced analytics tools to help healthcare professionals make data-driven decisions.
        </p>

        <div class="feature-cards">
            <div class="card">
                <i class="fas fa-chart-line"></i>
                <h4>Predictive Analytics</h4>
                <p>Forecast patient needs using advanced algorithms</p>
            </div>
            <div class="card">
                <i class="fas fa-project-diagram"></i>
                <h4>Clustering Tools</h4>
                <p>Group similar cases for optimized treatment</p>
            </div>
            <div class="card">
                <i class="fas fa-user-cog"></i>
                <h4>User Interface</h4>
                <p>Intuitive design for efficient workflow</p>
            </div>
        </div>

        <!-- Explore Now Button at Bottom -->
        <div class="cta-section">
            <a href="dashboard.php" class="explore-btn">
                Explore Now <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</body>
</html>
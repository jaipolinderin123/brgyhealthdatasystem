<!DOCTYPE html>
<html>
<head>
    <title>Healthcare Resource Utilization Dashboard</title>
    <style>
        :root {
            --bg-color: #d1f2e7;
            --button-gradient-start: #4CAF50;
            --button-gradient-end: #8BC34A;
            --text-color: #333;
            --card-bg: rgba(255, 255, 255, 0.8);
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .home-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
            background-color: var(--bg-color);
        }

        h1 {
            font-size: 36px;
            color: var(--text-color);
            margin-bottom: 20px;
        }

        .welcome-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
        }

        .features {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .feature-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 30%;
            min-width: 250px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .feature-card h2 {
            font-size: 24px;
            color: var(--button-gradient-start);
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 16px;
            color: #555;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; // Assuming this is your equivalent of base.html ?>
    
    <div class="home-container">
        <h1>🏠 Home</h1>
        <p class="welcome-message">Welcome to the Healthcare Resource Utilization Dashboard.</p>
        
        <div class="features">
            <div class="feature-card">
                <h2>📊 Data Analysis</h2>
                <p>Explore and analyze healthcare data to gain insights.</p>
            </div>
            <div class="feature-card">
                <h2>📈 Predictive Modeling</h2>
                <p>Use advanced models to predict future resource needs.</p>
            </div>
            <div class="feature-card">
                <h2>📋 Reports</h2>
                <p>Generate detailed reports for better decision-making.</p>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; // Footer if you have one ?>
</body>
</html>
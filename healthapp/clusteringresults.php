<?php
// Function to safely include files with error handling
function safeInclude($filename) {
    if (file_exists($filename)) {
        include $filename;
    } else {
        echo "<div class='error'>File not found: $filename</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clustering Results Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background-color: #f5f7fa;
        }

        /* Sidebar Styles */
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #343a40;
            color: white;
            overflow-y: auto;
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
            padding: 10px 10px;
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
        .sidebar a i {
            margin-right: 10px;
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

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }

        /* Title Page Styles */
        .title-page {
            background: linear-gradient(135deg, #007bff, #00bcd4);
            color: white;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .title-page h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .title-page p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 20px;
            line-height: 1.6;
        }
        
        .title-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: rgba(255,255,255,0.9);
        }

        /* Tab Container Styles */
        .tab-container {
            position: relative;
            min-height: 60px;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            z-index: 2;
        }

        /* Tab styles with enhanced buttons */
        .tab {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            display: flex;
            flex-wrap: wrap;
            background-color: #fff;
            border-radius: 8px 8px 0 0;
            z-index: 10;
            padding: 5px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .tab button {
            background-color: inherit;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 12px 20px;
            transition: 0.3s;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            position: relative;
            margin: 2px;
            border-radius: 5px;
            flex-grow: 1;
            text-align: center;
            min-width: 120px;
        }

        /* Color indicators for each clustering result button */
        .tab button[onclick*="Deworming"] {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
        }
        .tab button[onclick*="BloodPressure"] {
            background-color: #fce4ec;
            border-left: 4px solid #E91E63;
        }
        .tab button[onclick*="VitaminA"] {
            background-color: #fff8e1;
            border-left: 4px solid #FFC107;
        }
        .tab button[onclick*="Postpartum"] {
            background-color: #e8f5e9;
            border-left: 4px solid #4CAF50;
        }
        .tab button[onclick*="DispensedMedicine"] {
            background-color: #f3e5f5;
            border-left: 4px solid #9C27B0;
        }
        .tab button[onclick*="Immunization"] {
            background-color: #e0f7fa;
            border-left: 4px solid #00BCD4;
        }
        .tab button[onclick*="FamilyPlanning"] {
            background-color: #fff3e0;
            border-left: 4px solid #FF9800;
        }
        .tab button[onclick*="MaternalCare"] {
            background-color: #ede7f6;
            border-left: 4px solid #673AB7;
        }

        .tab button:hover {
            filter: brightness(95%);
        }

        .tab button.active {
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Active state with matching colors */
        .tab button.Deworming-active {
            background-color: #bbdefb !important;
        }
        .tab button.BloodPressure-active {
            background-color: #f8bbd0 !important;
        }
        .tab button.VitaminA-active {
            background-color: #ffecb3 !important;
        }
        .tab button.Postpartum-active {
            background-color: #c8e6c9 !important;
        }
        .tab button.DispensedMedicine-active {
            background-color: #e1bee7 !important;
        }
        .tab button.Immunization-active {
            background-color: #b2ebf2 !important;
        }
        .tab button.FamilyPlanning-active {
            background-color: #ffe0b2 !important;
        }
        .tab button.MaternalCare-active {
            background-color: #d1c4e9 !important;
        }

        /* Content Container */
        #content-container {
            margin-top: 60px;
            position: relative;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-top: none;
            background-color: #fff;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            animation: fadeEffect 0.5s;
            min-height: 300px;
            transition: min-height 0.3s ease;
        }

        /* NEW: Image grid styles */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .image-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
        }
        
        .image-card img {
            width: 100%;
            height: auto;
            display: block;
            border-bottom: 1px solid #eee;
        }
        
        .image-card .card-body {
            padding: 15px;
        }
        
        .image-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.2rem;
        }
        
        .image-card p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .loading-indicator {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: #666;
        }

        @keyframes fadeEffect {
            from {opacity: 0;}
            to {opacity: 1;}
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .error {
            color: #dc3545;
            padding: 10px;
            border: 1px solid #f5c6cb;
            background-color: #f8d7da;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .image-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar a {float: left;}
            .main-content {margin-left: 0; width: 100%;}
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
    
    <!-- Main Navigation Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Navigation</div>
        <a href="homepage.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : ''; ?>">
            <i class="bi bi-house-door"></i> Home
        </a>
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="servicesrecords.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'servicesrecords.php' ? 'active' : ''; ?>">
            <i class="bi bi-clipboard-data"></i> Service Records
        </a>
    </div>
    
    <!-- Analytics Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Analytics</div>
        <a href="healthdatavisual.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'healthdatavisual.php' ? 'active' : ''; ?>">
            <i class="bi bi-bar-chart-line"></i> Data Visualizations
        </a>
        <a href="clusteringresults.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'clusteringresults.php' ? 'active' : ''; ?>">
            <i class="bi bi-diagram-3"></i> Services Clustering Results
        </a>
    </div>
    
    <!-- Account Section -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Account</div>
        <a href="account.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'account.php' ? 'active' : ''; ?>">
            <i class="bi bi-person-gear"></i> Account
        </a>
        <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">
            <i class="bi bi-info-circle"></i> About
        </a>
    </div>
    
    <!-- Footer with Logout -->
    <div class="sidebar-footer">
        <a href="logout.php" style="color: white;">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
        <div style="margin-top: 3px; color: #b8c7ce; font-size: 0.7rem;">
            &copy; <?php echo date('Y'); ?> Predictive Healthcare Data System
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Title Page Section -->
    <div class="title-page">
        <div class="title-icon">
            <i class="bi bi-diagram-3"></i>
        </div>
        <h1>Healthcare Cluster Analysis Dashboard</h1>
        <p>Interactive visualization of patient clusters across various healthcare services</p>
    </div>

    <!-- Tab Container -->
    <div class="tab-container">
        <div class="tab">
            <button class="tablinks" onclick="loadContent('dewormingresult.php', this)"><i class="bi bi-bandaid"></i> Deworming</button>
            <button class="tablinks" onclick="loadContent('bloodpressureresult.php', this)"><i class="bi bi-heart-pulse"></i> Blood Pressure</button>
            <button class="tablinks" onclick="loadContent('vitaminaresult.php', this)"><i class="bi bi-capsule"></i> Vitamin A</button>
            <button class="tablinks" onclick="loadContent('postpartumresult.php', this)"><i class="bi bi-motherboard"></i> Postpartum</button>
            <button class="tablinks" onclick="loadContent('dispensedmedicineimciresult.php', this)"><i class="bi bi-capsule-pill"></i> Dispensed Medicine</button>
            <button class="tablinks" onclick="loadContent('immunizationresult.php', this)"><i class="bi bi-shield-plus"></i> Immunization</button>
            <button class="tablinks" onclick="loadContent('familyplanningresult.php', this)"><i class="bi bi-people"></i> Family Planning</button>
            <button class="tablinks" onclick="loadContent('maternalcareresult.php', this)"><i class="bi bi-gender-female"></i> Maternal Care</button>
        </div>
    </div>
    
    <!-- Content container that will be updated via AJAX -->
    <div id="content-container" class="tabcontent">
        <!-- This will be filled with content from the individual result files -->
        <div class="loading-indicator">
            <i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite; font-size: 2rem;"></i>
            <p>Loading content...</p>
        </div>
    </div>
</div>

<script>
function loadContent(file, button) {
    // Store current content height to prevent jumps
    const contentContainer = document.getElementById('content-container');
    const currentHeight = contentContainer.offsetHeight;
    contentContainer.style.minHeight = currentHeight + 'px';
    
    // Highlight active button
    var tablinks = document.getElementsByClassName("tablinks");
    for (var i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
        // Remove all color-specific active classes
        tablinks[i].classList.remove("Deworming-active", "BloodPressure-active", "VitaminA-active", 
                                   "Postpartum-active", "DispensedMedicine-active", 
                                   "Immunization-active", "FamilyPlanning-active", "MaternalCare-active");
    }
    
    // Add active class to clicked button
    button.classList.add("active");
    
    // Add specific color class based on button type
    if (button.innerHTML.includes("Deworming")) {
        button.classList.add("Deworming-active");
    } else if (button.innerHTML.includes("Blood Pressure")) {
        button.classList.add("BloodPressure-active");
    } else if (button.innerHTML.includes("Vitamin A")) {
        button.classList.add("VitaminA-active");
    } else if (button.innerHTML.includes("Postpartum")) {
        button.classList.add("Postpartum-active");
    } else if (button.innerHTML.includes("Dispensed Medicine")) {
        button.classList.add("DispensedMedicine-active");
    } else if (button.innerHTML.includes("Immunization")) {
        button.classList.add("Immunization-active");
    } else if (button.innerHTML.includes("Family Planning")) {
        button.classList.add("FamilyPlanning-active");
    } else if (button.innerHTML.includes("Maternal Care")) {
        button.classList.add("MaternalCare-active");
    }
    
    // Show loading state
    contentContainer.innerHTML = '<div class="loading-indicator"><i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite; font-size: 2rem;"></i><p>Loading content...</p></div>';
    contentContainer.style.display = "block";
    
    // Load content via AJAX
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState == 4) {
            // Reset min-height after content loads
            contentContainer.style.minHeight = '';
            
            if (this.status == 200) {
                contentContainer.innerHTML = this.responseText;
                
                // After content loads, format images into grid
                formatImageGrid();
            } else {
                contentContainer.innerHTML = '<div class="error">Error loading content. Please try again.</div>';
            }
        }
    };
    xhr.open("GET", file, true);
    xhr.send();
}

function formatImageGrid() {
    const contentContainer = document.getElementById('content-container');
    const images = contentContainer.querySelectorAll('img');
    
    if (images.length > 0) {
        let gridHTML = '<div class="image-grid">';
        
        images.forEach((img, index) => {
            const title = img.alt || `Cluster Visualization ${index + 1}`;
            gridHTML += `
                <div class="image-card">
                    <img src="${img.src}" alt="${title}">
                    <div class="card-body">
                        <h3>${title}</h3>
                        <p>Cluster analysis visualization</p>
                    </div>
                </div>
            `;
        });
        
        gridHTML += '</div>';
        contentContainer.innerHTML = gridHTML;
    }
}

// Load first tab by default when page loads
window.onload = function() {
    document.querySelector(".tablinks").click();
};
</script>

</body>
</html>
<?php
// Database configuration
$host = 'localhost';
$dbname = 'healthdata';
$username = 'admin';
$password = 'healthdata123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables in the database
    $tablesQuery = $pdo->query("SHOW TABLES");
    $allTables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);
    
    // Define patterns for tables to exclude
    $excludePatterns = [
        '/clustering/',
        '/metrics/',
        '/results/',
        '/budget_cluster_data/',
        '/users/'
    ];
    
    // Filter tables - only keep raw data tables
    $displayTables = array_filter($allTables, function($table) use ($excludePatterns) {
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $table)) {
                return false;
            }
        }
        return true;
    });
    
    // Reset array keys
    $displayTables = array_values($displayTables);
    
    $allTableData = [];
    $serviceCounts = [
        'deworming' => 0,
        'postpartum' => 0,
        'vitamin_a' => 0,
        'imci_medicine' => 0,
        'blood_pressure' => 0,
        'immunization' => 0,
        'family_planning' => 0,
        'maternal_care' => 0,
        'total_patients' => 0
    ];

    // Map table names to services with more inclusive patterns
    $serviceTables = [
        'deworming' => '/deworming/i',
        'postpartum' => '/postpartum/i',
        'vitamin_a' => '/vitamin_a|vita/i',
        'imci_medicine' => '/imci|medicine/i',
        'blood_pressure' => '/blood_pressure|bp_monitoring/i',
        'immunization' => '/immunization|vaccine/i',
        'family_planning' => '/family_planning|fp/i',
        'maternal_care' => '/maternal_care|maternal/i'
    ];

    // Calculate counts from each table
    foreach ($displayTables as $table) {
        try {
            // First fetch all data for the table
            $query = "SELECT * FROM `$table`";
            $stmt = $pdo->query($query);
            $allTableData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $count = count($allTableData[$table]);
            
            // Check which service this table belongs to
            $matched = false;
            foreach ($serviceTables as $service => $pattern) {
                if (preg_match($pattern, $table)) {
                    $serviceCounts[$service] += $count;
                    $matched = true;
                    break;
                }
            }
            
            if ($matched) {
                $serviceCounts['total_patients'] += $count;
            }
            
        } catch (PDOException $e) {
            continue;
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Bagumbayan Health Data Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .summary-card {
            transition: transform 0.3s;
            border-radius: 5px;
            border-left: 5px solid;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card-header {
            font-weight: bold;
        }
        .tab-content {
            padding: 20px 0;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
        .patient-count {
            font-size: 1.2rem;
            font-weight: bold;
        }
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
            padding: 0px;
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
        .deworming-card {
            background-color: #e3f2fd;
            border-left-color: #2196f3 !important;
        }
        .postpartum-card {
            background-color: #e8f5e9;
            border-left-color: #4caf50 !important;
        }
        .vitamin-a-card {
            background-color: #e1f5fe;
            border-left-color: #03a9f4 !important;
        }
        .imci-card {
            background-color: #fff8e1;
            border-left-color: #ffc107 !important;
        }
        .blood-pressure-card {
            background-color: #ffebee;
            border-left-color: #f44336 !important;
        }
        .immunization-card {
            background-color: #fff3e0;
            border-left-color: #ff9800 !important;
        }
        .maternal-care-card {
            background-color: #f3e5f5;
            border-left-color: #9c27b0 !important;
        }
        .family-planning-card {
            background-color: #e0f7fa;
            border-left-color: #00bcd4 !important;
        }
        .deworming-icon {
            color: #2196f3;
        }
        .postpartum-icon {
            color: #4caf50;
        }
        .vitamin-a-icon {
            color: #03a9f4;
        }
        .imci-icon {
            color: #ffc107;
        }
        .blood-pressure-icon {
            color: #f44336;
        }
        .immunization-icon {
            color: #ff9800;
        }
        .maternal-care-icon {
            color: #9c27b0;
        }
        .family-planning-icon {
            color: #00bcd4;
        }
        .title-page {
            background-color: #007bff;
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .title-page h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .title-page p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0;
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
            <a href="homepage.php" class="<?php echo $current_page == 'homepage.php' ? 'active' : ''; ?>">
                <i class="bi bi-house-door"></i> Home
            </a>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="servicesrecords.php" class="<?php echo $current_page == 'servicesrecords.php' ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-data"></i> Service Records
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Analytics</div>
            <a href="healthdatavisual.php" class="<?php echo $current_page == 'healthdatavisual.php' ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i> Data Visualizations
            </a>
            <a href="clusteringresults.php" class="<?php echo $current_page == 'clusteringresults.php' ? 'active' : ''; ?>">
                <i class="bi bi-diagram-3"></i> Services Clustering Results
            </a>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="account.php" class="<?php echo $current_page == 'account.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i> My Account
            </a>
            <a href="about.php" class="<?php echo $current_page == 'about.php' ? 'active' : ''; ?>">
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
        <div class="container-fluid py-4">
            <!-- Title Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="title-page">
                        <h1><i class="bi bi-speedometer2"></i> Barangay Bagumbayan Health Data Dashboard</h1>
                        <p class="mb-0">Comprehensive overview of community health services</p>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-people-fill"></i> Total Patients Served: 
                        <span class="patient-count"><?php echo number_format($serviceCounts['total_patients']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card summary-card deworming-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Deworming</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['deworming']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon deworming-icon">
                                    <i class="bi bi-capsule"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card summary-card postpartum-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Postpartum</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['postpartum']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon postpartum-icon">
                                    <i class="bi bi-heart-pulse"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card summary-card vitamin-a-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Vitamin A</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['vitamin_a']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon vitamin-a-icon">
                                    <i class="bi bi-droplet"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card summary-card imci-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">IMCI Medicine</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['imci_medicine']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon imci-icon">
                                    <i class="bi bi-prescription"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card summary-card blood-pressure-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Blood Pressure</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['blood_pressure']); ?></h2>
                                    <small>Patients screened</small>
                                </div>
                                <div class="card-icon blood-pressure-icon">
                                    <i class="bi bi-speedometer2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card summary-card immunization-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Immunization</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['immunization']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon immunization-icon">
                                    <i class="bi bi-shield-plus"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card summary-card maternal-care-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Maternal Care</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['maternal_care']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon maternal-care-icon">
                                    <i class="bi bi-motherboard"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card summary-card family-planning-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Family Planning</h5>
                                    <h2 class="mb-0"><?php echo number_format($serviceCounts['family_planning']); ?></h2>
                                    <small>Patients served</small>
                                </div>
                                <div class="card-icon family-planning-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Tables Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-table"></i> Service Data Tables
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="dataTabs" role="tablist">
                                <?php foreach ($displayTables as $index => $table): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                            id="<?php echo htmlspecialchars($table); ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?php echo htmlspecialchars($table); ?>" 
                                            type="button" 
                                            role="tab">
                                        <?php echo ucwords(str_replace('_', ' ', $table)); ?>
                                        <span class="badge bg-primary ms-1">
                                            <?php echo count($allTableData[$table]); ?>
                                        </span>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" id="dataTabsContent">
                                <?php foreach ($displayTables as $index => $table): ?>
                                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                     id="<?php echo htmlspecialchars($table); ?>" 
                                     role="tabpanel">
                                    <div class="table-responsive mt-3">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <?php if (!empty($allTableData[$table])): ?>
                                                        <?php foreach (array_keys($allTableData[$table][0]) as $column): ?>
                                                            <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($allTableData[$table] as $row): ?>
                                                    <tr>
                                                        <?php foreach ($row as $value): ?>
                                                            <td><?php echo htmlspecialchars($value); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
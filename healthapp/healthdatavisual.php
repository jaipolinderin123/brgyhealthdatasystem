<?php
// Database connection and functions
$host = 'localhost';
$dbname = 'healthdata';
$username = 'admin';
$password = 'healthdata123';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function getTableNames($conn) {
    $stmt = $conn->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Tables to exclude (clustering results, metrics, and user info)
    $excludedTables = [
        'clustering_results', 'metrics', 'users', 
        'user_data', 'analysis_metrics', 'cluster_metrics',
        'user_logs', 'sensitive_data', 'internal_metrics'
    ];
    
    // Filter out excluded tables (case insensitive)
    return array_filter($allTables, function($table) use ($excludedTables) {
        return !in_array(strtolower($table), array_map('strtolower', $excludedTables));
    });
}

function getColumnNames($conn, $tableName) {
    $stmt = $conn->prepare("DESCRIBE `$tableName`");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getAvailableYears($conn, $tableName) {
    try {
        // Find date columns
        $stmt = $conn->prepare("DESCRIBE `$tableName`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $dateColumns = [];
        $dateColumnTypes = ['date', 'datetime', 'timestamp'];
        foreach ($columns as $col) {
            $stmt = $conn->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$tableName, $col]);
            $type = $stmt->fetchColumn();
            
            if (in_array(strtolower($type), $dateColumnTypes)) {
                $dateColumns[] = $col;
            }
        }
        
        if (empty($dateColumns)) {
            // If no date columns, return current year and previous 2 years
            $stmt = $conn->query("SELECT DISTINCT YEAR(CURDATE()) as year 
                                 UNION SELECT DISTINCT YEAR(CURDATE()) - 1 as year
                                 UNION SELECT DISTINCT YEAR(CURDATE()) - 2 as year
                                 ORDER BY year DESC");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Get distinct years from all date columns
        $years = [];
        foreach ($dateColumns as $col) {
            $stmt = $conn->query("SELECT DISTINCT YEAR(`$col`) as year FROM `$tableName` 
                                WHERE `$col` IS NOT NULL ORDER BY year DESC");
            $columnYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $years = array_merge($years, $columnYears);
        }
        
        // Remove duplicates and sort
        $years = array_unique($years);
        rsort($years);
        
        return array_values($years);
    } catch(PDOException $e) {
        // Fallback if there's an error
        $stmt = $conn->query("SELECT DISTINCT YEAR(CURDATE()) as year 
                             UNION SELECT DISTINCT YEAR(CURDATE()) - 1 as year
                             UNION SELECT DISTINCT YEAR(CURDATE()) - 2 as year
                             ORDER BY year DESC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

function getAvailableMonths() {
    return [
        1 => 'January', 2 => 'February', 3 => 'March', 
        4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December'
    ];
}

// Handle API request for chart data
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_data') {
        $table = $_GET['table'] ?? '';
        $column = $_GET['column'] ?? '';
        $year = $_GET['year'] ?? '';
        $month = $_GET['month'] ?? '';
        
        if (empty($table) || empty($column)) {
            die(json_encode(['error' => 'Table and column parameters are required']));
        }
        
        // Validate table and column names to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            die(json_encode(['error' => 'Invalid table name']));
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            die(json_encode(['error' => 'Invalid column name']));
        }
        
        try {
            // Get table columns to check for date field
            $stmt = $conn->prepare("DESCRIBE `$table`");
            $stmt->execute();
            $tableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Find all date columns in the table
            $dateColumns = [];
            $dateColumnTypes = ['date', 'datetime', 'timestamp'];
            foreach ($tableColumns as $col) {
                $stmt = $conn->prepare("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                                      WHERE TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$table, $col]);
                $type = $stmt->fetchColumn();
                
                if (in_array(strtolower($type), $dateColumnTypes)) {
                    $dateColumns[] = $col;
                }
            }
            
            $query = "SELECT `$column`, COUNT(*) as count FROM `$table`";
            $whereClauses = [];
            $params = [];
            
            // Add date filtering if we have date columns and year/month filters
            if ((!empty($year) || !empty($month)) && !empty($dateColumns)) {
                $dateConditions = [];
                
                foreach ($dateColumns as $dateCol) {
                    $colConditions = [];
                    
                    if (!empty($year)) {
                        $colConditions[] = "YEAR(`$dateCol`) = ?";
                        $params[] = $year;
                    }
                    
                    if (!empty($month)) {
                        $colConditions[] = "MONTH(`$dateCol`) = ?";
                        $params[] = $month;
                    }
                    
                    if (!empty($colConditions)) {
                        $dateConditions[] = "(" . implode(" AND ", $colConditions) . ")";
                    }
                }
                
                if (!empty($dateConditions)) {
                    $whereClauses[] = "(" . implode(" OR ", $dateConditions) . ")";
                }
            }
            
            // Build WHERE clause if we have conditions
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $query .= " GROUP BY `$column` ORDER BY count DESC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($data);
            exit;
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    if ($_GET['action'] === 'columns') {
        $table = $_GET['table'] ?? '';
        
        if (empty($table)) {
            die(json_encode(['error' => 'Table parameter is required']));
        }
        
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            die(json_encode(['error' => 'Invalid table name']));
        }
        
        try {
            $columns = getColumnNames($conn, $table);
            echo json_encode($columns);
            exit;
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    if ($_GET['action'] === 'available_years') {
        $table = $_GET['table'] ?? '';
        
        if (empty($table)) {
            die(json_encode(['error' => 'Table parameter is required']));
        }
        
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            die(json_encode(['error' => 'Invalid table name']));
        }
        
        try {
            $years = getAvailableYears($conn, $table);
            echo json_encode($years);
            exit;
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
}

$tables = getTableNames($conn);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Data Visualization Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Controls Section */
        .controls {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .control-group {
            margin-bottom: 15px;
        }
        
        .control-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .control-group select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .filter-section-title {
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        #generate-chart {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        #generate-chart:hover {
            background-color: #218838;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Chart Container */
        .chart-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            height: 500px;
        }
        
        /* Data Table Section */
        .data-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .data-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        #dataTable {
            width: 100%;
            border-collapse: collapse;
        }
        
        #dataTable th {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: left;
        }
        
        #dataTable td {
            padding: 8px 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        #dataTable tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar a {float: left;}
            .main-content {margin-left: 0; width: 100%;}
            .sidebar-footer {position: relative;}
            
            .chart-container {
                height: 400px;
            }
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

<!-- Main Content -->
<div class="main-content">
    <!-- Title Page Section -->
    <div class="title-page">
        <div class="title-icon">
            <i class="bi bi-bar-chart-line"></i>
        </div>
        <h1>Health Data Visualization Dashboard</h1>
        <p>Interactive visualizations of healthcare service data with filtering capabilities</p>
    </div>
    
    <!-- Controls Section -->
    <div class="controls">
        <div class="control-row">
            <div class="control-group">
                <label for="table-select"><i class="bi bi-table"></i> Select Table</label>
                <select id="table-select" class="form-select">
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= htmlspecialchars($table) ?>"><?= htmlspecialchars($table) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="control-group">
                <label for="column-select"><i class="bi bi-columns"></i> Select Column</label>
                <select id="column-select" class="form-select">
                    <!-- Columns will be populated via JavaScript -->
                </select>
            </div>
            
            <div class="control-group">
                <label for="chart-type-select"><i class="bi bi-graph-up"></i> Chart Type</label>
                <select id="chart-type-select" class="form-select">
                    <option value="bar">Bar Chart</option>
                    <option value="pie">Pie Chart</option>
                    <option value="doughnut">Doughnut Chart</option>
                    <option value="line">Line Chart</option>
                    <option value="polarArea">Polar Area Chart</option>
                    <option value="radar">Radar Chart</option>
                </select>
            </div>
        </div>
        
        <div class="filter-section">
            <div class="filter-section-title">
                <i class="bi bi-funnel"></i> Filter Options
            </div>
            <div class="filter-controls">
                <div class="filter-group">
                    <label for="year-select"><i class="bi bi-calendar"></i> Year</label>
                    <select id="year-select" class="form-select">
                        <option value="">All Years</option>
                        <!-- Years will be populated dynamically -->
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="month-select"><i class="bi bi-calendar-month"></i> Month</label>
                    <select id="month-select" class="form-select">
                        <option value="">All Months</option>
                        <!-- Months will be populated dynamically -->
                    </select>
                </div>
            </div>
        </div>
        
        <button id="generate-chart">
            <i class="bi bi-lightning-charge"></i> <span id="generate-text">Generate Visualization</span>
            <span id="loading-indicator" class="loading" style="display: none;"></span>
        </button>
    </div>
    
    <!-- Chart Container -->
    <div class="chart-container">
        <div class="chart-wrapper" style="height: 100%;">
            <canvas id="chartCanvas"></canvas>
        </div>
    </div>
    
    <!-- Data Table Section -->
    <div class="data-section">
        <h3><i class="bi bi-table"></i> Raw Data</h3>
        <div class="table-responsive">
            <table id="dataTable" class="table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <!-- Data will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableSelect = document.getElementById('table-select');
    const columnSelect = document.getElementById('column-select');
    const chartTypeSelect = document.getElementById('chart-type-select');
    const yearSelect = document.getElementById('year-select');
    const monthSelect = document.getElementById('month-select');
    const generateBtn = document.getElementById('generate-chart');
    const chartCanvas = document.getElementById('chartCanvas');
    const dataTableBody = document.getElementById('dataTableBody');
    const loadingIndicator = document.getElementById('loading-indicator');
    const generateText = document.getElementById('generate-text');
    
    let chartInstance = null;
    
    // Load months (static list)
    loadAvailableMonths();
    
    // Load columns when table is selected
    tableSelect.addEventListener('change', function() {
        const table = this.value;
        loadColumns(table);
        loadAvailableYears(table);
    });
    
    // Generate chart when button is clicked
    generateBtn.addEventListener('click', function() {
        const table = tableSelect.value;
        const column = columnSelect.value;
        const chartType = chartTypeSelect.value;
        const year = yearSelect.value;
        const month = monthSelect.value;
        
        if (!table || !column) {
            alert('Please select both a table and a column');
            return;
        }
        
        // Show loading indicator
        generateText.textContent = 'Loading...';
        loadingIndicator.style.display = 'inline-block';
        
        fetchChartData(table, column, chartType, year, month);
    });
    
    // Load initial columns and years for the first table
    if (tableSelect.options.length > 0) {
        const initialTable = tableSelect.value;
        loadColumns(initialTable);
        loadAvailableYears(initialTable);
    }
    
    function loadAvailableYears(tableName) {
        fetch(`?action=available_years&table=${tableName}`)
            .then(response => response.json())
            .then(years => {
                yearSelect.innerHTML = '<option value="">All Years</option>';
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading available years:', error);
                alert('Error loading available years: ' + error.message);
            });
    }
    
    function loadAvailableMonths() {
        const months = {
            1: 'January', 2: 'February', 3: 'March', 
            4: 'April', 5: 'May', 6: 'June',
            7: 'July', 8: 'August', 9: 'September',
            10: 'October', 11: 'November', 12: 'December'
        };
        
        monthSelect.innerHTML = '<option value="">All Months</option>';
        for (const [value, name] of Object.entries(months)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = name;
            monthSelect.appendChild(option);
        }
    }
    
    function loadColumns(tableName) {
        fetch(`?action=columns&table=${tableName}`)
            .then(response => response.json())
            .then(columns => {
                columnSelect.innerHTML = '';
                columns.forEach(column => {
                    const option = document.createElement('option');
                    option.value = column;
                    option.textContent = column;
                    columnSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error loading columns:', error);
                alert('Error loading columns: ' + error.message);
            });
    }
    
    function fetchChartData(table, column, chartType, year, month) {
        let url = `?action=get_data&table=${table}&column=${column}`;
        
        if (year) url += `&year=${year}`;
        if (month) url += `&month=${month}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                if (data.length === 0) {
                    alert('No data found for the selected filters');
                    return;
                }
                
                // Calculate total for percentages
                const total = data.reduce((sum, item) => sum + item.count, 0);
                
                // Prepare chart data
                const labels = data.map(item => item[column] || 'Unknown');
                const values = data.map(item => item.count);
                const percentages = data.map(item => ((item.count / total) * 100).toFixed(1) + '%');
                
                // Render the chart
                renderChart(labels, values, chartType, table, column, year, month);
                renderDataTable(data, column, percentages);
            })
            .catch(error => {
                console.error('Error fetching chart data:', error);
                alert('Error loading chart data: ' + error.message);
            })
            .finally(() => {
                // Hide loading indicator
                generateText.textContent = 'Generate Visualization';
                loadingIndicator.style.display = 'none';
            });
    }
    
    function renderChart(labels, values, chartType, table, column, year, month) {
        // Destroy previous chart instance if exists
        if (chartInstance) {
            chartInstance.destroy();
        }
        
        const ctx = chartCanvas.getContext('2d');
        
        // Generate title based on filters
        let title = `${table} - ${column} Distribution`;
        if (year || month) {
            title += ' (';
            if (year) title += `Year: ${year}`;
            if (year && month) title += ', ';
            if (month) title += `Month: ${getMonthName(month)}`;
            title += ')';
        }
        
        // Chart configuration
        const config = {
            type: chartType,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Count',
                    data: values,
                    backgroundColor: generateChartColors(values.length),
                    borderColor: '#ffffff',
                    borderWidth: chartType === 'bar' || chartType === 'line' ? 1 : 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: title,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: chartType === 'bar' || chartType === 'line' ? {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: column
                        }
                    }
                } : {}
            }
        };
        
        // Create new chart
        chartInstance = new Chart(ctx, config);
    }
    
    function renderDataTable(data, column, percentages) {
        dataTableBody.innerHTML = '';
        
        data.forEach((item, index) => {
            const row = document.createElement('tr');
            
            const categoryCell = document.createElement('td');
            categoryCell.textContent = item[column] || 'Unknown';
            row.appendChild(categoryCell);
            
            const countCell = document.createElement('td');
            countCell.textContent = item.count;
            row.appendChild(countCell);
            
            const percentageCell = document.createElement('td');
            percentageCell.textContent = percentages[index];
            row.appendChild(percentageCell);
            
            dataTableBody.appendChild(row);
        });
    }
    
    function generateChartColors(count) {
        const colors = [];
        const hueStep = 360 / count;
        
        for (let i = 0; i < count; i++) {
            const hue = i * hueStep;
            colors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
        }
        
        return colors;
    }
    
    function getMonthName(monthNumber) {
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        return months[parseInt(monthNumber) - 1] || '';
    }
});
</script>
</body>
</html>
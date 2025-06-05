<?php
// Start session for message handling
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'healthdata';
$username = 'admin';
$password = 'healthdata123';

// Table names including the new maternal care and family planning tables
$tables = ['blood_pressure_monitoring', 'deworming', 'postpartum', 'vitamin_a', 'dispensed_medicine_imci', 'immunization', 'maternal_care', 'family_planning'];

try {
    // Database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Handle form submissions
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['table_name'])) {
        $table = $_POST['table_name'];
        unset($_POST['table_name']); // Remove from data

        // Data validation and sanitization
        $data = array_map(function($value) {
            return is_string($value) ? trim($value) : $value;
        }, $_POST);

        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        try {
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            foreach ($data as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            
            $pdo->commit();
            $_SESSION['success_message'] = "New record added to <strong>" . ucwords(str_replace('_', ' ', $table)) . "</strong>.";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Error adding record: " . $e->getMessage();
        }
    }

    // Check and create maternal_care table if missing
    try {
        $pdo->query("SELECT 1 FROM `maternal_care` LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE `maternal_care` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `registration_date` VARCHAR(10),
            `purok` INT(1),
            `age` INT(2),
            `lmp` VARCHAR(10),
            `edc` VARCHAR(10),
            `pre_natal_date_1st_tri` VARCHAR(10),
            `pre_natal_2nd_tri` VARCHAR(10),
            `pre_natal_3rd_tri` VARCHAR(10),
            `pre_natal_3rd_tri_repeat` VARCHAR(10),
            `validation_for_delivery` VARCHAR(10),
            `td_tt_1` VARCHAR(10),
            `td_tt_2` VARCHAR(10),
            `td_tt_3` VARCHAR(10),
            `iron_no_tablets_1st_tri` VARCHAR(4),
            `iron_no_tablets_2nd_tri` VARCHAR(4),
            `iron_no_tablets_3rd_tri` INT(2),
            `iron_no_tablets_3rd_tri_repeat` INT(2),
            `iron_date_given_1st_tri` VARCHAR(10),
            `iron_date_given_2nd_tri` VARCHAR(10),
            `iron_date_given_3rd_tri` VARCHAR(10),
            `iron_date_given_3rd_tri_repeat` VARCHAR(10),
            `calcium_no_tablets_1st_tri` VARCHAR(4),
            `calcium_no_tablets_2nd_tri` VARCHAR(4),
            `calcium_no_tablets_3rd_tri` VARCHAR(4),
            `calcium_date_given_1st_tri` VARCHAR(10),
            `calcium_date_given_2nd_tri` VARCHAR(10),
            `calcium_date_given_3rd_tri` VARCHAR(10),
            `iodine_capsule_given` VARCHAR(4),
            `bmi` DECIMAL(4,2),
            `deworming_tablet_given` VARCHAR(4),
            `syphilis_screening_date` VARCHAR(8),
            `syphilis_result` VARCHAR(10),
            `hepatitis_b_date_screened` VARCHAR(10),
            `hepatitis_b_result` VARCHAR(8),
            `hiv_screening_date` VARCHAR(10),
            `gestational_diabetes_date` VARCHAR(10),
            `gestational_diabetes_result` VARCHAR(8),
            `cbc_date_screened` VARCHAR(10),
            `cbc_result` VARCHAR(10),
            `given_iron` VARCHAR(4),
            `date_terminated_of_pregnancy` VARCHAR(10),
            `pregnancy_outcome` VARCHAR(9),
            `gender` VARCHAR(6),
            `type_of_delivery` VARCHAR(16),
            `birth_weight` VARCHAR(4),
            `place_of_delivery` VARCHAR(8),
            `bemonc_capable` VARCHAR(4),
            `ownership` VARCHAR(7),
            `birth_attendant` VARCHAR(14),
            `date_of_delivery` VARCHAR(10),
            `postpartum_checkup_24hrs` VARCHAR(10),
            `postpartum_checkup_7days` VARCHAR(10),
            `iron_tablets_postpartum_1st_month` VARCHAR(4),
            `iron_tablets_postpartum_2nd_month` VARCHAR(4),
            `iron_tablets_postpartum_3rd_month` VARCHAR(4),
            `iron_date_given_1st_month` VARCHAR(10),
            `iron_date_given_2nd_month` VARCHAR(10),
            `iron_date_given_3rd_month` VARCHAR(10),
            `completed_90_tablets_iron` VARCHAR(10),
            `vitamin_a_given` VARCHAR(10)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Check and create family_planning table if missing
    try {
        $pdo->query("SELECT 1 FROM `family_planning` LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("CREATE TABLE `family_planning` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `date_of_registration` VARCHAR(10),
            `purok` INT(1),
            `age` INT(2),
            `birthday` VARCHAR(10),
            `se_status` VARCHAR(8),
            `type_of_client` VARCHAR(15),
            `source` VARCHAR(9),
            `previous_method` VARCHAR(39)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // HTML Output
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Barangay Bagumbayan Health Services Data</title>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css' rel='stylesheet'>
        <style>
            :root {
                --primary-color: #3498db;
                --secondary-color: #2980b9;
                --success-color: #2ecc71;
                --danger-color: #e74c3c;
                --light-color: #ecf0f1;
                --dark-color: #2c3e50;
                --border-radius: 5px;
                --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                --transition: all 0.3s ease;
            }
            
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background-color: #f5f7fa;
                color: #333;
                line-height: 1.6;
                display: flex;
                min-height: 100vh;
            }

            /* Sidebar Styles */
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
                padding: 0px;
                background-color: #2c3e50;
                text-align: center;
                font-size: 0.8rem;
            }
            .main-content {
                margin-left: 250px;
                padding: 20px;
                width: calc(100% - 250px);
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

            /* Rest of your existing styles... */
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 15px;
            }
            
            h1 {
                color: var(--dark-color);
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 2px solid var(--primary-color);
            }
            
            h2 {
                color: var(--dark-color);
                margin: 30px 0 15px;
                padding-bottom: 10px;
                border-bottom: 1px solid var(--primary-color);
            }
            
            .table-container {
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-bottom: 25px;
                overflow: hidden;
                transition: var(--transition);
            }
            
            .table-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 20px;
                background-color: var(--primary-color);
                color: white;
                cursor: pointer;
                transition: var(--transition);
            }
            
            .table-header:hover {
                background-color: var(--secondary-color);
            }
            
            .toggle-btn {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
                padding: 0 10px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
            }
            
            th, td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            
            th {
                background-color: var(--light-color);
                font-weight: 600;
            }
            
            tr:hover {
                background-color: rgba(52, 152, 219, 0.1);
            }
            
            .add-record-btn {
                background-color: var(--success-color);
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: var(--border-radius);
                cursor: pointer;
                margin: 15px;
                transition: var(--transition);
                display: inline-block;
            }
            
            .add-record-btn:hover {
                background-color: #27ae60;
                transform: translateY(-2px);
            }
            
            .print-record-btn {
                background-color: #9b59b6;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: var(--border-radius);
                cursor: pointer;
                margin: 15px;
                transition: var(--transition);
                display: inline-block;
            }
            
            .print-record-btn:hover {
                background-color: #8e44ad;
                transform: translateY(-2px);
            }
            
            .add-form {
                display: none;
                padding: 20px;
                background-color: #f9f9f9;
                border-top: 1px solid #eee;
            }
            
            .form-row {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .form-group {
                flex: 1 1 calc(33.333% - 20px);
                min-width: 250px;
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: var(--dark-color);
            }
            
            input, select, textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: var(--border-radius);
                font-size: 16px;
                transition: var(--transition);
            }
            
            input:focus, select:focus, textarea:focus {
                border-color: var(--primary-color);
                outline: none;
                box-shadow: 0 0 0 2px rgba(85, 140, 177, 0.2);
            }
            
            .form-buttons {
                display: flex;
                gap: 15px;
                justify-content: flex-end;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
            
            input[type='submit'] {
                background-color: var(--primary-color);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: var(--border-radius);
                cursor: pointer;
                transition: var(--transition);
            }
            
            input[type='submit']:hover {
                background-color: var(--secondary-color);
            }
            
            .clear-btn {
                background-color: #95a5a6;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: var(--border-radius);
                cursor: pointer;
                transition: var(--transition);
            }
            
            .clear-btn:hover {
                background-color: #7f8c8d;
            }
            
            .success-message {
                background-color: var(--success-color);
                color: white;
                padding: 15px;
                border-radius: var(--border-radius);
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .error-message {
                background-color: var(--danger-color);
                color: white;
                padding: 15px;
                border-radius: var(--border-radius);
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .no-records {
                padding: 20px;
                text-align: center;
                color: #7f8c8d;
                font-style: italic;
            }
            
            /* Print-specific styles */
            @media print {
                body * {
                    visibility: hidden;
                }
                .print-section, .print-section * {
                    visibility: visible;
                }
                .print-section {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }
                .no-print {
                    display: none !important;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                }
                th {
                    background-color: #f2f2f2;
                }
            }
            
            @media (max-width: 768px) {
                .form-group {
                    flex: 1 1 100%;
                }
                
                .form-buttons {
                    flex-direction: column;
                }
                
                input[type='submit'], .clear-btn {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>

    <!-- Updated Sidebar Navigation -->
    <div class='sidebar'>
        <div class='sidebar-header'>
            <img src='/healthapp/images/image_clean_no_bg.png' alt='Healthcare Logo' style='height: 150px; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto;'>
            <i class=></i> Predictive Healthcare Data System
        </div>
        
        <div class='sidebar-section'>
            <div class='sidebar-section-title'>Navigation</div>
            <a href='homepage.php' class='".(basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'active' : '')."'>
                <i class='bi bi-house-door'></i> Home
            </a>
            <a href='dashboard.php' class='".(basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '')."'>
                <i class='bi bi-speedometer2'></i> Dashboard
            </a>
            <a href='servicesrecords.php' class='".(basename($_SERVER['PHP_SELF']) == 'servicesrecords.php' ? 'active' : '')."'>
                <i class='bi bi-clipboard-data'></i> Service Records
            </a>
        </div>
        
        <div class='sidebar-section'>
            <div class='sidebar-section-title'>Analytics</div>
            <a href='healthdatavisual.php' class='".(basename($_SERVER['PHP_SELF']) == 'healthdatavisual.php' ? 'active' : '')."'>
                <i class='bi bi-bar-chart-line'></i> Data Visualizations
            </a>
            <a href='clusteringresults.php' class='".(basename($_SERVER['PHP_SELF']) == 'clusteringresults.php' ? 'active' : '')."'>
                <i class='bi bi-diagram-3'></i> Services Clustering Results
            </a>
            <a href='http://localhost/healthapp/healthbudgetanalysis/healthanalysishandler.php' class='".(basename($_SERVER['PHP_SELF']) == 'http://localhost/healthapp/healthbudgetanalysis/healthanalysishandler.php' ? 'active' : '')."'>
                <i class='bi bi-cpu'></i> Predictive Models
            </a>
        </div>
        
        <div class='sidebar-section'>
            <div class='sidebar-section-title'>Account</div>
            <a href='account.php' class='".(basename($_SERVER['PHP_SELF']) == 'account.php' ? 'active' : '')."'>
                <i class='bi bi-person-gear'></i> My Account
            </a>
            <a href='about.php' class='".(basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : '')."'>
                <i class='bi bi-info-circle'></i> About
            </a>
        </div>
        
        <div class='sidebar-footer'>
            <a href='logout.php' style='color: white;'>
                <i class='bi bi-box-arrow-left'></i> Logout
            </a>
            <div style='margin-top: 3px; color: #b8c7ce; font-size: 0.7rem;'>
                &copy; " . date('Y') . " Predictive Healthcare Data System
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class='main-content'>
        <div class='container'>
            <h1><i class='fas fa-heartbeat'></i> Barangay Bagumbayan Health Services Data</h1>";
            
            // Display success/error messages
            if (!empty($_SESSION['success_message'])) {
                echo "<div class='success-message'><i class='fas fa-check-circle'></i> " . $_SESSION['success_message'] . "</div>";
                unset($_SESSION['success_message']);
            }
            
            if (!empty($_SESSION['error_message'])) {
                echo "<div class='error-message'><i class='fas fa-exclamation-circle'></i> " . $_SESSION['error_message'] . "</div>";
                unset($_SESSION['error_message']);
            }

            // Group tables by category
            $categories = [
                'Maternal and Child Health' => ['maternal_care', 'postpartum', 'immunization'],
                'Family Planning' => ['family_planning'],
                'General Health Services' => ['blood_pressure_monitoring', 'deworming', 'vitamin_a', 'dispensed_medicine_imci']
            ];

            foreach ($categories as $category => $tablesInCategory) {
                echo "<h2>$category</h2>";
                
                foreach ($tablesInCategory as $table) {
                    $displayName = ucwords(str_replace('_', ' ', $table));
                    
                    echo "<div class='table-container' id='{$table}-container'>
                            <div class='table-header' onclick='toggleTable(\"$table\")'>
                                <h2><i class='fas fa-table'></i> $displayName</h2>
                                <button class='toggle-btn' id='{$table}-toggle'>−</button>
                            </div>";

                    try {
                        $stmt = $pdo->query("SELECT * FROM `$table`");
                        $rows = $stmt->fetchAll();
                        
                        if (!empty($rows)) {
                            $columns = array_keys($rows[0]);
                            echo "<div id='{$table}-table'>
                                    <div style='overflow-x: auto;'>
                                        <div id='{$table}-print-section' class='print-section'>
                                            <h3 style='text-align: center; margin-bottom: 20px;'>$displayName Records</h3>
                                            <p style='text-align: right; margin-bottom: 10px;'>Printed on: " . date('Y-m-d H:i:s') . "</p>
                                            <table>
                                                <thead><tr>";
                            foreach ($columns as $col) {
                                echo "<th>" . htmlspecialchars(str_replace('_', ' ', $col)) . "</th>";
                            }
                            echo "</tr></thead><tbody>";
                            
                            foreach ($rows as $row) {
                                echo "<tr>";
                                foreach ($row as $value) {
                                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</tbody></table>
                                        </div>"; // Close print-section
                        } else {
                            echo "<div id='{$table}-table'><div class='no-records'><i class='fas fa-info-circle'></i> No records found in this table.</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<div id='{$table}-table'><div class='error-message'><i class='fas fa-exclamation-triangle'></i> Error loading table: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                    
                    echo "<div class='no-print'>
                            <button class='add-record-btn' onclick='toggleForm(\"$table\")'><i class='fas fa-plus'></i> Add New Record</button>
                            <button class='print-record-btn' onclick='printTable(\"$table\")'><i class='fas fa-print'></i> Print Records</button>
                          </div>"; // Close no-print div
                          
                    echo "</div>"; // Close table div

                    // Display form
                    echo "<div class='add-form' id='{$table}-form'>
                            <h3><i class='fas fa-plus-circle'></i> Add New $displayName Record</h3>
                            <form method='POST' id='{$table}-form-element'>
                                <input type='hidden' name='table_name' value='$table'>";

                    try {
                        $desc = $pdo->query("DESCRIBE `$table`")->fetchAll();
                        echo "<div class='form-row'>";
                        foreach ($desc as $column) {
                            $field = $column['Field'];
                            $type = $column['Type'];

                            // Skip auto-increment primary keys
                            if (strpos($column['Extra'], 'auto_increment') !== false) continue;

                            echo "<div class='form-group'>
                                    <label for='$field'><i class='fas fa-tag'></i> " . ucwords(str_replace('_', ' ', $field)) . ":</label>";
                            
                            $inputName = str_replace(' ', '_', $field);
                            
                            if (str_contains($type, 'text')) {
                                echo "<textarea name='$inputName' id='$field' rows='3'></textarea>";
                            } elseif ($field === 'date' || $field === 'birthday' || $field === 'date_of_immunization' || 
                                     $field === 'date_of_consultation' || $field === 'next_session' || 
                                     $field === 'registration_date' || $field === 'lmp' || $field === 'edc' ||
                                     $field === 'date_of_registration' || $field === 'date_of_delivery' ||
                                     $field === 'pre_natal_date_1st_tri' || $field === 'pre_natal_2nd_tri' ||
                                     $field === 'pre_natal_3rd_tri' || $field === 'pre_natal_3rd_tri_repeat' ||
                                     $field === 'date_terminated_of_pregnancy' || $field === 'postpartum_checkup_24hrs' ||
                                     $field === 'postpartum_checkup_7days') {
                                echo "<input type='date' name='$inputName' id='$field' class='date-input'>";
                            } elseif ($field === 'gender') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Gender</option>
                                    <option value='Male'>Male</option>
                                    <option value='Female'>Female</option>
                                </select>";
                            } elseif ($field === 'se_status') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Status</option>
                                    <option value='NTHS'>NTHS</option>
                                    <option value='Non-NTHS'>Non-NTHS</option>
                                </select>";
                            } elseif ($field === 'type_of_client') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Client Type</option>
                                    <option value='New Acceptor'>New Acceptor</option>
                                    <option value='Current User'>Current User</option>
                                    <option value='Changing Clinic'>Changing Clinic</option>
                                    <option value='Changing Method'>Changing Method</option>
                                    <option value='Restart'>Restart</option>
                                    <option value='Other Acceptor'>Other Acceptor</option>
                                </select>";
                            } elseif ($field === 'pregnancy_outcome') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Outcome</option>
                                    <option value='Full Term'>Full Term</option>
                                    <option value='Preterm'>Preterm</option>
                                    <option value='Stillbirth'>Stillbirth</option>
                                    <option value='Miscarriage'>Miscarriage</option>
                                </select>";
                            } elseif ($field === 'type_of_delivery') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Delivery Type</option>
                                    <option value='Normal/Vaginal'>Normal/Vaginal</option>
                                    <option value='CS'>CS</option>
                                    <option value='Assisted'>Assisted</option>
                                </select>";
                            } elseif ($field === 'purok') {
                                echo "<select name='$inputName' id='$field'>
                                    <option value=''>Select Purok</option>";
                                for ($i = 1; $i <= 10; $i++) {
                                    echo "<option value='$i'>$i</option>";
                                }
                                echo "</select>";
                            } elseif (str_contains($type, 'int') || str_contains($type, 'decimal') || 
                                     $field === 'age_in_months' || $field === 'weight' || $field === 'age' || 
                                     $field === 'bmi' || $field === 'iron_no_tablets_3rd_tri' || 
                                     $field === 'iron_no_tablets_3rd_tri_repeat') {
                                echo "<input type='number' name='$inputName' id='$field' step='" . (str_contains($type, 'decimal') ? "0.01" : "1" . "'>");
                            } else {
                                echo "<input type='text' name='$inputName' id='$field'>";
                            }
                            echo "</div>";
                        }
                        echo "</div>";
                    } catch (PDOException $e) {
                        echo "<div class='error-message'><i class='fas fa-exclamation-triangle'></i> Error loading form: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }

                    echo "<div class='form-buttons'>
                            <button type='submit' class='submit-btn'><i class='fas fa-save'></i> Submit Record</button>
                            <button type='button' class='clear-btn' onclick='clearForm(\"{$table}-form-element\")'><i class='fas fa-broom'></i> Clear Form</button>
                          </div>
                          </form>
                          </div>"; // Close add-form
                          
                    echo "</div>"; // Close table-container
                }
            }

            echo "</div>"; // Close container

            echo "<script>
                // Initialize all tables as visible
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.table-container').forEach(container => {
                        const tableName = container.id.replace('-container', '');
                        const table = document.getElementById(tableName + '-table');
                        const form = document.getElementById(tableName + '-form');
                        const toggleBtn = document.getElementById(tableName + '-toggle');
                        
                        table.style.display = 'block';
                        form.style.display = 'none';
                        toggleBtn.textContent = '−';
                    });
                    
                    // Set current date for date fields
                    const today = new Date().toISOString().split('T')[0];
                    document.querySelectorAll('.date-input').forEach(input => {
                        if (!input.value) {
                            input.value = today;
                        }
                    });
                });
                
                function toggleTable(tableName) {
                    const table = document.getElementById(tableName + '-table');
                    const form = document.getElementById(tableName + '-form');
                    const toggleBtn = document.getElementById(tableName + '-toggle');
                    
                    if (table.style.display === 'none') {
                        table.style.display = 'block';
                        form.style.display = 'none';
                        toggleBtn.textContent = '−';
                    } else {
                        table.style.display = 'none';
                        form.style.display = 'none';
                        toggleBtn.textContent = '+';
                    }
                }
                
                function toggleForm(tableName) {
                    const form = document.getElementById(tableName + '-form');
                    if (form.style.display === 'block') {
                        form.style.display = 'none';
                    } else {
                        document.querySelectorAll('.add-form').forEach(f => {
                            f.style.display = 'none';
                        });
                        form.style.display = 'block';
                        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
                
                function clearForm(formId) {
                    const form = document.getElementById(formId);
                    const inputs = form.querySelectorAll('input:not([type=\"hidden\"]), textarea, select');
                    inputs.forEach(input => {
                        if (input.type === 'select-one') {
                            input.selectedIndex = 0;
                        } else if (input.classList.contains('date-input')) {
                            input.value = new Date().toISOString().split('T')[0];
                        } else {
                            input.value = '';
                        }
                    });
                }
                
                function printTable(tableName) {
                    const printSection = document.getElementById(tableName + '-print-section');
                    const printWindow = window.open('', '', 'left=0,top=0,width=800,height=900,toolbar=0,scrollbars=0,status=0');
                    
                    printWindow.document.write('<html><head><title>Print ' + tableName + ' Records</title>');
                    printWindow.document.write('<style>');
                    printWindow.document.write('body { font-family: Arial; }');
                    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
                    printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
                    printWindow.document.write('th { background-color: #f2f2f2; }');
                    printWindow.document.write('h3 { text-align: center; }');
                    printWindow.document.write('</style>');
                    printWindow.document.write('</head><body>');
                    printWindow.document.write(printSection.innerHTML);
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    printWindow.focus();
                    
                    // Wait for content to load before printing
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 500);
                }
            </script>
        </div> <!-- Close main-content -->
    </body>
    </html>";

} catch (PDOException $e) {
    echo "<div class='error-message'><i class='fas fa-database'></i> Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
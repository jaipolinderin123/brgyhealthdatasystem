<?php
// Database connection
$servername = "sql112.ezyro.com";
$username = "ezyro_39081039";
$password = "healthdata12345";
$dbname = "ezyro_39081039_healthdata";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get cluster metrics
$metrics_sql = "SELECT * FROM imci_clustering_metrics";
$metrics_result = $conn->query($metrics_sql);

// Get cluster assignments
$clusters_sql = "SELECT * FROM imci_clustering_results";
$clusters_result = $conn->query($clusters_sql);

// Get optimal K
$optimal_k_sql = "SELECT k FROM imci_clustering_metrics WHERE optimal_k = 1 LIMIT 1";
$optimal_k_result = $conn->query($optimal_k_sql);
$optimal_k_row = $optimal_k_result->fetch_assoc();
$optimal_k = $optimal_k_row['k'];

// Calculate cluster profiles
$cluster_profiles = array();
$cluster_counts = array();
$numeric_stats = array();
$categorical_stats = array();

// Initialize arrays with all required keys
for ($i = 0; $i < $optimal_k; $i++) {
    $cluster_profiles[$i] = array();
    $cluster_counts[$i] = 0;
    $numeric_stats[$i] = array(
        'age_sum' => 0,
        'age_count' => 0,
        'age_min' => PHP_INT_MAX,
        'age_max' => 0,
        'quantity_sum' => 0,
        'quantity_min' => PHP_INT_MAX,
        'quantity_max' => 0,
        'days_sum' => 0,
        'days_min' => PHP_INT_MAX,
        'days_max' => 0
    );
    $categorical_stats[$i] = array(
        'gender' => array('male' => 0, 'female' => 0), // Initialize with both gender keys
        'chief_complain' => array(),
        'medicine_given' => array(),
        'purok' => array()
    );
}

// Process cluster data
if ($clusters_result->num_rows > 0) {
    while($row = $clusters_result->fetch_assoc()) {
        $cluster = $row['cluster'];
        $cluster_counts[$cluster]++;
        
        // Numeric features
        $numeric_stats[$cluster]['age_sum'] += $row['age'];
        $numeric_stats[$cluster]['age_count']++;
        $numeric_stats[$cluster]['age_min'] = min($numeric_stats[$cluster]['age_min'], $row['age']);
        $numeric_stats[$cluster]['age_max'] = max($numeric_stats[$cluster]['age_max'], $row['age']);
        
        $numeric_stats[$cluster]['quantity_sum'] += $row['quantity'];
        $numeric_stats[$cluster]['quantity_min'] = min($numeric_stats[$cluster]['quantity_min'], $row['quantity']);
        $numeric_stats[$cluster]['quantity_max'] = max($numeric_stats[$cluster]['quantity_max'], $row['quantity']);
        
        $numeric_stats[$cluster]['days_sum'] += $row['days'];
        $numeric_stats[$cluster]['days_min'] = min($numeric_stats[$cluster]['days_min'], $row['days']);
        $numeric_stats[$cluster]['days_max'] = max($numeric_stats[$cluster]['days_max'], $row['days']);
        
        // Categorical features - gender (case-insensitive)
        $gender = strtolower($row['gender']);
        if (isset($categorical_stats[$cluster]['gender'][$gender])) {
            $categorical_stats[$cluster]['gender'][$gender]++;
        }
        
        // Chief complaints
        $complaint = $row['chief_complain'];
        if (!isset($categorical_stats[$cluster]['chief_complain'][$complaint])) {
            $categorical_stats[$cluster]['chief_complain'][$complaint] = 0;
        }
        $categorical_stats[$cluster]['chief_complain'][$complaint]++;
        
        // Medicine given
        $medicine = $row['medicine_given'];
        if (!isset($categorical_stats[$cluster]['medicine_given'][$medicine])) {
            $categorical_stats[$cluster]['medicine_given'][$medicine] = 0;
        }
        $categorical_stats[$cluster]['medicine_given'][$medicine]++;
        
        // Purok distribution
        $purok = $row['purok'];
        if (!isset($categorical_stats[$cluster]['purok'][$purok])) {
            $categorical_stats[$cluster]['purok'][$purok] = 0;
        }
        $categorical_stats[$cluster]['purok'][$purok]++;
    }
}

// Calculate final stats
for ($i = 0; $i < $optimal_k; $i++) {
    $cluster_profiles[$i]['count'] = $cluster_counts[$i];
    $total = $cluster_counts[$i];
    
    // Numeric stats
    $cluster_profiles[$i]['age_mean'] = $numeric_stats[$i]['age_sum'] / $total;
    $cluster_profiles[$i]['age_min'] = $numeric_stats[$i]['age_min'];
    $cluster_profiles[$i]['age_max'] = $numeric_stats[$i]['age_max'];
    
    $cluster_profiles[$i]['quantity_mean'] = $numeric_stats[$i]['quantity_sum'] / $total;
    $cluster_profiles[$i]['quantity_min'] = $numeric_stats[$i]['quantity_min'];
    $cluster_profiles[$i]['quantity_max'] = $numeric_stats[$i]['quantity_max'];
    
    $cluster_profiles[$i]['days_mean'] = $numeric_stats[$i]['days_sum'] / $total;
    $cluster_profiles[$i]['days_min'] = $numeric_stats[$i]['days_min'];
    $cluster_profiles[$i]['days_max'] = $numeric_stats[$i]['days_max'];
    
    // Gender distribution
    $cluster_profiles[$i]['gender_male'] = round(($categorical_stats[$i]['gender']['male'] / $total) * 100, 2);
    $cluster_profiles[$i]['gender_female'] = round(($categorical_stats[$i]['gender']['female'] / $total) * 100, 2);
    
    // Top complaints (top 3)
    arsort($categorical_stats[$i]['chief_complain']);
    $cluster_profiles[$i]['top_complaints'] = array_slice($categorical_stats[$i]['chief_complain'], 0, 3, true);
    
    // Top medicines (top 3)
    arsort($categorical_stats[$i]['medicine_given']);
    $cluster_profiles[$i]['top_medicines'] = array_slice($categorical_stats[$i]['medicine_given'], 0, 3, true);
    
    // Top puroks (top 3)
    arsort($categorical_stats[$i]['purok']);
    $cluster_profiles[$i]['top_puroks'] = array_slice($categorical_stats[$i]['purok'], 0, 3, true);
    
    // Determine priority level
    $cluster_profiles[$i]['priority'] = 'low-priority';
    if ($cluster_profiles[$i]['age_mean'] < 12 || 
        isset($cluster_profiles[$i]['top_complaints']['Pneumonia']) || 
        isset($cluster_profiles[$i]['top_complaints']['Malnutrition'])) {
        $cluster_profiles[$i]['priority'] = 'high-priority';
    } elseif ($cluster_profiles[$i]['age_mean'] < 24 || 
             isset($cluster_profiles[$i]['top_complaints']['Diarrhea'])) {
        $cluster_profiles[$i]['priority'] = 'medium-priority';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMCI Clustering Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .metric-card { background-color: #f8f9fa; }
        .optimal-card { background-color: #e7f5ff; }
        .profile-table th { background-color: #f1f1f1; }
        .badge-complaint { background-color: #6c757d; margin-right: 5px; }
        .badge-medicine { background-color: #0d6efd; margin-right: 5px; }
        .badge-purok { background-color: #198754; margin-right: 5px; }
        .high-priority { background-color: #ffcccc; }
        .medium-priority { background-color: #fff3cd; }
        .low-priority { background-color: #d1e7dd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">IMCI Clustering Results</h1>
        
        <!-- Cluster Metrics -->
        <div class="card metric-card">
            <div class="card-header">
                <h2>Cluster Evaluation Metrics</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>K</th>
                                <th>Inertia</th>
                                <th>Silhouette Score</th>
                                <th>Elbow Diff</th>
                                <th>Optimal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $metrics_result->fetch_assoc()): ?>
                            <tr <?= $row['optimal_k'] ? 'class="table-success"' : '' ?>>
                                <td><?= $row['k'] ?></td>
                                <td><?= number_format($row['inertia'], 2) ?></td>
                                <td><?= number_format($row['silhouette_score'], 4) ?></td>
                                <td><?= $row['elbow_diff'] ? number_format($row['elbow_diff'], 2) : 'N/A' ?></td>
                                <td><?= $row['optimal_k'] ? '✓' : '' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Optimal Clusters -->
        <div class="card optimal-card">
            <div class="card-header">
                <h2>Optimal Cluster Selection</h2>
            </div>
            <div class="card-body">
                <p class="lead">The algorithm selected <strong><?= $optimal_k ?></strong> as the optimal number of clusters.</p>
            </div>
        </div>
        
        <!-- Cluster Distribution -->
        <div class="card">
            <div class="card-header">
                <h2>Cluster Distribution</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cluster</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = array_sum($cluster_counts);
                            foreach ($cluster_counts as $cluster => $count): 
                                $percentage = ($count / $total) * 100;
                            ?>
                            <tr>
                                <td><?= $cluster ?></td>
                                <td><?= $count ?></td>
                                <td><?= number_format($percentage, 2) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-secondary">
                                <td><strong>Total</strong></td>
                                <td><strong><?= $total ?></strong></td>
                                <td><strong>100%</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Cluster Profiles -->
        <div class="card">
            <div class="card-header">
                <h2>Cluster Profiles</h2>
            </div>
            <div class="card-body">
                <?php foreach ($cluster_profiles as $cluster => $profile): ?>
                <div class="mb-4 <?= $profile['priority'] ?> p-3 rounded">
                    <h4>Cluster <?= $cluster ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered profile-table">
                            <tr>
                                <th width="25%">Size</th>
                                <td><?= $profile['count'] ?> records (<?= number_format(($profile['count'] / $total) * 100, 2) ?>%)</td>
                            </tr>
                            <tr>
                                <th>Age (months)</th>
                                <td>
                                    Mean: <?= number_format($profile['age_mean'], 1) ?><br>
                                    Range: <?= $profile['age_min'] ?> to <?= $profile['age_max'] ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Medicine Quantity</th>
                                <td>
                                    Mean: <?= number_format($profile['quantity_mean'], 1) ?><br>
                                    Range: <?= $profile['quantity_min'] ?> to <?= $profile['quantity_max'] ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Treatment Days</th>
                                <td>
                                    Mean: <?= number_format($profile['days_mean'], 1) ?><br>
                                    Range: <?= $profile['days_min'] ?> to <?= $profile['days_max'] ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Gender Distribution</th>
                                <td>
                                    Male: <?= $profile['gender_male'] ?>% | 
                                    Female: <?= $profile['gender_female'] ?>%
                                </td>
                            </tr>
                            <tr>
                                <th>Top Complaints</th>
                                <td>
                                    <?php foreach ($profile['top_complaints'] as $complaint => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-complaint">
                                        <?= $complaint ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Top Medicines</th>
                                <td>
                                    <?php foreach ($profile['top_medicines'] as $medicine => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-medicine">
                                        <?= $medicine ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Top Puroks</th>
                                <td>
                                    <?php foreach ($profile['top_puroks'] as $purok => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-purok">
                                        <?= $purok ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Priority Level</th>
                                <td class="fw-bold">
                                    <?= strtoupper(str_replace('-', ' ', $profile['priority'])) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>
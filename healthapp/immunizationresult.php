<?php
// Database connection
$servername = "localhost";
$username = "admin";
$password = "healthdata123";
$dbname = "healthdata";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get cluster metrics
$metrics_sql = "SELECT * FROM immunization_clustering_metrics";
$metrics_result = $conn->query($metrics_sql);

// Get cluster assignments
$clusters_sql = "SELECT * FROM immunization_clustering_results";
$clusters_result = $conn->query($clusters_sql);

// Get optimal K
$optimal_k_sql = "SELECT k FROM immunization_clustering_metrics WHERE optimal_k = 1 LIMIT 1";
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
        'weight_sum' => 0,
        'weight_min' => PHP_INT_MAX,
        'weight_max' => 0,
        'dose_sum' => 0,
        'dose_min' => PHP_INT_MAX,
        'dose_max' => 0
    );
    $categorical_stats[$i] = array(
        'gender' => array('male' => 0, 'female' => 0),
        'vaccine_type' => array(),
        'location' => array(),
        'health_worker' => array()
    );
}

// Process cluster data
if ($clusters_result->num_rows > 0) {
    while($row = $clusters_result->fetch_assoc()) {
        $cluster = $row['cluster'];
        $cluster_counts[$cluster]++;
        
        // Numeric features
        $numeric_stats[$cluster]['age_sum'] += $row['age_in_months'];
        $numeric_stats[$cluster]['age_count']++;
        $numeric_stats[$cluster]['age_min'] = min($numeric_stats[$cluster]['age_min'], $row['age_in_months']);
        $numeric_stats[$cluster]['age_max'] = max($numeric_stats[$cluster]['age_max'], $row['age_in_months']);
        
        $numeric_stats[$cluster]['weight_sum'] += $row['weight'];
        $numeric_stats[$cluster]['weight_min'] = min($numeric_stats[$cluster]['weight_min'], $row['weight']);
        $numeric_stats[$cluster]['weight_max'] = max($numeric_stats[$cluster]['weight_max'], $row['weight']);
        
        $numeric_stats[$cluster]['dose_sum'] += $row['dose'];
        $numeric_stats[$cluster]['dose_min'] = min($numeric_stats[$cluster]['dose_min'], $row['dose']);
        $numeric_stats[$cluster]['dose_max'] = max($numeric_stats[$cluster]['dose_max'], $row['dose']);
        
        // Categorical features - gender (case-insensitive)
        $gender = strtolower($row['gender']);
        if (isset($categorical_stats[$cluster]['gender'][$gender])) {
            $categorical_stats[$cluster]['gender'][$gender]++;
        }
        
        // Vaccine types
        $vaccine = $row['vaccine_type'];
        if (!isset($categorical_stats[$cluster]['vaccine_type'][$vaccine])) {
            $categorical_stats[$cluster]['vaccine_type'][$vaccine] = 0;
        }
        $categorical_stats[$cluster]['vaccine_type'][$vaccine]++;
        
        // Location
        $location = $row['location'];
        if (!isset($categorical_stats[$cluster]['location'][$location])) {
            $categorical_stats[$cluster]['location'][$location] = 0;
        }
        $categorical_stats[$cluster]['location'][$location]++;
        
        // Health worker
        $worker = $row['health_worker'];
        if (!isset($categorical_stats[$cluster]['health_worker'][$worker])) {
            $categorical_stats[$cluster]['health_worker'][$worker] = 0;
        }
        $categorical_stats[$cluster]['health_worker'][$worker]++;
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
    
    $cluster_profiles[$i]['weight_mean'] = $numeric_stats[$i]['weight_sum'] / $total;
    $cluster_profiles[$i]['weight_min'] = $numeric_stats[$i]['weight_min'];
    $cluster_profiles[$i]['weight_max'] = $numeric_stats[$i]['weight_max'];
    
    $cluster_profiles[$i]['dose_mean'] = $numeric_stats[$i]['dose_sum'] / $total;
    $cluster_profiles[$i]['dose_min'] = $numeric_stats[$i]['dose_min'];
    $cluster_profiles[$i]['dose_max'] = $numeric_stats[$i]['dose_max'];
    
    // Gender distribution
    $cluster_profiles[$i]['gender_male'] = round(($categorical_stats[$i]['gender']['male'] / $total) * 100, 2);
    $cluster_profiles[$i]['gender_female'] = round(($categorical_stats[$i]['gender']['female'] / $total) * 100, 2);
    
    // Top vaccine types (top 3)
    arsort($categorical_stats[$i]['vaccine_type']);
    $cluster_profiles[$i]['top_vaccines'] = array_slice($categorical_stats[$i]['vaccine_type'], 0, 3, true);
    
    // Location distribution
    $cluster_profiles[$i]['location_urban'] = isset($categorical_stats[$i]['location']['Urban']) ? 
        round(($categorical_stats[$i]['location']['Urban'] / $total) * 100, 2) : 0;
    $cluster_profiles[$i]['location_rural'] = isset($categorical_stats[$i]['location']['Rural']) ? 
        round(($categorical_stats[$i]['location']['Rural'] / $total) * 100, 2) : 0;
    
    // Top health workers (top 3)
    arsort($categorical_stats[$i]['health_worker']);
    $cluster_profiles[$i]['top_workers'] = array_slice($categorical_stats[$i]['health_worker'], 0, 3, true);
    
    // Determine priority level
    $cluster_profiles[$i]['priority'] = 'low-priority';
    if ($cluster_profiles[$i]['age_mean'] < 6 || 
        isset($cluster_profiles[$i]['top_vaccines']['BCG']) || 
        isset($cluster_profiles[$i]['top_vaccines']['Measles'])) {
        $cluster_profiles[$i]['priority'] = 'high-priority';
    } elseif ($cluster_profiles[$i]['age_mean'] < 12 || 
             isset($cluster_profiles[$i]['top_vaccines']['DPT'])) {
        $cluster_profiles[$i]['priority'] = 'medium-priority';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Clustering Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .metric-card { background-color: #f8f9fa; }
        .optimal-card { background-color: #e7f5ff; }
        .profile-table th { background-color: #f1f1f1; }
        .badge-vaccine { background-color: #6c757d; margin-right: 5px; }
        .badge-worker { background-color: #0d6efd; margin-right: 5px; }
        .high-priority { background-color: #ffcccc; }
        .medium-priority { background-color: #fff3cd; }
        .low-priority { background-color: #d1e7dd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Immunization Clustering Results</h1>
        
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
                                <th>Weight (kg)</th>
                                <td>
                                    Mean: <?= number_format($profile['weight_mean'], 2) ?><br>
                                    Range: <?= number_format($profile['weight_min'], 2) ?> to <?= number_format($profile['weight_max'], 2) ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Dose Number</th>
                                <td>
                                    Mean: <?= number_format($profile['dose_mean'], 1) ?><br>
                                    Range: <?= $profile['dose_min'] ?> to <?= $profile['dose_max'] ?>
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
                                <th>Top Vaccines</th>
                                <td>
                                    <?php foreach ($profile['top_vaccines'] as $vaccine => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-vaccine">
                                        <?= $vaccine ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Location</th>
                                <td>
                                    Urban: <?= $profile['location_urban'] ?>% | 
                                    Rural: <?= $profile['location_rural'] ?>%
                                </td>
                            </tr>
                            <tr>
                                <th>Health Workers</th>
                                <td>
                                    <?php foreach ($profile['top_workers'] as $worker => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-worker">
                                        <?= $worker ?>: <?= number_format($pct, 1) ?>%
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
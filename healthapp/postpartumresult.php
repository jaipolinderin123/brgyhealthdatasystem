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
$metrics_sql = "SELECT * FROM postpartum_clustering_metrics";
$metrics_result = $conn->query($metrics_sql);

// Get cluster assignments
$clusters_sql = "SELECT * FROM postpartum_clustering_results";
$clusters_result = $conn->query($clusters_sql);

// Get optimal K
$optimal_k_sql = "SELECT k FROM postpartum_clustering_metrics WHERE optimal_k = 1 LIMIT 1";
$optimal_k_result = $conn->query($optimal_k_sql);
$optimal_k_row = $optimal_k_result->fetch_assoc();
$optimal_k = $optimal_k_row['k'];

// Calculate cluster profiles
$cluster_profiles = array();
$cluster_counts = array();
$numeric_stats = array();
$categorical_stats = array();
$month_distribution = array();

// Initialize arrays
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
        'weight_max' => 0
    );
    $categorical_stats[$i] = array(
        'type_of_delivery' => array('Normal' => 0, 'C-section' => 0),
        'place_of_delivery' => array('Hospital' => 0, 'Clinic' => 0, 'Home' => 0),
        'medical_staff_attend' => array('Doctor' => 0, 'Nurse' => 0, 'Midwife' => 0)
    );
    $month_distribution[$i] = array();
}

// Process cluster data
if ($clusters_result->num_rows > 0) {
    while($row = $clusters_result->fetch_assoc()) {
        $cluster = $row['cluster'];
        $cluster_counts[$cluster]++;
        
        // Numeric features
        $numeric_stats[$cluster]['age_sum'] += $row['age_of_mother'];
        $numeric_stats[$cluster]['age_count']++;
        $numeric_stats[$cluster]['age_min'] = min($numeric_stats[$cluster]['age_min'], $row['age_of_mother']);
        $numeric_stats[$cluster]['age_max'] = max($numeric_stats[$cluster]['age_max'], $row['age_of_mother']);
        
        $numeric_stats[$cluster]['weight_sum'] += $row['baby_weight'];
        $numeric_stats[$cluster]['weight_min'] = min($numeric_stats[$cluster]['weight_min'], $row['baby_weight']);
        $numeric_stats[$cluster]['weight_max'] = max($numeric_stats[$cluster]['weight_max'], $row['baby_weight']);
        
        // Categorical features
        $categorical_stats[$cluster]['type_of_delivery'][$row['type_of_delivery']]++;
        $categorical_stats[$cluster]['place_of_delivery'][$row['place_of_delivery']]++;
        $categorical_stats[$cluster]['medical_staff_attend'][$row['medical_staff_attend']]++;
        
        // Month distribution
        $month = $row['month'];
        if (!isset($month_distribution[$cluster][$month])) {
            $month_distribution[$cluster][$month] = 0;
        }
        $month_distribution[$cluster][$month]++;
    }
}

// Calculate final stats
for ($i = 0; $i < $optimal_k; $i++) {
    $cluster_profiles[$i]['count'] = $cluster_counts[$i];
    
    // Numeric stats
    $cluster_profiles[$i]['age_mean'] = 
        $numeric_stats[$i]['age_sum'] / $numeric_stats[$i]['age_count'];
    $cluster_profiles[$i]['age_min'] = $numeric_stats[$i]['age_min'];
    $cluster_profiles[$i]['age_max'] = $numeric_stats[$i]['age_max'];
    
    $cluster_profiles[$i]['weight_mean'] = 
        $numeric_stats[$i]['weight_sum'] / $numeric_stats[$i]['age_count'];
    $cluster_profiles[$i]['weight_min'] = $numeric_stats[$i]['weight_min'];
    $cluster_profiles[$i]['weight_max'] = $numeric_stats[$i]['weight_max'];
    
    // Categorical stats (percentages)
    $total = $cluster_counts[$i];
    foreach ($categorical_stats[$i] as $feature => $values) {
        foreach ($values as $category => $count) {
            $cluster_profiles[$i][$feature . '_' . $category] = round(($count / $total) * 100, 2);
        }
    }
    
    // Month distribution (top 3)
    arsort($month_distribution[$i]);
    $cluster_profiles[$i]['top_months'] = array_slice($month_distribution[$i], 0, 3, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postpartum Clustering Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .metric-card { background-color: #f8f9fa; }
        .optimal-card { background-color: #e7f5ff; }
        .profile-table th { background-color: #f1f1f1; }
        .month-badge { margin-right: 5px; }
        .high-risk { background-color: #ffcccc; }
        .normal-risk { background-color: #ccffcc; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Postpartum Clustering Results</h1>
        
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
                <?php foreach ($cluster_profiles as $cluster => $profile): 
                    // Determine risk level based on features
                    $risk_level = ($profile['type_of_delivery_C-section'] > 40 || 
                                 $profile['place_of_delivery_Home'] > 20 || 
                                 $profile['age_mean'] < 20 || 
                                 $profile['weight_mean'] < 2.5) ? 'high-risk' : 'normal-risk';
                ?>
                <div class="mb-4 <?= $risk_level ?> p-3 rounded">
                    <h4>Cluster <?= $cluster ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered profile-table">
                            <tr>
                                <th width="25%">Size</th>
                                <td><?= $profile['count'] ?> records (<?= number_format(($profile['count'] / $total) * 100, 2) ?>%)</td>
                            </tr>
                            <tr>
                                <th>Mother's Age</th>
                                <td>
                                    Mean: <?= number_format($profile['age_mean'], 1) ?> years<br>
                                    Range: <?= $profile['age_min'] ?> to <?= $profile['age_max'] ?> years
                                </td>
                            </tr>
                            <tr>
                                <th>Baby Weight</th>
                                <td>
                                    Mean: <?= number_format($profile['weight_mean'], 2) ?> kg<br>
                                    Range: <?= number_format($profile['weight_min'], 2) ?> to <?= number_format($profile['weight_max'], 2) ?> kg
                                </td>
                            </tr>
                            <tr>
                                <th>Delivery Type</th>
                                <td>
                                    Normal: <?= $profile['type_of_delivery_Normal'] ?>%<br>
                                    C-section: <?= $profile['type_of_delivery_C-section'] ?>%
                                </td>
                            </tr>
                            <tr>
                                <th>Place of Delivery</th>
                                <td>
                                    Hospital: <?= $profile['place_of_delivery_Hospital'] ?>%<br>
                                    Clinic: <?= $profile['place_of_delivery_Clinic'] ?>%<br>
                                    Home: <?= $profile['place_of_delivery_Home'] ?>%
                                </td>
                            </tr>
                            <tr>
                                <th>Medical Staff</th>
                                <td>
                                    Doctor: <?= $profile['medical_staff_attend_Doctor'] ?>%<br>
                                    Nurse: <?= $profile['medical_staff_attend_Nurse'] ?>%<br>
                                    Midwife: <?= $profile['medical_staff_attend_Midwife'] ?>%
                                </td>
                            </tr>
                            <tr>
                                <th>Top Months</th>
                                <td>
                                    <?php foreach ($profile['top_months'] as $month => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge bg-primary month-badge">
                                        <?= $month ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Risk Level</th>
                                <td class="fw-bold">
                                    <?= $risk_level == 'high-risk' ? 'HIGH RISK' : 'Normal Risk' ?>
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
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
$metrics_sql = "SELECT * FROM family_planning_clustering_metrics";
$metrics_result = $conn->query($metrics_sql);

// Get cluster assignments
$clusters_sql = "SELECT * FROM family_planning_clustering_results";
$clusters_result = $conn->query($clusters_sql);

// Get optimal K
$optimal_k_sql = "SELECT k FROM family_planning_clustering_metrics WHERE optimal_k = 1 LIMIT 1";
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
        'purok_sum' => 0,
        'purok_min' => PHP_INT_MAX,
        'purok_max' => 0
    );
    $categorical_stats[$i] = array(
        'se_status' => array(),
        'type_of_client' => array(),
        'source' => array(),
        'previous_method' => array()
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
        
        $numeric_stats[$cluster]['purok_sum'] += $row['purok'];
        $numeric_stats[$cluster]['purok_min'] = min($numeric_stats[$cluster]['purok_min'], $row['purok']);
        $numeric_stats[$cluster]['purok_max'] = max($numeric_stats[$cluster]['purok_max'], $row['purok']);
        
        // Categorical features
        $se_status = $row['se_status'];
        if (!isset($categorical_stats[$cluster]['se_status'][$se_status])) {
            $categorical_stats[$cluster]['se_status'][$se_status] = 0;
        }
        $categorical_stats[$cluster]['se_status'][$se_status]++;
        
        $client_type = $row['type_of_client'];
        if (!isset($categorical_stats[$cluster]['type_of_client'][$client_type])) {
            $categorical_stats[$cluster]['type_of_client'][$client_type] = 0;
        }
        $categorical_stats[$cluster]['type_of_client'][$client_type]++;
        
        $source = $row['source'];
        if (!isset($categorical_stats[$cluster]['source'][$source])) {
            $categorical_stats[$cluster]['source'][$source] = 0;
        }
        $categorical_stats[$cluster]['source'][$source]++;
        
        $method = $row['previous_method'];
        if (!isset($categorical_stats[$cluster]['previous_method'][$method])) {
            $categorical_stats[$cluster]['previous_method'][$method] = 0;
        }
        $categorical_stats[$cluster]['previous_method'][$method]++;
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
    
    $cluster_profiles[$i]['purok_mean'] = $numeric_stats[$i]['purok_sum'] / $total;
    $cluster_profiles[$i]['purok_min'] = $numeric_stats[$i]['purok_min'];
    $cluster_profiles[$i]['purok_max'] = $numeric_stats[$i]['purok_max'];
    
    // SE Status distribution
    arsort($categorical_stats[$i]['se_status']);
    $cluster_profiles[$i]['top_se_status'] = array_slice($categorical_stats[$i]['se_status'], 0, 2, true);
    
    // Client type distribution
    arsort($categorical_stats[$i]['type_of_client']);
    $cluster_profiles[$i]['top_client_types'] = array_slice($categorical_stats[$i]['type_of_client'], 0, 3, true);
    
    // Source distribution
    arsort($categorical_stats[$i]['source']);
    $cluster_profiles[$i]['top_sources'] = array_slice($categorical_stats[$i]['source'], 0, 2, true);
    
    // Previous method distribution
    arsort($categorical_stats[$i]['previous_method']);
    $cluster_profiles[$i]['top_methods'] = array_slice($categorical_stats[$i]['previous_method'], 0, 3, true);
    
    // Determine priority level
    $cluster_profiles[$i]['priority'] = 'low-priority';
    if ($cluster_profiles[$i]['age_mean'] < 25 || 
        isset($cluster_profiles[$i]['top_se_status']['Poor'])) {
        $cluster_profiles[$i]['priority'] = 'high-priority';
    } elseif ($cluster_profiles[$i]['age_mean'] < 30 || 
             isset($cluster_profiles[$i]['top_se_status']['Low Income'])) {
        $cluster_profiles[$i]['priority'] = 'medium-priority';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Planning Clustering Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .metric-card { background-color: #f8f9fa; }
        .optimal-card { background-color: #e7f5ff; }
        .profile-table th { background-color: #f1f1f1; }
        .badge-se-status { background-color: #6c757d; margin-right: 5px; }
        .badge-client { background-color: #0d6efd; margin-right: 5px; }
        .badge-source { background-color: #198754; margin-right: 5px; }
        .badge-method { background-color: #ffc107; margin-right: 5px; color: #000; }
        .high-priority { background-color: #ffcccc; }
        .medium-priority { background-color: #fff3cd; }
        .low-priority { background-color: #d1e7dd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Family Planning Clustering Results</h1>
        
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
                                <th>Age (years)</th>
                                <td>
                                    Mean: <?= number_format($profile['age_mean'], 1) ?><br>
                                    Range: <?= $profile['age_min'] ?> to <?= $profile['age_max'] ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Purok</th>
                                <td>
                                    Mean: <?= number_format($profile['purok_mean'], 1) ?><br>
                                    Range: <?= $profile['purok_min'] ?> to <?= $profile['purok_max'] ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Socioeconomic Status</th>
                                <td>
                                    <?php foreach ($profile['top_se_status'] as $status => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-se-status">
                                        <?= $status ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Client Types</th>
                                <td>
                                    <?php foreach ($profile['top_client_types'] as $type => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-client">
                                        <?= $type ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Service Sources</th>
                                <td>
                                    <?php foreach ($profile['top_sources'] as $source => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-source">
                                        <?= $source ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Previous Methods</th>
                                <td>
                                    <?php foreach ($profile['top_methods'] as $method => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-method">
                                        <?= $method ?>: <?= number_format($pct, 1) ?>%
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
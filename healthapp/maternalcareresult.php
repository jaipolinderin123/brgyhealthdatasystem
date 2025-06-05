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
$metrics_sql = "SELECT * FROM maternal_care_clustering_metrics";
$metrics_result = $conn->query($metrics_sql);

// Get cluster assignments
$clusters_sql = "SELECT * FROM maternal_care_clustering_results";
$clusters_result = $conn->query($clusters_sql);

// Get optimal K
$optimal_k_sql = "SELECT k FROM maternal_care_clustering_metrics WHERE optimal_k = 1 LIMIT 1";
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
        'bmi_sum' => 0,
        'bmi_min' => PHP_INT_MAX,
        'bmi_max' => 0,
        'birth_weight_sum' => 0,
        'birth_weight_min' => PHP_INT_MAX,
        'birth_weight_max' => 0,
        'prenatal_visits_sum' => 0,
        'prenatal_visits_min' => PHP_INT_MAX,
        'prenatal_visits_max' => 0,
        'iron_sum' => 0,
        'iron_count' => 0
    );
    $categorical_stats[$i] = array(
        'given_iron' => array(0 => 0, 1 => 0),
        'deworming_tablet_given' => array(0 => 0, 1 => 0),
        'vitamin_a_given' => array(0 => 0, 1 => 0),
        'completed_90_tablets_iron' => array(0 => 0, 1 => 0),
        'type_of_delivery' => array(),
        'place_of_delivery' => array(),
        'purok' => array()
    );
}

// Process cluster data
if ($clusters_result->num_rows > 0) {
    while($row = $clusters_result->fetch_assoc()) {
        $cluster = $row['cluster'];
        $cluster_counts[$cluster]++;
        
        // Numeric features
        if (isset($row['age'])) {
            $numeric_stats[$cluster]['age_sum'] += $row['age'];
            $numeric_stats[$cluster]['age_count']++;
            $numeric_stats[$cluster]['age_min'] = min($numeric_stats[$cluster]['age_min'], $row['age']);
            $numeric_stats[$cluster]['age_max'] = max($numeric_stats[$cluster]['age_max'], $row['age']);
        }
        
        if (isset($row['bmi'])) {
            $numeric_stats[$cluster]['bmi_sum'] += $row['bmi'];
            $numeric_stats[$cluster]['bmi_min'] = min($numeric_stats[$cluster]['bmi_min'], $row['bmi']);
            $numeric_stats[$cluster]['bmi_max'] = max($numeric_stats[$cluster]['bmi_max'], $row['bmi']);
        }
        
        if (isset($row['birth_weight'])) {
            $numeric_stats[$cluster]['birth_weight_sum'] += $row['birth_weight'];
            $numeric_stats[$cluster]['birth_weight_min'] = min($numeric_stats[$cluster]['birth_weight_min'], $row['birth_weight']);
            $numeric_stats[$cluster]['birth_weight_max'] = max($numeric_stats[$cluster]['birth_weight_max'], $row['birth_weight']);
        }
        
        if (isset($row['prenatal_visits'])) {
            $numeric_stats[$cluster]['prenatal_visits_sum'] += $row['prenatal_visits'];
            $numeric_stats[$cluster]['prenatal_visits_min'] = min($numeric_stats[$cluster]['prenatal_visits_min'], $row['prenatal_visits']);
            $numeric_stats[$cluster]['prenatal_visits_max'] = max($numeric_stats[$cluster]['prenatal_visits_max'], $row['prenatal_visits']);
        }
        
        // Iron supplementation
        if (isset($row['given_iron'])) {
            $numeric_stats[$cluster]['iron_sum'] += $row['given_iron'];
            $numeric_stats[$cluster]['iron_count']++;
        }
        
        // Binary features
        if (isset($row['given_iron'])) {
            $categorical_stats[$cluster]['given_iron'][$row['given_iron']]++;
        }
        
        if (isset($row['deworming_tablet_given'])) {
            $categorical_stats[$cluster]['deworming_tablet_given'][$row['deworming_tablet_given']]++;
        }
        
        if (isset($row['vitamin_a_given'])) {
            $categorical_stats[$cluster]['vitamin_a_given'][$row['vitamin_a_given']]++;
        }
        
        if (isset($row['completed_90_tablets_iron'])) {
            $categorical_stats[$cluster]['completed_90_tablets_iron'][$row['completed_90_tablets_iron']]++;
        }
        
        // Categorical features
        if (isset($row['type_of_delivery'])) {
            $delivery_type = $row['type_of_delivery'];
            if (!isset($categorical_stats[$cluster]['type_of_delivery'][$delivery_type])) {
                $categorical_stats[$cluster]['type_of_delivery'][$delivery_type] = 0;
            }
            $categorical_stats[$cluster]['type_of_delivery'][$delivery_type]++;
        }
        
        if (isset($row['place_of_delivery'])) {
            $place = $row['place_of_delivery'];
            if (!isset($categorical_stats[$cluster]['place_of_delivery'][$place])) {
                $categorical_stats[$cluster]['place_of_delivery'][$place] = 0;
            }
            $categorical_stats[$cluster]['place_of_delivery'][$place]++;
        }
        
        // Purok distribution
        if (isset($row['purok'])) {
            $purok = $row['purok'];
            if (!isset($categorical_stats[$cluster]['purok'][$purok])) {
                $categorical_stats[$cluster]['purok'][$purok] = 0;
            }
            $categorical_stats[$cluster]['purok'][$purok]++;
        }
    }
}

// Calculate final stats
for ($i = 0; $i < $optimal_k; $i++) {
    $cluster_profiles[$i]['count'] = $cluster_counts[$i];
    $total = $cluster_counts[$i];
    
    // Numeric stats
    if ($numeric_stats[$i]['age_count'] > 0) {
        $cluster_profiles[$i]['age_mean'] = $numeric_stats[$i]['age_sum'] / $numeric_stats[$i]['age_count'];
        $cluster_profiles[$i]['age_min'] = $numeric_stats[$i]['age_min'];
        $cluster_profiles[$i]['age_max'] = $numeric_stats[$i]['age_max'];
    }
    
    if ($numeric_stats[$i]['bmi_min'] != PHP_INT_MAX) {
        $cluster_profiles[$i]['bmi_mean'] = $numeric_stats[$i]['bmi_sum'] / $total;
        $cluster_profiles[$i]['bmi_min'] = $numeric_stats[$i]['bmi_min'];
        $cluster_profiles[$i]['bmi_max'] = $numeric_stats[$i]['bmi_max'];
    }
    
    if ($numeric_stats[$i]['birth_weight_min'] != PHP_INT_MAX) {
        $cluster_profiles[$i]['birth_weight_mean'] = $numeric_stats[$i]['birth_weight_sum'] / $total;
        $cluster_profiles[$i]['birth_weight_min'] = $numeric_stats[$i]['birth_weight_min'];
        $cluster_profiles[$i]['birth_weight_max'] = $numeric_stats[$i]['birth_weight_max'];
    }
    
    if ($numeric_stats[$i]['prenatal_visits_min'] != PHP_INT_MAX) {
        $cluster_profiles[$i]['prenatal_visits_mean'] = $numeric_stats[$i]['prenatal_visits_sum'] / $total;
        $cluster_profiles[$i]['prenatal_visits_min'] = $numeric_stats[$i]['prenatal_visits_min'];
        $cluster_profiles[$i]['prenatal_visits_max'] = $numeric_stats[$i]['prenatal_visits_max'];
    }
    
    // Iron supplementation
    if ($numeric_stats[$i]['iron_count'] > 0) {
        $cluster_profiles[$i]['iron_percentage'] = round(($numeric_stats[$i]['iron_sum'] / $numeric_stats[$i]['iron_count']) * 100, 2);
    }
    
    // Binary features
    $cluster_profiles[$i]['given_iron_yes'] = isset($categorical_stats[$i]['given_iron'][1]) ? 
        round(($categorical_stats[$i]['given_iron'][1] / $total) * 100, 2) : 0;
    $cluster_profiles[$i]['given_iron_no'] = isset($categorical_stats[$i]['given_iron'][0]) ? 
        round(($categorical_stats[$i]['given_iron'][0] / $total) * 100, 2) : 0;
    
    $cluster_profiles[$i]['deworming_yes'] = isset($categorical_stats[$i]['deworming_tablet_given'][1]) ? 
        round(($categorical_stats[$i]['deworming_tablet_given'][1] / $total) * 100, 2) : 0;
    $cluster_profiles[$i]['deworming_no'] = isset($categorical_stats[$i]['deworming_tablet_given'][0]) ? 
        round(($categorical_stats[$i]['deworming_tablet_given'][0] / $total) * 100, 2) : 0;
    
    $cluster_profiles[$i]['vitamin_a_yes'] = isset($categorical_stats[$i]['vitamin_a_given'][1]) ? 
        round(($categorical_stats[$i]['vitamin_a_given'][1] / $total) * 100, 2) : 0;
    $cluster_profiles[$i]['vitamin_a_no'] = isset($categorical_stats[$i]['vitamin_a_given'][0]) ? 
        round(($categorical_stats[$i]['vitamin_a_given'][0] / $total) * 100, 2) : 0;
    
    $cluster_profiles[$i]['completed_iron_yes'] = isset($categorical_stats[$i]['completed_90_tablets_iron'][1]) ? 
        round(($categorical_stats[$i]['completed_90_tablets_iron'][1] / $total) * 100, 2) : 0;
    $cluster_profiles[$i]['completed_iron_no'] = isset($categorical_stats[$i]['completed_90_tablets_iron'][0]) ? 
        round(($categorical_stats[$i]['completed_90_tablets_iron'][0] / $total) * 100, 2) : 0;
    
    // Top delivery types
    if (isset($categorical_stats[$i]['type_of_delivery'])) {
        arsort($categorical_stats[$i]['type_of_delivery']);
        $cluster_profiles[$i]['top_delivery_types'] = array_slice($categorical_stats[$i]['type_of_delivery'], 0, 3, true);
    }
    
    // Top delivery places
    if (isset($categorical_stats[$i]['place_of_delivery'])) {
        arsort($categorical_stats[$i]['place_of_delivery']);
        $cluster_profiles[$i]['top_delivery_places'] = array_slice($categorical_stats[$i]['place_of_delivery'], 0, 3, true);
    }
    
    // Top puroks
    if (isset($categorical_stats[$i]['purok'])) {
        arsort($categorical_stats[$i]['purok']);
        $cluster_profiles[$i]['top_puroks'] = array_slice($categorical_stats[$i]['purok'], 0, 3, true);
    }
    
    // Determine priority level
    $cluster_profiles[$i]['priority'] = 'low-priority';
    if (($cluster_profiles[$i]['given_iron_no'] ?? 0) > 30 || 
        ($cluster_profiles[$i]['completed_iron_no'] ?? 0) > 50 ||
        (isset($cluster_profiles[$i]['top_delivery_places']['Home']) && 
         $cluster_profiles[$i]['top_delivery_places']['Home'] > 20)) {
        $cluster_profiles[$i]['priority'] = 'high-priority';
    } elseif (($cluster_profiles[$i]['given_iron_no'] ?? 0) > 15 || 
             ($cluster_profiles[$i]['completed_iron_no'] ?? 0) > 30) {
        $cluster_profiles[$i]['priority'] = 'medium-priority';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maternal Care Clustering Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { margin-bottom: 20px; }
        .metric-card { background-color: #f8f9fa; }
        .optimal-card { background-color: #e7f5ff; }
        .profile-table th { background-color: #f1f1f1; }
        .badge-delivery { background-color: #6c757d; margin-right: 5px; }
        .badge-place { background-color: #0d6efd; margin-right: 5px; }
        .badge-purok { background-color: #198754; margin-right: 5px; }
        .high-priority { background-color: #ffcccc; }
        .medium-priority { background-color: #fff3cd; }
        .low-priority { background-color: #d1e7dd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Maternal Care Clustering Results</h1>
        
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
                                <td><?= $profile['count'] ?> mothers (<?= number_format(($profile['count'] / $total) * 100, 2) ?>%)</td>
                            </tr>
                            
                            <?php if (isset($profile['age_mean'])): ?>
                            <tr>
                                <th>Age</th>
                                <td>
                                    Mean: <?= number_format($profile['age_mean'], 1) ?> years<br>
                                    Range: <?= $profile['age_min'] ?> to <?= $profile['age_max'] ?> years
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($profile['bmi_mean'])): ?>
                            <tr>
                                <th>BMI</th>
                                <td>
                                    Mean: <?= number_format($profile['bmi_mean'], 1) ?><br>
                                    Range: <?= number_format($profile['bmi_min'], 1) ?> to <?= number_format($profile['bmi_max'], 1) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($profile['birth_weight_mean'])): ?>
                            <tr>
                                <th>Birth Weight (kg)</th>
                                <td>
                                    Mean: <?= number_format($profile['birth_weight_mean'], 2) ?><br>
                                    Range: <?= number_format($profile['birth_weight_min'], 2) ?> to <?= number_format($profile['birth_weight_max'], 2) ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($profile['prenatal_visits_mean'])): ?>
                            <tr>
                                <th>Prenatal Visits</th>
                                <td>
                                    Mean: <?= number_format($profile['prenatal_visits_mean'], 1) ?><br>
                                    Range: <?= $profile['prenatal_visits_min'] ?> to <?= $profile['prenatal_visits_max'] ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <tr>
                                <th>Iron Supplementation</th>
                                <td>
                                    Received: <?= $profile['given_iron_yes'] ?>%<br>
                                    Not Received: <?= $profile['given_iron_no'] ?>%
                                </td>
                            </tr>
                            
                            <tr>
                                <th>Completed 90 Iron Tablets</th>
                                <td>
                                    Completed: <?= $profile['completed_iron_yes'] ?>%<br>
                                    Not Completed: <?= $profile['completed_iron_no'] ?>%
                                </td>
                            </tr>
                            
                            <tr>
                                <th>Deworming Tablet</th>
                                <td>
                                    Received: <?= $profile['deworming_yes'] ?>%<br>
                                    Not Received: <?= $profile['deworming_no'] ?>%
                                </td>
                            </tr>
                            
                            <tr>
                                <th>Vitamin A</th>
                                <td>
                                    Received: <?= $profile['vitamin_a_yes'] ?>%<br>
                                    Not Received: <?= $profile['vitamin_a_no'] ?>%
                                </td>
                            </tr>
                            
                            <?php if (isset($profile['top_delivery_types'])): ?>
                            <tr>
                                <th>Top Delivery Types</th>
                                <td>
                                    <?php foreach ($profile['top_delivery_types'] as $type => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-delivery">
                                        <?= $type ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($profile['top_delivery_places'])): ?>
                            <tr>
                                <th>Top Delivery Places</th>
                                <td>
                                    <?php foreach ($profile['top_delivery_places'] as $place => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-place">
                                        <?= $place ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php if (isset($profile['top_puroks'])): ?>
                            <tr>
                                <th>Top Puroks</th>
                                <td>
                                    <?php foreach ($profile['top_puroks'] as $purok => $count): 
                                        $pct = ($count / $profile['count']) * 100;
                                    ?>
                                    <span class="badge badge-purok">
                                        Purok <?= $purok ?>: <?= number_format($pct, 1) ?>%
                                    </span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
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
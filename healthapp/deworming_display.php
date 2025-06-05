<?php
// Configuration
$source_dir = 'D:/xampp/deworming_clustering_output'; // Source directory where files are generated
$output_dir = 'D:/xampp/htdocs/healthapp/deworming_clustering_output'; // Web directory
$web_output_dir = 'deworming_clustering_output'; // Web-accessible path

// Create output directory if it doesn't exist
if (!file_exists($output_dir)) {
    mkdir($output_dir, 0777, true);
}

// Copy files from source to web directory
if (file_exists("$source_dir/silhouette.png")) {
    copy("$source_dir/silhouette.png", "$output_dir/silhouette.png");
}
if (file_exists("$source_dir/tsne_clusters.png")) {
    copy("$source_dir/tsne_clusters.png", "$output_dir/tsne_clusters.png");
}

// Initialize plots array with file paths
$plots = [
    'metrics' => "$output_dir/cluster_metrics_comparison.png",
    'silhouette' => "$output_dir/silhouette.png", // Updated to match actual filename
    'pca' => "$output_dir/pca.png",
    'tsne' => "$output_dir/tsne_clusters.png", // Updated to match actual filename
    'sizes' => "$output_dir/cluster_sizes.png",
    'data' => "$output_dir/clustered_patients.csv",
    'profiles' => "$output_dir/cluster_profiles.csv"
];

// Debug information
$debug_info = [];
$has_errors = false;

// Additional debug information
error_log("Checking files in directory: $output_dir");
if (is_dir($output_dir)) {
    $files = scandir($output_dir);
    error_log("Files in directory: " . print_r($files, true));
}

// Validate plot files and prepare web paths
foreach ($plots as $key => $filepath) {
    $exists = file_exists($filepath);
    $readable = is_readable($filepath);
    $filesize = $exists ? filesize($filepath) : 0;
    
    $debug_info[$key] = [
        'full_path' => $filepath,
        'web_path' => $web_output_dir . '/' . basename($filepath),
        'exists' => $exists,
        'readable' => $readable,
        'filesize' => $filesize,
        'error_msg' => (!$exists ? 'File does not exist' : (!$readable ? 'File not readable' : ''))
    ];
    
    if (!$exists || !$readable) {
        $plots[$key] = null;
        $has_errors = true;
        error_log("Failed to load plot $key from $filepath - Exists: $exists, Readable: $readable, Size: $filesize bytes");
    } else {
        $plots[$key] = $web_output_dir . '/' . basename($filepath);
        error_log("Successfully loaded plot $key from $filepath - Size: $filesize bytes");
    }
}

// Get cluster data if available
$cluster_data = [];
$cluster_counts = [];
if (file_exists($plots['data'])) {
    $cluster_data = array_map('str_getcsv', file($plots['data']));
    $headers = array_shift($cluster_data);
    
    // Count patients in each cluster
    if ($headers) {
        $cluster_col_index = array_search('Cluster', $headers);
        if ($cluster_col_index !== false) {
            foreach ($cluster_data as $row) {
                if (isset($row[$cluster_col_index])) {
                    $cluster = $row[$cluster_col_index];
                    $cluster_counts[$cluster] = ($cluster_counts[$cluster] ?? 0) + 1;
                }
            }
            ksort($cluster_counts);
        }
    }
}

// Get cluster profiles if available
$cluster_profiles = [];
if (file_exists($plots['profiles'])) {
    $profile_data = array_map('str_getcsv', file($plots['profiles']));
    if (!empty($profile_data)) {
        $profile_headers = array_shift($profile_data);
        foreach ($profile_data as $row) {
            if (!empty($row)) {
                $cluster = $row[0] ?? '';
                $cluster_profiles[$cluster] = [
                    'age_mean' => is_numeric($row[1] ?? null) ? (float)$row[1] : 0,
                    'age_median' => is_numeric($row[2] ?? null) ? (float)$row[2] : 0,
                    'age_std' => is_numeric($row[3] ?? null) ? (float)$row[3] : 0,
                    'age_min' => is_numeric($row[4] ?? null) ? (float)$row[4] : 0,
                    'age_max' => is_numeric($row[5] ?? null) ? (float)$row[5] : 0,
                    'purok_mode' => $row[7] ?? '',
                    'gender_mode' => isset($row[9]) ? ($row[9] == 1 ? 'Male' : 'Female') : '',
                    'male_percent' => is_numeric($row[10] ?? null) ? round((float)$row[10] * 100, 1) : 0
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deworming Patient Cluster Analysis Results</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f9fc;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        h1 {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .plot-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .plot-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .plot-header {
            background: #3498db;
            color: white;
            padding: 10px 15px;
            font-size: 1.1em;
        }
        .plot-content {
            padding: 15px;
        }
        .plot-img {
            width: 100%;
            height: auto;
            border: 1px solid #ddd;
            max-width: 100%;
            display: block;
        }
        .error {
            color: #e74c3c;
            background: #fadbd8;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .interpretation {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        .cluster-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .cluster-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .cluster-distribution {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }
        .cluster-distribution-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            min-width: 80px;
            flex: 1;
        }
        .debug-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 0.9em;
            overflow-x: auto;
        }
        .debug-info h3 {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <h1>Deworming Patient Cluster Analysis Results</h1>
    
    <?php if ($has_errors): ?>
    <div class="error">
        <h3>Warning: Some files could not be loaded</h3>
        <p>Please check the debug information below for details about missing files.</p>
    </div>
    <?php endif; ?>

    <div class="plot-container">
        <!-- Cluster Metrics Comparison -->
        <div class="plot-card">
            <div class="plot-header">Cluster Evaluation Metrics</div>
            <div class="plot-content">
                <?php if ($plots['metrics']): ?>
                    <img src="<?= htmlspecialchars($plots['metrics']) ?>" alt="Cluster metrics comparison" class="plot-img">
                    <p>Comparison of different metrics for determining optimal number of clusters.</p>
                <?php else: ?>
                    <div class="error">
                        Cluster metrics plot not found at: <?= htmlspecialchars($debug_info['metrics']['full_path']) ?>
                        <br>
                        Error: <?= htmlspecialchars($debug_info['metrics']['error_msg']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Silhouette Analysis -->
        <div class="plot-card">
            <div class="plot-header">Silhouette Analysis</div>
            <div class="plot-content">
                <?php if ($plots['silhouette']): ?>
                    <img src="<?= htmlspecialchars($plots['silhouette']) ?>" alt="Silhouette visualization" class="plot-img">
                    <p>Silhouette analysis showing cluster quality and separation.</p>
                <?php else: ?>
                    <div class="error">
                        Silhouette plot not found at: <?= htmlspecialchars($debug_info['silhouette']['full_path']) ?>
                        <br>
                        Error: <?= htmlspecialchars($debug_info['silhouette']['error_msg']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="plot-container">
        <!-- PCA Visualization -->
        <div class="plot-card">
            <div class="plot-header">PCA Cluster Visualization</div>
            <div class="plot-content">
                <?php if ($plots['pca']): ?>
                    <img src="<?= htmlspecialchars($plots['pca']) ?>" alt="PCA cluster visualization" class="plot-img">
                    <p>Principal Component Analysis showing patient clusters in 2D.</p>
                <?php else: ?>
                    <div class="error">
                        PCA plot not found at: <?= htmlspecialchars($debug_info['pca']['full_path']) ?>
                        <br>
                        Error: <?= htmlspecialchars($debug_info['pca']['error_msg']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- t-SNE Visualization -->
        <div class="plot-card">
            <div class="plot-header">t-SNE Cluster Visualization</div>
            <div class="plot-content">
                <?php if ($plots['tsne']): ?>
                    <img src="<?= htmlspecialchars($plots['tsne']) ?>" alt="t-SNE cluster visualization" class="plot-img">
                    <p>t-SNE visualization showing cluster relationships.</p>
                <?php else: ?>
                    <div class="error">
                        t-SNE plot not found at: <?= htmlspecialchars($debug_info['tsne']['full_path']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="plot-container">
        <!-- Cluster Sizes -->
        <div class="plot-card">
            <div class="plot-header">Cluster Distribution</div>
            <div class="plot-content">
                <?php if ($plots['sizes']): ?>
                    <img src="<?= htmlspecialchars($plots['sizes']) ?>" alt="Cluster size distribution" class="plot-img">
                    <p>Distribution of patients across identified clusters.</p>
                <?php else: ?>
                    <div class="error">
                        Cluster sizes plot not found at: <?= htmlspecialchars($debug_info['sizes']['full_path']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="interpretation">
        <h2>Cluster Characteristics Analysis</h2>
        
        <!-- Cluster Distribution Summary -->
        <?php if (!empty($cluster_counts)): ?>
        <h3>Patient Distribution Across Clusters</h3>
        <div class="cluster-distribution">
            <?php foreach ($cluster_counts as $cluster => $count): ?>
                <div class="cluster-distribution-item">
                    <strong>Cluster <?= htmlspecialchars($cluster) ?></strong>
                    <div><?= htmlspecialchars($count) ?> patients</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Cluster Profiles -->
        <?php if (!empty($cluster_profiles)): ?>
        <h3>Cluster Profiles</h3>
        <table>
            <thead>
                <tr>
                    <th>Cluster</th>
                    <th>Avg Age</th>
                    <th>Median Age</th>
                    <th>Age Range</th>
                    <th>Most Common Purok</th>
                    <th>Most Common Gender</th>
                    <th>% Male</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cluster_profiles as $cluster => $profile): ?>
                <tr>
                    <td><?= htmlspecialchars($cluster) ?></td>
                    <td><?= round($profile['age_mean'], 1) ?></td>
                    <td><?= round($profile['age_median'], 1) ?></td>
                    <td><?= round($profile['age_min'], 1) ?>-<?= round($profile['age_max'], 1) ?></td>
                    <td><?= htmlspecialchars($profile['purok_mode']) ?></td>
                    <td><?= htmlspecialchars($profile['gender_mode']) ?></td>
                    <td><?= $profile['male_percent'] ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Debug Information Section -->
    <div class="debug-info">
        <h3>File Access Debug Information</h3>
        <table>
            <tr>
                <th>Plot Type</th>
                <th>Full Path</th>
                <th>Web Path</th>
                <th>Exists</th>
                <th>Readable</th>
                <th>Filesize</th>
                <th>Error</th>
            </tr>
            <?php foreach ($debug_info as $key => $info): ?>
            <tr>
                <td><?= htmlspecialchars($key) ?></td>
                <td><?= htmlspecialchars($info['full_path']) ?></td>
                <td><?= htmlspecialchars($info['web_path']) ?></td>
                <td><?= $info['exists'] ? 'Yes' : 'No' ?></td>
                <td><?= $info['readable'] ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars($info['filesize']) ?> bytes</td>
                <td><?= htmlspecialchars($info['error_msg']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h3>Recommendations</h3>
        <ul>
            <li>Verify the files exist at the specified paths</li>
            <li>Check file permissions (web server needs read access)</li>
            <li>Ensure the web_output_dir ('<?= htmlspecialchars($web_output_dir) ?>') is accessible from your web root</li>
            <li>Confirm the analysis script completed successfully</li>
            <li>Make sure the filenames in $plots array match exactly with the generated files</li>
        </ul>
    </div>
</body>
</html> 
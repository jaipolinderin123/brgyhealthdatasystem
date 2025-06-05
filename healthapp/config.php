<?php
// config.php - Configuration settings

// Database configuration (for future enhancements)
define('DB_HOST', 'sql112.ezyro.com');
define('DB_USER', 'ezyro_39081039');
define('DB_PASS', 'healthdata12345');
define('DB_NAME', 'ezyro_39081039_healthdata');

// Ensure reports directory exists
if (!file_exists('reports')) {
    mkdir('reports', 0755, true);
}

// Set timezone
date_default_timezone_set('Asia/Manila');
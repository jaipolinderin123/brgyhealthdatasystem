<?php
// Database connection
$con = mysqli_connect("sql112.ezyro.com", "ezyro_39081039", "healthdata12345", "ezyro_39081039_healthdata");

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get search term if any
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$where_clause = $search ? "WHERE fullname LIKE '%$search%' OR username LIKE '%$search%'" : '';

// Fetch data
$sql = "SELECT id, fullname, username FROM users $where_clause ORDER BY id DESC";
$result = mysqli_query($con, $sql);

// Set headers based on export type
$type = isset($_GET['type']) ? $_GET['type'] : 'csv';

if ($type == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="users_export.xls"');
} else {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export.csv"');
}

// Open output stream
$output = fopen('php://output', 'w');

// Add headers - using Full Name instead of Name
fputcsv($output, array('ID', 'Full Name', 'Username'), "\t");

// Add data
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, $row, "\t");
}

// Close connection and stream
fclose($output);
mysqli_close($con);
?>
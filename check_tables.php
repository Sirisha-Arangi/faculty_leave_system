<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Connect to database
$conn = connectDB();

if (!$conn) {
    die("Failed to connect to database");
}

// Function to get table structure
function getTableStructure($conn, $tableName) {
    $result = $conn->query("SHOW CREATE TABLE $tableName");
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['Create Table'];
    }
    return "Error: " . $conn->error;
}

// Get all tables
$tables = [
    'class_adjustments',
    'leave_applications',
    'users',
    'leave_types'
];

foreach ($tables as $table) {
    echo "\n\nTable: $table\n";
    echo str_repeat("=", 80) . "\n";
    
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows === 0) {
        echo "Table does not exist.\n";
        continue;
    }
    
    // Get table structure
    echo getTableStructure($conn, $table) . "\n";
    
    // Get sample data (first 2 rows)
    $sample = $conn->query("SELECT * FROM $table LIMIT 2");
    if ($sample && $sample->num_rows > 0) {
        echo "\nSample data (first 2 rows):\n";
        while ($row = $sample->fetch_assoc()) {
            print_r($row);
        }
    } else {
        echo "\nNo data found in table.\n";
    }
}

// Close connection
$conn->close();
?>

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

// Check if table exists
$result = $conn->query("SHOW CREATE TABLE class_adjustments");

if ($result) {
    $row = $result->fetch_assoc();
    echo "Table structure for class_adjustments:\n";
    echo $row['Create Table'] . "\n\n";
    
    // Show first few rows if any
    $data = $conn->query("SELECT * FROM class_adjustments LIMIT 2");
    if ($data && $data->num_rows > 0) {
        echo "Sample data:\n";
        while ($row = $data->fetch_assoc()) {
            print_r($row);
            echo "\n";
        }
    } else {
        echo "No data found in class_adjustments table\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>

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
$tableCheck = $conn->query("SHOW TABLES LIKE 'class_adjustments'");
if ($tableCheck->num_rows === 0) {
    die("The 'class_adjustments' table does not exist in the database.\n");
}

// Get table structure
$result = $conn->query("DESCRIBE class_adjustments");
if (!$result) {
    die("Error describing table: " . $conn->error . "\n");
}

echo "class_adjustments table structure:\n";
echo str_repeat("-", 100) . "\n";
echo sprintf("%-20s | %-20s | %-10s | %-10s | %-10s | %-10s\n", 
    'Field', 'Type', 'Null', 'Key', 'Default', 'Extra');
echo str_repeat("-", 100) . "\n";

while ($row = $result->fetch_assoc()) {
    echo sprintf("%-20s | %-20s | %-10s | %-10s | %-10s | %-10s\n",
        $row['Field'], 
        $row['Type'],
        $row['Null'],
        $row['Key'],
        $row['Default'] ?? 'NULL',
        $row['Extra']
    );
}

// Check foreign key constraints
$result = $conn->query("
    SELECT 
        TABLE_NAME, COLUMN_NAME, 
        CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE 
        TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'class_adjustments' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($result) {
    echo "\nForeign Key Constraints:\n";
    echo str_repeat("-", 100) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['COLUMN_NAME']} references {$row['REFERENCED_TABLE_NAME']}({$row['REFERENCED_COLUMN_NAME']})\n";
    }
}

// Close connection
$conn->close();
?>

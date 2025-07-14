<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'faculty_leave_system';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully to database: " . $db . "<br><br>";

// List all tables
$tables = $conn->query("SHOW TABLES");
echo "Tables in database:<br>";
while ($table = $tables->fetch_array()) {
    echo "- " . $table[0] . "<br>";
    
    // Show columns for each table
    $columns = $conn->query("SHOW COLUMNS FROM " . $table[0]);
    echo "  Columns:<br>";
    while ($column = $columns->fetch_assoc()) {
        echo "  - " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
    echo "<br>";
}

$conn->close();
?>

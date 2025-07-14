<?php
// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any accidental output
ob_start();

// Set JSON content type header
header('Content-Type: application/json');

// Include required files using relative paths
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Function to send JSON response and exit
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Log function for debugging
function logMessage($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message);
}

// Log the request
logMessage("Search faculty request: " . print_r($_GET, true));
logMessage("Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    logMessage("Unauthenticated access attempt");
    sendResponse(['error' => 'Authentication required'], 401);
}

// Check user role
$allowedRoles = ['faculty', 'hod', 'central_admin', 'admin'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
    logMessage("Access denied for role: " . ($_SESSION['role'] ?? 'none'));
    sendResponse(['error' => 'Access denied'], 403);
}

// Get and sanitize input
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Connect to database
$conn = connectDB();
if (!$conn) {
    logMessage("Database connection failed");
    sendResponse(['error' => 'Database connection failed'], 500);
}

logMessage("Database connected successfully");

// Get faculty role ID
try {
    $roleQuery = "SELECT role_id FROM roles WHERE role_name = 'faculty' LIMIT 1";
    $roleResult = $conn->query($roleQuery);
    
    if (!$roleResult) {
        throw new Exception("Error getting faculty role: " . $conn->error);
    }
    
    $roleRow = $roleResult->fetch_assoc();
    $facultyRoleId = $roleRow ? intval($roleRow['role_id']) : 0;
    
    if ($facultyRoleId === 0) {
        throw new Exception("Faculty role not found in database");
    }
    
    logMessage("Faculty role_id: " . $facultyRoleId);
    
} catch (Exception $e) {
    logMessage($e->getMessage());
    sendResponse(['error' => 'Error getting faculty role'], 500);
}

// Build the base query
$query = "SELECT u.user_id, u.first_name, u.last_name, d.dept_name 
          FROM users u
          LEFT JOIN departments d ON u.dept_id = d.dept_id
          WHERE u.role_id = ? 
          AND u.status = 'active'
          AND u.user_id != ?
          AND u.dept_id IS NOT NULL";

// Add search conditions if search term is provided
$params = [$facultyRoleId, $_SESSION['user_id']];
$types = "ii";

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";
    
    logMessage("Search term: " . $search);
}

// Add sorting and pagination
$query .= " ORDER BY u.first_name, u.last_name, d.dept_name";
$query .= " LIMIT ? OFFSET ?";
$params = array_merge($params, [$perPage, $offset]);
$types .= "ii";

logMessage("Final query: $query");
logMessage("Query params: " . print_r($params, true));
logMessage("Param types: $types");

// Execute the query with error handling
try {
    // Prepare the statement
    if (!$stmt = $conn->prepare($query)) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $bindParams = array_merge([$types], $params);
    $refs = [];
    foreach ($bindParams as $key => $value) {
        $refs[$key] = &$bindParams[$key];
    }
    
    if (!empty($refs)) {
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            throw new Exception("Binding parameters failed: " . $stmt->error);
        }
    }
    
    // Execute the query
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Get the result
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Getting result set failed: " . $stmt->error);
    }
    
    // Fetch all faculty
    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = [
            'id' => (int)$row['user_id'],
            'text' => trim($row['first_name'] . ' ' . $row['last_name']) . ' (' . $row['dept_name'] . ')'
        ];
    }
    
    logMessage("Found " . count($faculty) . " faculty members");
    
} catch (Exception $e) {
    logMessage("Database error: " . $e->getMessage());
    sendResponse(['error' => 'Database query failed: ' . $e->getMessage()], 500);
}

// Get total count for pagination (simplified for now, we'll handle pagination on the client side)
$hasMore = count($faculty) >= $perPage;

// Close database connection
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();

// Clean any output before sending JSON
ob_clean();

// Send the response
sendResponse($faculty);

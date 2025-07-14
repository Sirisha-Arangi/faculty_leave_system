<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define environment (set to 'development' or 'production')
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development');
}

// Set log file path
$logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'php_errors.log';

// Create logs directory if it doesn't exist
if (!file_exists($logDir)) {
    if (!mkdir($logDir, 0777, true)) {
        die(json_encode([
            'success' => false,
            'message' => 'Failed to create logs directory. Check permissions.'
        ]));
    }
}

// Create the log file if it doesn't exist
if (!file_exists($logFile)) {
    if (file_put_contents($logFile, '') === false) {
        die(json_encode([
            'success' => false,
            'message' => 'Failed to create error log file. Check permissions.'
        ]));
    }
    chmod($logFile, 0666);
}

// Verify the log file is writable
if (!is_writable($logFile)) {
    die(json_encode([
        'success' => false,
        'message' => 'Error log file is not writable. Check file permissions.'
    ]));
}

// Configure error logging
ini_set('error_log', $logFile);
error_log('\n=== ' . date('Y-m-d H:i:s') . ' Error logging initialized ===');

// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent($file, $line)) {
        error_log("Cannot start session: headers already sent in $file on line $line");
        die(json_encode([
            'success' => false,
            'message' => 'Session start failed: Headers already sent'
        ]));
    }
    session_start();
}

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Function to send JSON response and exit
function sendJsonResponse($data) {
    // Clear any previous output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure we have a valid response structure
    if (!isset($data['success'])) {
        $data = array_merge(['success' => false, 'message' => ''], $data);
    }
    
    // Add debug info in development
    if (!isset($data['debug']) && defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $data['debug'] = [
            'post_data' => $_POST,
            'session_data' => $_SESSION ?? []
        ];
    }
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Encode and output
    $json = json_encode($data);
    if ($json === false) {
        // JSON encoding failed
        $error = json_last_error_msg();
        error_log("JSON encode error: $error");
        $json = json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $error,
            'debug' => defined('ENVIRONMENT') && ENVIRONMENT === 'development' ? [
                'post_data' => $_POST,
                'session_data' => $_SESSION ?? []
            ] : null
        ]);
    }
    
    echo $json;
    exit();
}

// Function to check and create class_adjustments table if it doesn't exist
function ensureClassAdjustmentsTable($conn) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'class_adjustments'");
    if ($tableCheck->num_rows > 0) {
        return true; // Table already exists
    }
    
    // Table doesn't exist, create it
    $sql = "
    CREATE TABLE IF NOT EXISTS `class_adjustments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `application_id` int(11) NOT NULL,
        `adjustment_date` date NOT NULL,
        `adjustment_time` varchar(50) NOT NULL,
        `subject` varchar(100) NOT NULL,
        `adjusted_by` int(11) NOT NULL,
        `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
        `remarks` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `application_id` (`application_id`),
        KEY `adjusted_by` (`adjusted_by`),
        CONSTRAINT `class_adjustments_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `leave_applications` (`application_id`) ON DELETE CASCADE,
        CONSTRAINT `class_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->multi_query($sql)) {
        do {
            // Clear any remaining result sets
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        error_log("Created class_adjustments table successfully");
        return true;
    } else {
        error_log("Error creating class_adjustments table: " . $conn->error);
        return false;
    }
}

// Function to get HOD user ID
function getHodUserId($conn, $userId) {
    try {
        // First, get the department of the current user
        $stmt = $conn->prepare("SELECT department_id FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Failed to prepare department query: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute department query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('User not found');
        }
        
        $user = $result->fetch_assoc();
        $departmentId = $user['department_id'];
        
        if (empty($departmentId)) {
            throw new Exception('User has no department assigned');
        }
        
        // Now find the HOD for this department
        $stmt = $conn->prepare("SELECT u.id FROM users u 
                              JOIN roles r ON u.role_id = r.id 
                              WHERE u.department_id = ? AND r.name = 'HOD' 
                              LIMIT 1");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare HOD query: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $departmentId);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute HOD query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception('No HOD found for department');
        }
        
        $hod = $result->fetch_assoc();
        return $hod['id'];
    } catch (Exception $e) {
        error_log('Error in getHodUserId: ' . $e->getMessage());
        return null;
    }
}

// Main script execution
try {
    // Log script start
    error_log('\n=== ' . date('Y-m-d H:i:s') . ' Script started ===');
    error_log('PHP Version: ' . phpversion());
    error_log('Current directory: ' . __DIR__);
    error_log('Log file: ' . $logFile);
    error_log('Session status: ' . session_status());
    error_log('Session ID: ' . session_id());
    error_log('POST data: ' . print_r($_POST, true));
    error_log('Session data: ' . print_r($_SESSION, true));
    
    // Initialize response array
    $response = [
        'success' => false,
        'message' => '',
        'errors' => []
    ];
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    // Get form data with validation
    $permissionDate = isset($_POST['permission_date']) ? trim($_POST['permission_date']) : '';
    $permissionSlot = isset($_POST['permission_slot']) ? trim($_POST['permission_slot']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $adjustmentsJson = isset($_POST['adjustments']) ? $_POST['adjustments'] : '';
    $userId = $_SESSION['user_id'];
    
    // Convert date from d-m-Y to Y-m-d format for database
    $mysqlPermissionDate = '';
    if (!empty($permissionDate)) {
        $dateObj = DateTime::createFromFormat('d-m-Y', $permissionDate);
        if ($dateObj) {
            $mysqlPermissionDate = $dateObj->format('Y-m-d');
        } else {
            error_log("Invalid date format: $permissionDate");
            throw new Exception('Invalid date format. Please use DD-MM-YYYY format.');
        }
    }
    
    // Validate required fields
    $requiredFields = [
        'permission_date' => 'Permission date',
        'permission_slot' => 'Permission slot',
        'reason' => 'Reason'
    ];
    
    $missingFields = [];
    foreach ($requiredFields as $field => $name) {
        if (empty($_POST[$field])) {
            $missingFields[] = $name;
        }
    }
    
    // Check if adjustments are provided and valid JSON
    if (!isset($_POST['adjustments'])) {
        $missingFields[] = 'Class adjustments';
    } else {
        $adjustments = json_decode($_POST['adjustments'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($adjustments)) {
            throw new Exception('Invalid adjustments data. Please provide valid class adjustment details.');
        }
        if (empty($adjustments)) {
            $missingFields[] = 'At least one class adjustment is required';
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
    }
    
    // Get database connection
    $conn = connectDB();
    if (!$conn) {
        throw new Exception('Failed to connect to database');
    }
    
    // Get permission leave type ID
    $result = $conn->query("SELECT type_id FROM leave_types WHERE type_name = 'permission_leave' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        throw new Exception('Permission leave type not found in database');
    }
    
    $permissionType = $result->fetch_assoc();
    $leaveTypeId = $permissionType['type_id'];
    
    // Start database transaction
    error_log('Starting database transaction...');
    if (!$conn->begin_transaction()) {
        throw new Exception('Failed to start transaction: ' . $conn->error);
    }
    
    try {
        // Insert leave application
        $stmt = $conn->prepare("INSERT INTO leave_applications 
            (user_id, leave_type_id, start_date, end_date, reason, status, is_permission, permission_slot) 
            VALUES (?, ?, ?, ?, ?, 'pending', 1, ?)");
            
        if (!$stmt) {
            throw new Exception('Failed to prepare leave application statement: ' . $conn->error);
        }
        
        // Use the converted date for database
        $startDate = $mysqlPermissionDate;
        $endDate = $mysqlPermissionDate; // Same as start date for permission leave
        
        $stmt->bind_param("iissss", $userId, $leaveTypeId, $startDate, $endDate, $reason, $permissionSlot);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute leave application statement: ' . $stmt->error);
        }
        
        $leaveId = $conn->insert_id;
        error_log("Inserted leave application with ID: $leaveId");
        
        // Process class adjustments
        $adjustments = json_decode($adjustmentsJson, true);
        if (!empty($adjustments) && is_array($adjustments)) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid adjustments data: ' . json_last_error_msg());
            }
            
            // Ensure the class_adjustments table exists
            if (!ensureClassAdjustmentsTable($conn)) {
                throw new Exception('Failed to verify or create the class_adjustments table');
            }
            
            // Get the current timestamp for created_at
            $currentTime = date('Y-m-d H:i:s');
            
            // Prepare the SQL query with correct column names from the database schema
            $sql = "INSERT INTO class_adjustments 
                (application_id, class_date, class_time, subject, adjusted_by, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')";
                
            error_log("Preparing SQL: $sql");
            $stmt = $conn->prepare($sql);
                
            if (!$stmt) {
                $error = $conn->error;
                error_log("SQL Error: $error");
                error_log("SQL State: " . $conn->sqlstate);
                error_log("Error Code: " . $conn->errno);
                
                // Try to get more detailed error information
                if (method_exists($conn, 'error_list')) {
                    $errors = $conn->error_list;
                    error_log("Error List: " . print_r($errors, true));
                }
                
                throw new Exception('Failed to prepare class adjustment statement: ' . $error);
            }
            
            foreach ($adjustments as $index => $adjustment) {
                // Convert adjustment date to Y-m-d format
                $adjDate = $adjustment['date'] ?? '';
                $date = '';
                if (!empty($adjDate)) {
                    $dateObj = DateTime::createFromFormat('d-m-Y', $adjDate);
                    if ($dateObj) {
                        $date = $dateObj->format('Y-m-d');
                    } else {
                        error_log("Invalid adjustment date format: $adjDate");
                        continue; // Skip this adjustment if date is invalid
                    }
                }
                $time = $adjustment['time'] ?? '';
                $subject = $adjustment['subject'] ?? '';
                $facultyId = $adjustment['faculty_id'] ?? '';
                
                if (empty($date) || empty($time) || empty($subject) || empty($facultyId)) {
                    error_log("Skipping invalid adjustment at index $index: " . print_r($adjustment, true));
                    continue;
                }
                
                // Bind parameters with correct types and order
                // Note: Removed $currentTime as it's not needed (auto-timestamped by the database)
                $stmt->bind_param("isssi", $leaveId, $date, $time, $subject, $facultyId);
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert class adjustment: ' . $stmt->error);
                }
                
                error_log("Inserted class adjustment for faculty ID: $facultyId on $date at $time");
            }
        }
        
        // Send notification to HOD
        $userName = $_SESSION['name'] ?? 'Unknown';
        $formattedDate = date('d-m-Y', strtotime($permissionDate));
        $notificationMessage = "New permission leave request from $userName for $formattedDate (" . ucfirst($permissionSlot) . ")";
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, status) 
                              VALUES (?, 'New Permission Leave Request', ?, 'permission_leave', 'unread')");
        
        if ($stmt) {
            // Get HOD user ID
            $hodUserId = getHodUserId($conn, $userId);
            
            if ($hodUserId) {
                $stmt->bind_param("is", $hodUserId, $notificationMessage);
                
                if (!$stmt->execute()) {
                    error_log('Failed to send notification: ' . $stmt->error);
                    // Don't fail the whole request if notification fails
                } else {
                    error_log("Notification sent to HOD (User ID: $hodUserId)");
                }
            } else {
                error_log('Could not find HOD to send notification');
            }
        } else {
            error_log('Failed to prepare notification statement: ' . $conn->error);
        }
        
        // Commit transaction
        if (!$conn->commit()) {
            throw new Exception('Failed to commit transaction: ' . $conn->error);
        }
        
        // Success response
        $response = [
            'success' => true,
            'message' => 'Permission leave request submitted successfully',
            'redirect' => 'leave_status.php'
        ];
        
        sendJsonResponse($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }
        throw $e; // Re-throw to be caught by the outer catch block
    }
    
} catch (Exception $e) {
    // Handle any uncaught exceptions
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    
    $response = [
        'success' => false,
        'message' => 'An error occurred while processing your request',
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
        ]
    ];
    
    sendJsonResponse($response);
}

// Close database connection if it exists
if (isset($conn)) {
    $conn->close();
}

exit();

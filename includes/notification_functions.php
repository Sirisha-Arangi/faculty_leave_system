<?php
/**
 * Notification Functions
 * 
 * This file contains functions related to notifications in the leave management system.
 */

/**
 * Create a notification for a user
 * 
 * @param int $userId The ID of the user to notify
 * @param string $title The title of the notification
 * @param string $message The message content of the notification
 * @param string $type The type of notification (e.g., 'leave_request', 'leave_approved', etc.)
 * @param string $status The status of the notification (default: 'unread')
 * @param string $link Optional link for the notification
 * @return bool|int The ID of the created notification or false on failure
 */
function createNotification($userId, $title, $message, $type = 'info', $status = 'unread', $link = '') {
    $conn = connectDB();
    
    // Sanitize inputs
    $userId = (int)$userId;
    $title = $conn->real_escape_string(trim($title));
    $message = $conn->real_escape_string(trim($message));
    $type = $conn->real_escape_string(trim($type));
    $status = $conn->real_escape_string(trim($status));
    $link = $conn->real_escape_string(trim($link));
    
    $createdAt = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO notifications 
            (user_id, title, message, type, status, link, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare notification statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issssss", $userId, $title, $message, $type, $status, $link, $createdAt);
    
    if ($stmt->execute()) {
        $notificationId = $stmt->insert_id;
        $stmt->close();
        return $notificationId;
    } else {
        error_log("Failed to create notification: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Get unread notifications for a user
 * 
 * @param int $userId The ID of the user
 * @param int $limit Maximum number of notifications to return (0 for no limit)
 * @return array Array of notification records
 */
function getUnreadNotifications($userId, $limit = 0) {
    $conn = connectDB();
    $userId = (int)$userId;
    
    $sql = "SELECT * FROM notifications 
            WHERE user_id = ? AND status = 'unread' 
            ORDER BY created_at DESC";
            
    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare get notifications statement: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Mark a notification as read
 * 
 * @param int $notificationId The ID of the notification to mark as read
 * @return bool True on success, false on failure
 */
function markNotificationAsRead($notificationId) {
    $conn = connectDB();
    $notificationId = (int)$notificationId;
    
    $sql = "UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare mark as read statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $notificationId);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $userId The ID of the user
 * @return bool True on success, false on failure
 */
function markAllNotificationsAsRead($userId) {
    $conn = connectDB();
    $userId = (int)$userId;
    
    $sql = "UPDATE notifications SET status = 'read', read_at = NOW() 
            WHERE user_id = ? AND status = 'unread'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare mark all as read statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Count unread notifications for a user
 * 
 * @param int $userId The ID of the user
 * @return int Number of unread notifications
 */
function countUnreadNotifications($userId) {
    $conn = connectDB();
    $userId = (int)$userId;
    
    $sql = "SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND status = 'unread'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare count unread notifications statement: " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)$row['count'];
}

/**
 * Delete a notification
 * 
 * @param int $notificationId The ID of the notification to delete
 * @param int $userId Optional user ID to verify ownership
 * @return bool True on success, false on failure
 */
function deleteNotification($notificationId, $userId = null) {
    $conn = connectDB();
    $notificationId = (int)$notificationId;
    
    $sql = "DELETE FROM notifications WHERE id = ?";
    if ($userId !== null) {
        $sql .= " AND user_id = " . (int)$userId;
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare delete notification statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $notificationId);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get notifications for a user with pagination
 * 
 * @param int $userId The ID of the user
 * @param int $page Page number (1-based)
 * @param int $perPage Number of items per page
 * @return array Array containing 'notifications' and 'total_pages'
 */
function getNotificationsPaginated($userId, $page = 1, $perPage = 10) {
    $conn = connectDB();
    $userId = (int)$userId;
    $page = max(1, (int)$page);
    $perPage = max(1, (int)$perPage);
    $offset = ($page - 1) * $perPage;
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $userId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($total / $perPage);
    $countStmt->close();
    
    // Get paginated notifications
    $sql = "SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare paginated notifications statement: " . $conn->error);
        return ['notifications' => [], 'total_pages' => 0];
    }
    
    $stmt->bind_param("iii", $userId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    
    return [
        'notifications' => $notifications,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total
    ];
}

/**
 * Send an email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message body (HTML)
 * @param string $from Sender email address
 * @return bool True on success, false on failure
 */
function sendEmailNotification($to, $subject, $message, $from = 'noreply@example.com') {
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    
    // Send email
    return mail($to, $subject, $message, $headers);
}

/**
 * Get notification settings for a user
 * 
 * @param int $userId The ID of the user
 * @return array Notification settings
 */
function getNotificationSettings($userId) {
    $conn = connectDB();
    $userId = (int)$userId;
    
    // Default settings
    $defaultSettings = [
        'email_notifications' => 1,
        'browser_notifications' => 1,
        'notify_on_approval' => 1,
        'notify_on_rejection' => 1,
        'notify_on_comment' => 1,
        'notify_on_new_leave' => 0, // HODs and admins only
        'notify_on_holiday' => 1
    ];
    
    // Try to get user's custom settings
    $sql = "SELECT settings FROM notification_settings WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userSettings = json_decode($row['settings'], true);
            if (is_array($userSettings)) {
                // Merge with defaults (user settings take precedence)
                return array_merge($defaultSettings, $userSettings);
            }
        }
        
        $stmt->close();
    }
    
    return $defaultSettings;
}

/**
 * Update notification settings for a user
 * 
 * @param int $userId The ID of the user
 * @param array $settings Array of settings to update
 * @return bool True on success, false on failure
 */
function updateNotificationSettings($userId, $settings) {
    $conn = connectDB();
    $userId = (int)$userId;
    
    // Get current settings
    $currentSettings = getNotificationSettings($userId);
    
    // Merge with new settings
    $newSettings = array_merge($currentSettings, $settings);
    $settingsJson = json_encode($newSettings);
    
    // Check if settings exist for this user
    $checkSql = "SELECT id FROM notification_settings WHERE user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    if ($checkResult->num_rows > 0) {
        // Update existing settings
        $sql = "UPDATE notification_settings SET settings = ? WHERE user_id = ?";
    } else {
        // Insert new settings
        $sql = "INSERT INTO notification_settings (user_id, settings) VALUES (?, ?)";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare update notification settings statement: " . $conn->error);
        return false;
    }
    
    if ($checkResult->num_rows > 0) {
        $stmt->bind_param("si", $settingsJson, $userId);
    } else {
        $stmt->bind_param("is", $userId, $settingsJson);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Log a notification event (for debugging and auditing)
 * 
 * @param string $eventType Type of event
 * @param string $description Description of the event
 * @param int $userId Optional user ID related to the event
 * @param array $data Optional additional data to log
 * @return bool True on success, false on failure
 */
function logNotificationEvent($eventType, $description, $userId = null, $data = []) {
    $conn = connectDB();
    
    $eventType = $conn->real_escape_string(trim($eventType));
    $description = $conn->real_escape_string(trim($description));
    $userId = $userId !== null ? (int)$userId : 'NULL';
    $dataJson = !empty($data) ? $conn->real_escape_string(json_encode($data)) : 'NULL';
    
    $sql = "INSERT INTO notification_logs 
            (event_type, description, user_id, data, created_at) 
            VALUES (?, ?, $userId, $dataJson, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare log notification event statement: " . $conn->error);
        return false;
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Clean up old notifications
 * 
 * @param int $daysOld Delete notifications older than this many days (default: 90)
 * @return bool True on success, false on failure
 */
function cleanupOldNotifications($daysOld = 90) {
    $conn = connectDB();
    $daysOld = max(1, (int)$daysOld);
    
    $sql = "DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status = 'read'";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare cleanup old notifications statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $daysOld);
    $result = $stmt->execute();
    $deletedCount = $stmt->affected_rows;
    $stmt->close();
    
    if ($deletedCount > 0) {
        error_log("Cleaned up $deletedCount old notifications");
    }
    
    return $result;
}
?>

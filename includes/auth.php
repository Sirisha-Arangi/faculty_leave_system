<?php
// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Check if user has admin role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user has HOD role
function isHOD() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hod';
}

// Check if user has faculty role
function isFaculty() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'faculty';
}

// Require admin access
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        die('Access Denied: You do not have permission to access this page.');
    }
}

// Require HOD access
function requireHOD() {
    requireLogin();
    if (!isHOD() && !isAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        die('Access Denied: You do not have permission to access this page.');
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Logout function
function logout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit();
}
?>

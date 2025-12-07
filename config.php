<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'farm_management');

// Start session
session_start();

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user type
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Check access permissions
function checkAccess($requiredType) {
    $userType = getUserType();
    
    if ($userType === 'owner') {
        return true; // Owner has access to everything
    }
    
    if ($requiredType === 'poultry' && $userType === 'poultry_manager') {
        return true;
    }
    
    if ($requiredType === 'ruminant' && $userType === 'ruminant_manager') {
        return true;
    }
    
    return false;
}

// Get farm type for current user
function getUserFarmType() {
    $userType = getUserType();
    if ($userType === 'poultry_manager') return 'poultry';
    if ($userType === 'ruminant_manager') return 'ruminant';
    return 'both'; // owner
}
?>
<?php
/**
 * Logout Handler
 * Deliverance Church Management System
 * 
 * Handles user logout and session cleanup
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Clear remember me cookie and token
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db = Database::getInstance();
            $db->executeQuery(
                "UPDATE users SET remember_token = NULL WHERE id = ?",
                [$_SESSION['user_id']]
            );
        } catch (Exception $e) {
            error_log("Error clearing remember token: " . $e->getMessage());
        }
        
        // Clear the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Log the logout activity
    logActivity('User logged out');
    
    // Destroy the session
    destroyUserSession();
}

// Redirect to login page with logout message
$redirect_param = isset($_GET['expired']) ? '?expired=1' : '?logout=1';
header('Location: ' . BASE_URL . 'auth/login.php' . $redirect_param);
exit();
?>
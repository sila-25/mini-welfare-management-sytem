<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Store the user role before destroying session
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    
    // Clear token from database
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {
            // Table might not exist
        }
    }
}

// Log the logout action
if (isset($_SESSION['user_id'])) {
    auditLog('logout', 'users', $_SESSION['user_id']);
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect based on user role
if ($user_role === 'super_admin') {
    // Admin logged out - redirect to admin login
    header('Location: ' . APP_URL . '/admin/login.php?logout=1');
} else {
    // Regular user logged out - redirect to user login
    header('Location: ' . APP_URL . '/pages/login.php?logout=1');
}
exit();
?>
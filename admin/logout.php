<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (Exception $e) {}
    }
}

// Log the logout action
if (isset($_SESSION['user_id'])) {
    auditLog('logout', 'users', $_SESSION['user_id']);
}

// Destroy session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to admin login
header('Location: ' . APP_URL . '/admin/login.php?logout=1');
exit();
?>
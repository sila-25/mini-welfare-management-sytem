<?php
require_once __DIR__ . '/functions.php';

class Middleware {
    
    public static function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            // Check remember token
            if (isset($_COOKIE['remember_token'])) {
                try {
                    $stmt = db()->prepare("SELECT * FROM users WHERE remember_token = ?");
                    $stmt->execute([$_COOKIE['remember_token']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['group_id'] = $user['group_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['login_time'] = time();
                        return;
                    }
                } catch (Exception $e) {
                    // Token invalid or table doesn't exist
                }
            }
            
            header('Location: /careway/pages/login.php');
            exit();
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
            session_destroy();
            header('Location: /careway/pages/login.php?timeout=1');
            exit();
        }
        
        // Refresh session time
        $_SESSION['login_time'] = time();
    }
    
    public static function requireGroupAccess() {
        self::requireLogin();
        
        // Skip subscription checks for super admin
        if ($_SESSION['user_role'] === 'super_admin') {
            return true;
        }
        
        // For now, allow all access
        return true;
    }
    
    public static function csrfProtection() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                http_response_code(403);
                die('CSRF token validation failed.');
            }
        }
    }
}
?>
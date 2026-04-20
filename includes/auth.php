<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class Auth {
    
    public static function login($username, $password, $remember = false) {
        // Check for too many attempts
        if (self::isLoginThrottled($username)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        // Get user by username or email
        $stmt = db()->prepare("
            SELECT u.*, g.group_name, g.subscription_status 
            FROM users u
            LEFT JOIN groups g ON u.group_id = g.id
            WHERE u.username = ? OR u.email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordFailedAttempt($username);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
        
        // Check if user is active
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is inactive. Please contact administrator.'];
        }
        
        // Check group subscription for non-super admins
        if ($user['role'] !== 'super_admin' && $user['group_id']) {
            if ($user['subscription_status'] !== 'active') {
                return ['success' => false, 'message' => 'Group subscription has expired. Please renew to continue.'];
            }
        }
        
        // Update last login
        $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Clear failed attempts
        self::clearFailedAttempts($username);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['group_id'] = $user['group_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['login_time'] = time();
        
        // Set remember me cookie
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $stmt = db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            setcookie('remember_token', $token, time() + 86400 * 30, '/', '', true, true);
        }
        
        // Log audit
        auditLog('login', 'users', $user['id'], null, ['username' => $user['username']]);
        
        return ['success' => true, 'user' => $user];
    }
    
    public static function logout() {
        if (isLoggedIn()) {
            auditLog('logout', 'users', $_SESSION['user_id']);
            
            // Clear remember token
            if (isset($_COOKIE['remember_token'])) {
                $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            }
        }
        
        session_destroy();
        return true;
    }
    
    public static function register($data) {
        // Validate data
        $errors = [];
        
        if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = "Passwords do not match.";
        }
        
        // Check if username exists
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists.";
        }
        
        // Check if email exists
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered.";
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        // Create user
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = db()->prepare("
            INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, phone, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['group_id'] ?? null,
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null,
            $data['role'] ?? 'member'
        ]);
        
        if ($success) {
            $userId = db()->lastInsertId();
            auditLog('register', 'users', $userId, null, ['username' => $data['username']]);
            return ['success' => true, 'user_id' => $userId];
        }
        
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
    
    private static function isLoginThrottled($username) {
        $stmt = db()->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$username, $_SERVER['REMOTE_ADDR']]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= MAX_LOGIN_ATTEMPTS;
    }
    
    private static function recordFailedAttempt($username) {
        $stmt = db()->prepare("
            INSERT INTO login_attempts (username, ip_address, user_agent)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    }
    
    private static function clearFailedAttempts($username) {
        $stmt = db()->prepare("DELETE FROM login_attempts WHERE username = ? OR ip_address = ?");
        $stmt->execute([$username, $_SERVER['REMOTE_ADDR']]);
    }
    
    public static function checkRememberToken() {
        if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
            $token = $_COOKIE['remember_token'];
            $stmt = db()->prepare("
                SELECT * FROM users 
                WHERE remember_token = ? AND status = 'active'
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['group_id'] = $user['group_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_time'] = time();
                
                return true;
            }
        }
        return false;
    }
    
    public static function changePassword($userId, $oldPassword, $newPassword) {
        $stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'];
        }
        
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        auditLog('change_password', 'users', $userId);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}
?>
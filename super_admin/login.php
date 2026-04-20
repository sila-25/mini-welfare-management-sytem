<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// If already logged in as super admin, redirect to super admin dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin') {
    header('Location: ' . APP_URL . '/super_admin/dashboard.php');
    exit();
}

// If logged in as group admin, redirect to group admin dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'chairperson') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit();
}

// If logged in as regular member, redirect to member dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'super_admin' && $_SESSION['user_role'] !== 'chairperson') {
    header('Location: ' . APP_URL . '/dashboard/home.php');
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Get user by username or email with role super_admin
        $stmt = db()->prepare("
            SELECT * FROM users 
            WHERE (username = ? OR email = ?) AND role = 'super_admin'
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid super admin credentials.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is inactive. Please contact system administrator.';
        } else {
            // Update last login
            $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['group_id'] = $user['group_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['login_time'] = time();
            
            // Redirect to super admin dashboard
            header('Location: ' . APP_URL . '/super_admin/dashboard.php');
            exit();
        }
    }
}

$page_title = 'Super Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Super Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #dc3545;
            --secondary-color: #c82333;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .login-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .super-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
        }
        
        .form-control {
            border-left: none;
            padding: 12px 15px;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #dc3545;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            color: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.4);
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .help-text {
            text-align: center;
            margin-top: 20px;
        }
        
        .help-text a {
            color: #dc3545;
            text-decoration: none;
        }
        
        .help-text a:hover {
            text-decoration: underline;
        }
        
        .credentials-hint {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        .credentials-hint p {
            margin-bottom: 5px;
        }
        
        .credentials-hint code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="super-badge">
                    <i class="fas fa-crown me-1"></i> Super Admin
                </div>
                <i class="fas fa-crown fa-3x mb-3"></i>
                <h1>Super Admin Login</h1>
                <p>Careway Platform Management</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout']) && $_GET['logout'] == 1): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-sign-out-alt me-2"></i>You have been logged out successfully.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['unauthorized']) && $_GET['unauthorized'] == 1): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Unauthorized access. Please login as super administrator.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-user me-2"></i>Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="text" class="form-control" name="username" placeholder="Enter username or email" required autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock me-2"></i>Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Enter password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Login to System Admin
                    </button>
                </form>
                
                <div class="credentials-hint">
                    <p><i class="fas fa-info-circle me-1"></i> <strong>Demo Credentials:</strong></p>
                    <p><strong>Username:</strong> <code>super_admin</code></p>
                    <p><strong>Password:</strong> <code>SuperAdmin2024!</code></p>
                </div>
                
                <div class="help-text">
                    <p><a href="<?php echo APP_URL; ?>/admin/login.php">Group Admin Login</a> | <a href="<?php echo APP_URL; ?>/pages/login.php">Member Login</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
        }
    </script>
</body>
</html>
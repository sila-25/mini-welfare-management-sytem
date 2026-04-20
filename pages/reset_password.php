<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'super_admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/dashboard/home.php');
    }
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Validate token
if (empty($token)) {
    redirect('/pages/login.php');
}

// Check if token is valid
$stmt = db()->prepare("
    SELECT pr.*, u.id as user_id, u.username, u.email, u.first_name, u.last_name
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = "Invalid or expired reset token. Please request a new password reset.";
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = 'Please enter a new password';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            db()->beginTransaction();
            
            // Update user password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $reset['user_id']]);
            
            // Mark token as used
            $stmt = db()->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Audit log
            auditLog('password_reset', 'users', $reset['user_id']);
            
            db()->commit();
            
            // Send confirmation email
            $subject = "Password Reset Successful - " . APP_NAME;
            $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f8f9fa; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Reset Successful</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$reset['first_name']} {$reset['last_name']},</p>
                            <p>Your password has been successfully reset.</p>
                            <p>You can now login with your new password.</p>
                            <p>If you did not perform this action, please contact support immediately.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            sendEmail($reset['email'], $subject, $body);
            
            $success = true;
            
        } catch (Exception $e) {
            db()->rollback();
            $error = "An error occurred. Please try again.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

$page_title = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
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
        
        .reset-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .reset-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .reset-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: white;
            border-radius: 10px;
            transition: transform 0.3s;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card">
            <div class="reset-header">
                <i class="fas fa-lock fa-3x mb-3"></i>
                <h1>Reset Password</h1>
                <p>Create a new password</p>
            </div>
            <div class="reset-body">
                <?php if ($success): ?>
                    <div class="text-center">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Your password has been reset successfully!
                        </div>
                        <p class="text-muted">You can now login with your new password.</p>
                        <a href="login.php" class="btn btn-primary mt-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Now
                        </a>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="forgot_password.php" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Request New Reset Link
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-4">
                        Please enter your new password for account: <strong><?php echo htmlspecialchars($reset['username']); ?></strong>
                    </p>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label><i class="fas fa-lock me-2"></i>New Password</label>
                            <input type="password" class="form-control" name="password" id="password" required onkeyup="checkPasswordStrength()">
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock me-2"></i>Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-reset">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let message = '';
            let className = '';
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                message = 'Weak password';
                className = 'strength-weak';
            } else if (strength <= 4) {
                message = 'Medium password';
                className = 'strength-medium';
            } else {
                message = 'Strong password';
                className = 'strength-strong';
            }
            
            strengthDiv.innerHTML = '<i class="fas fa-shield-alt me-1"></i>' + message;
            strengthDiv.className = 'password-strength ' + className;
        }
    </script>
</body>
</html>
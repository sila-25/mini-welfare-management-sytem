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

$message = '';
$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if user exists
        $stmt = db()->prepare("SELECT id, username, first_name, last_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = db()->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$user['id'], $token, $expires]);
            
            // Send reset email
            $resetLink = APP_URL . "/pages/reset_password.php?token=" . $token;
            $subject = "Password Reset Request - " . APP_NAME;
            $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f8f9fa; }
                        .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Reset Request</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$user['first_name']} {$user['last_name']},</p>
                            <p>We received a request to reset your password for your " . APP_NAME . " account.</p>
                            <p>Click the button below to reset your password:</p>
                            <div style='text-align: center;'>
                                <a href='{$resetLink}' class='button'>Reset Password</a>
                            </div>
                            <p>This link will expire in 1 hour.</p>
                            <p>If you did not request a password reset, please ignore this email.</p>
                            <hr>
                            <p><strong>Account Details:</strong><br>
                            Username: {$user['username']}<br>
                            Email: {$email}</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            sendEmail($email, $subject, $body);
            $success = true;
            $message = "Password reset instructions have been sent to your email address.";
        } else {
            // Don't reveal that email doesn't exist for security
            $success = true;
            $message = "If an account exists with that email, password reset instructions have been sent.";
        }
    }
}

$page_title = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Forgot Password</title>
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
        
        .forgot-card {
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
        
        .forgot-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .forgot-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .forgot-body {
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
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-card">
            <div class="forgot-header">
                <i class="fas fa-key fa-3x mb-3"></i>
                <h1>Forgot Password</h1>
                <p>We'll help you reset it</p>
            </div>
            <div class="forgot-body">
                <?php if ($success): ?>
                    <div class="text-center">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-envelope me-2"></i><?php echo $message; ?>
                        </div>
                        <a href="login.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>Return to Login
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label><i class="fas fa-envelope me-2"></i>Email Address</label>
                            <input type="email" class="form-control" name="email" required placeholder="Enter your registered email" autofocus>
                        </div>
                        
                        <button type="submit" class="btn btn-reset">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                        </button>
                    </form>
                    
                    <div class="back-link">
                        <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
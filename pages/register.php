<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'super_admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/dashboard/home.php');
    }
}

$errors = [];
$form_data = [];

// Get group code from URL if provided
$group_code = $_GET['group_code'] ?? '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = $_POST;
    $group_code = strtoupper(trim($_POST['group_code'] ?? ''));
    
    // Validate inputs
    if (empty($_POST['first_name'])) {
        $errors[] = 'First name is required';
    }
    if (empty($_POST['last_name'])) {
        $errors[] = 'Last name is required';
    }
    if (empty($_POST['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($_POST['username'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($_POST['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    if (empty($_POST['password'])) {
        $errors[] = 'Password is required';
    } elseif (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $errors[] = 'Passwords do not match';
    }
    if (empty($group_code)) {
        $errors[] = 'Group code is required';
    }
    
    // Verify group code
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT id, group_name FROM groups WHERE group_code = ? AND subscription_status = 'active'");
        $stmt->execute([$group_code]);
        $group = $stmt->fetch();
        
        if (!$group) {
            $errors[] = 'Invalid or inactive group code. Please check and try again.';
        } else {
            // Check if user already exists in this group
            $stmt = db()->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND group_id = ?");
            $stmt->execute([$_POST['username'], $_POST['email'], $group['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists in this group';
            }
        }
    }
    
    // Register user
    if (empty($errors)) {
        try {
            db()->beginTransaction();
            
            $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Create user account (default role is 'member')
            $stmt = db()->prepare("
                INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, phone, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'member', 'active')
            ");
            
            $stmt->execute([
                $group['id'],
                $_POST['username'],
                $_POST['email'],
                $passwordHash,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['phone'] ?? null
            ]);
            
            $userId = db()->lastInsertId();
            
            // Create member record
            $memberNumber = generateMemberNumber($group['id']);
            $stmt = db()->prepare("
                INSERT INTO members (group_id, user_id, member_number, first_name, last_name, email, phone, join_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')
            ");
            $stmt->execute([
                $group['id'],
                $userId,
                $memberNumber,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'] ?? null
            ]);
            
            auditLog('register', 'users', $userId, null, ['username' => $_POST['username'], 'group_id' => $group['id']]);
            
            db()->commit();
            
            // Send welcome email
            $subject = "Welcome to {$group['group_name']} - " . APP_NAME;
            $loginUrl = APP_URL . "/pages/login.php";
            $body = "
                <html>
                <head><style>body{font-family:Arial,sans-serif;}</style></head>
                <body>
                    <h3>Welcome to {$group['group_name']}!</h3>
                    <p>Dear {$_POST['first_name']} {$_POST['last_name']},</p>
                    <p>Your account has been created successfully.</p>
                    <p><strong>Group Code:</strong> {$group_code}</p>
                    <p><strong>Username:</strong> {$_POST['username']}</p>
                    <p><strong>Member Number:</strong> {$memberNumber}</p>
                    <p><a href='{$loginUrl}'>Click here to login</a></p>
                </body>
                </html>
            ";
            sendEmail($_POST['email'], $subject, $body);
            
            redirect('/pages/login.php?registered=1');
            
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Register</title>
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
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        
        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 550px;
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
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .register-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-control {
            padding: 10px 15px;
            border-radius: 10px;
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            color: white;
            border-radius: 10px;
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .text-muted small {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <i class="fas fa-user-plus fa-3x mb-3"></i>
                <h1>Create Account</h1>
                <p>Join your welfare group</p>
            </div>
            <div class="register-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required>
                        <small class="text-muted">Username must be unique within your group</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                        <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Group Code *</label>
                        <input type="text" class="form-control text-uppercase" name="group_code" value="<?php echo htmlspecialchars($group_code); ?>" required placeholder="Enter your group's unique code">
                        <small class="text-muted">Ask your group administrator for the group code</small>
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i>Register Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
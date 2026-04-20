<?php
// This script will create the super admin with correct password hash
// Run this file once, then delete it

require_once 'includes/config.php';
require_once 'includes/db.php';

// Configuration
$username = 'super_admin';
$email = 'superadmin@careway.com';
$password = 'SuperAdmin2024!';
$firstName = 'System';
$lastName = 'Administrator';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Super Admin Setup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; margin-top: 0; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #c82333; }
        hr { margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Super Admin Setup</h1>";

try {
    // Delete existing super admin
    $stmt = db()->prepare("DELETE FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    echo "<p>✓ Removed existing super admin accounts</p>";
    
    // Generate correct password hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new super admin
    $stmt = db()->prepare("
        INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'super_admin', 'active', NOW())
    ");
    $stmt->execute([$username, $email, $hash, $firstName, $lastName]);
    
    // Verify creation
    $stmt = db()->prepare("SELECT id, username, email, role FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p class='success'>✓ Super admin created successfully!</p>";
        echo "<div class='info'>";
        echo "<strong>Login Credentials:</strong><br><br>";
        echo "<strong>URL:</strong> <code>" . APP_URL . "/super_admin/login.php</code><br>";
        echo "<strong>Username:</strong> <code>super_admin</code><br>";
        echo "<strong>Password:</strong> <code>SuperAdmin2024!</code><br>";
        echo "<strong>Email:</strong> <code>superadmin@careway.com</code><br>";
        echo "</div>";
        echo "<hr>";
        echo "<p><strong>Security Note:</strong> Please change this password after first login.</p>";
        echo "<a href='" . APP_URL . "/super_admin/login.php' class='btn'>Go to Super Admin Login →</a>";
    } else {
        echo "<p class='error'>✗ Failed to create super admin</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "
    </div>
</body>
</html>";
?>
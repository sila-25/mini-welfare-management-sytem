<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>Careway Admin Setup</h1>";

try {
    // Create default group if none exists
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM groups");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = db()->prepare("
            INSERT INTO groups (group_name, group_code, email, subscription_plan, subscription_status, subscription_start, subscription_end) 
            VALUES ('Default Group', 'DEF001', 'default@careway.com', 'monthly', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))
        ");
        $stmt->execute();
        $groupId = db()->lastInsertId();
        echo "<p>✓ Created default group (ID: $groupId)</p>";
    } else {
        $stmt = db()->prepare("SELECT id FROM groups LIMIT 1");
        $stmt->execute();
        $groupId = $stmt->fetch()['id'];
        echo "<p>✓ Using existing group (ID: $groupId)</p>";
    }
    
    // Check if admin exists
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM users WHERE username = 'superadmin' OR role = 'super_admin'");
    $stmt->execute();
    $adminExists = $stmt->fetch()['count'];
    
    if ($adminExists > 0) {
        echo "<p style='color: orange;'>⚠ Admin user already exists!</p>";
        echo "<p>Login at: <a href='pages/login.php'>pages/login.php</a></p>";
    } else {
        // Create admin with password 'Admin@123'
        $password = 'Admin@123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = db()->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role, status, group_id) 
            VALUES (?, ?, ?, ?, ?, 'super_admin', 'active', ?)
        ");
        $stmt->execute(['superadmin', 'admin@careway.com', $hash, 'System', 'Administrator', $groupId]);
        
        echo "<p style='color: green; font-weight: bold;'>✓ Admin user created successfully!</p>";
        echo "<hr>";
        echo "<h2>Login Credentials:</h2>";
        echo "<ul>";
        echo "<li><strong>Login URL:</strong> <a href='pages/login.php'>http://localhost/careway/pages/login.php</a></li>";
        echo "<li><strong>Username:</strong> <code>superadmin</code></li>";
        echo "<li><strong>Email:</strong> <code>admin@careway.com</code></li>";
        echo "<li><strong>Password:</strong> <code>Admin@123</code></li>";
        echo "</ul>";
        echo "<hr>";
        echo "<p style='color: red;'><strong>⚠ IMPORTANT:</strong> Delete this file (create_admin.php) after login!</p>";
        echo "<br>";
        echo "<a href='pages/login.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page →</a>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
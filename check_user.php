<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$stmt = db()->prepare("SELECT * FROM users WHERE username = 'superadmin'");
$stmt->execute();
$user = $stmt->fetch();

if ($user) {
    echo "<h1>User Found!</h1>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
} else {
    echo "<h1>User NOT Found!</h1>";
    echo "<p>Please run the setup script to create admin user.</p>";
}

echo "<br><a href='admin/login.php'>Go to Login</a>";
?>
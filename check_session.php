<?php
require_once 'includes/config.php';

echo "<h1>Session Information</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Session ID:</h2>";
echo session_id();

echo "<h2>Actions:</h2>";
echo "<a href='pages/logout.php'>Logout</a><br>";
echo "<a href='admin/dashboard.php'>Go to Admin Dashboard</a><br>";
echo "<a href='dashboard/home.php'>Go to User Dashboard</a><br>";
echo "<a href='pages/login.php'>Go to Login Page</a>";
?>
<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'careway_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Careway Welfare Management System');
define('APP_URL', 'http://localhost/careway');
define('APP_ROOT', dirname(__DIR__));
define('APP_TIMEZONE', 'Africa/Nairobi');
define('APP_VERSION', '1.0.0');

// Security configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// Upload configuration
define('UPLOAD_MAX_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Subscription plans
define('SUBSCRIPTION_PLANS', [
    'monthly' => ['price' => 29.99, 'days' => 30],
    'quarterly' => ['price' => 79.99, 'days' => 90],
    'biannual' => ['price' => 149.99, 'days' => 180],
    'annual' => ['price' => 299.99, 'days' => 365]
]);

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
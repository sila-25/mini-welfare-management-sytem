<?php
// Simple authentication for all module files

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /careway/pages/login.php');
    exit();
}

// Simple permission check using the functions from functions.php
// This file now just ensures authentication and lets functions.php handle the rest
?>
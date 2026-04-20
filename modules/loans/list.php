<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$page_title = 'Loans Management';
$current_page = 'loans';

$groupId = $_SESSION['group_id'];
$memberId = $_SESSION['member_id'] ?? null;
$userRole = $_SESSION['user_role'];

// Handle filters
$status = $_GET['status'] ?? '';
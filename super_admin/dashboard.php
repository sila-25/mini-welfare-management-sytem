<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/super_admin/login.php');
    exit();
}

// Only super admin can access
if ($_SESSION['user_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/super_admin/login.php?unauthorized=1');
    exit();
}

$page_title = 'System Administration';
$current_page = 'super_admin';
$active_section = $_GET['section'] ?? 'overview';

// Get system statistics
$stmt = db()->prepare("SELECT COUNT(*) as total FROM groups");
$stmt->execute();
$totalGroups = $stmt->fetch()['total'];

$stmt = db()->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'chairperson'");
$stmt->execute();
$totalGroupAdmins = $stmt->fetch()['total'];

$stmt = db()->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'super_admin'");
$stmt->execute();
$totalSystemAdmins = $stmt->fetch()['total'];

$stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payment_transactions WHERE status = 'completed'");
$stmt->execute();
$totalRevenue = $stmt->fetch()['total'];

include_once '../templates/header.php';
include_once '../templates/sidebar.php';
include_once '../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-crown me-2 text-warning"></i>System Administration
                </h1>
                <p class="text-muted mt-2">Manage all groups, group admins, and platform settings</p>
            </div>
            <div>
                <button class="btn btn-primary me-2" onclick="window.location.reload()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
                <a href="<?php echo APP_URL; ?>/pages/logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-title">Total Groups</h6>
                        <h2 class="mb-0"><?php echo number_format($totalGroups); ?></h2>
                        <small>Registered on platform</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-title">Group Admins</h6>
                        <h2 class="mb-0"><?php echo number_format($totalGroupAdmins); ?></h2>
                        <small>Managing groups</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-title">System Admins</h6>
                        <h2 class="mb-0"><?php echo number_format($totalSystemAdmins); ?></h2>
                        <small>Platform managers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-title">Total Revenue</h6>
                        <h2 class="mb-0"><?php echo formatCurrency($totalRevenue); ?></h2>
                        <small>From subscriptions</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'overview' ? 'active' : ''; ?>" href="?section=overview">
                            <i class="fas fa-chart-pie me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'groups' ? 'active' : ''; ?>" href="?section=groups">
                            <i class="fas fa-building me-2"></i>Manage Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'group-admins' ? 'active' : ''; ?>" href="?section=group-admins">
                            <i class="fas fa-user-shield me-2"></i>Group Admins
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'system-admins' ? 'active' : ''; ?>" href="?section=system-admins">
                            <i class="fas fa-crown me-2"></i>System Admins
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'plans' ? 'active' : ''; ?>" href="?section=plans">
                            <i class="fas fa-tags me-2"></i>Subscription Plans
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'transactions' ? 'active' : ''; ?>" href="?section=transactions">
                            <i class="fas fa-credit-card me-2"></i>Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_section === 'settings' ? 'active' : ''; ?>" href="?section=settings">
                            <i class="fas fa-cog me-2"></i>Platform Settings
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php
                if ($active_section === 'overview') {
                    echo '<div class="overview-content">';
                    echo '<div class="alert alert-info">Welcome to the System Administration Panel. Use the tabs above to manage groups, admins, and platform settings.</div>';
                    echo '<div class="row">';
                    echo '<div class="col-md-6"><div class="card mb-3"><div class="card-body"><h5>Quick Links</h5>';
                    echo '<a href="?section=groups" class="btn btn-outline-primary m-1">Manage Groups</a>';
                    echo '<a href="?section=group-admins" class="btn btn-outline-success m-1">Manage Group Admins</a>';
                    echo '<a href="?section=system-admins" class="btn btn-outline-info m-1">Add System Admin</a>';
                    echo '<a href="?section=plans" class="btn btn-outline-warning m-1">Manage Plans</a>';
                    echo '</div></div></div>';
                    echo '<div class="col-md-6"><div class="card mb-3"><div class="card-body"><h5>System Info</h5>';
                    echo '<p><strong>Total Groups:</strong> ' . number_format($totalGroups) . '</p>';
                    echo '<p><strong>Group Admins:</strong> ' . number_format($totalGroupAdmins) . '</p>';
                    echo '<p><strong>System Admins:</strong> ' . number_format($totalSystemAdmins) . '</p>';
                    echo '<p><strong>Total Revenue:</strong> ' . formatCurrency($totalRevenue) . '</p>';
                    echo '</div></div></div></div></div>';
                } elseif ($active_section === 'groups') {
                    include_once __DIR__ . '/groups.php';
                } elseif ($active_section === 'group-admins') {
                    include_once __DIR__ . '/group_admins.php';
                } elseif ($active_section === 'system-admins') {
                    include_once __DIR__ . '/system_admins.php';
                } elseif ($active_section === 'plans') {
                    include_once __DIR__ . '/plans.php';
                } elseif ($active_section === 'transactions') {
                    include_once __DIR__ . '/transactions.php';
                } elseif ($active_section === 'settings') {
                    include_once __DIR__ . '/settings.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>
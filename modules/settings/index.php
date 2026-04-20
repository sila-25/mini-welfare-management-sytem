<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

// Check if user has permission to access settings
// Only chairperson and super admin can access settings
if ($_SESSION['user_role'] !== 'chairperson' && $_SESSION['user_role'] !== 'super_admin') {
    $page_title = 'Access Denied';
    include_once '../../templates/header.php';
    include_once '../../templates/sidebar.php';
    include_once '../../templates/topbar.php';
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-0 shadow-lg text-center">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <i class="fas fa-lock fa-4x text-danger"></i>
                            </div>
                            <h2 class="h4 mb-3">Access Denied</h2>
                            <p class="text-muted mb-4">You do not have permission to access the settings page.</p>
                            <p class="text-muted mb-4">Only Chairpersons and System Administrators can access system settings.</p>
                            <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include_once '../../templates/footer.php';
    exit();
}

$page_title = 'Group Settings';
$current_page = 'settings';
$active_tab = $_GET['tab'] ?? 'general';

$groupId = $_SESSION['group_id'];

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-cog me-2"></i>Group Settings
                </h1>
                <p class="text-muted mt-2">Manage your group configuration and preferences</p>
            </div>
            <div>
                <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Settings Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" href="?tab=general">
                            <i class="fas fa-sliders-h me-2"></i>General Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'positions' ? 'active' : ''; ?>" href="?tab=positions">
                            <i class="fas fa-users me-2"></i>Positions & Roles
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'permissions' ? 'active' : ''; ?>" href="?tab=permissions">
                            <i class="fas fa-lock me-2"></i>Permissions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'subscriptions' ? 'active' : ''; ?>" href="?tab=subscriptions">
                            <i class="fas fa-credit-card me-2"></i>Subscriptions
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php
                // Include the appropriate settings page based on active tab
                if ($active_tab === 'general') {
                    include_once 'general.php';
                } elseif ($active_tab === 'positions') {
                    include_once 'positions.php';
                } elseif ($active_tab === 'permissions') {
                    include_once 'permissions.php';
                } elseif ($active_tab === 'subscriptions') {
                    include_once 'subscriptions.php';
                } else {
                    include_once 'general.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link {
    color: #495057;
    border: none;
    padding: 10px 20px;
    margin-right: 5px;
}
.nav-tabs .nav-link:hover {
    border-bottom: 2px solid #667eea;
}
.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
    background: transparent;
}
</style>

<?php include_once '../../templates/footer.php'; ?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

// Check permission - only authorized roles can access investments
$allowed_roles = ['chairperson', 'treasurer', 'super_admin'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
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
                            <p class="text-muted mb-4">You do not have permission to access the Investments section.</p>
                            <p class="text-muted mb-4">Only Chairpersons, Treasurers, and Administrators can access this area.</p>
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

$page_title = 'Investments';
$current_page = 'investments';

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-warning"></i>Investments Management</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Investments module is under construction. Check back soon!
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
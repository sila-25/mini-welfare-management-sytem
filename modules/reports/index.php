<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

// Check permission - only authorized roles can access reports
$allowed_roles = ['chairperson', 'treasurer', 'secretary', 'super_admin'];
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
                            <p class="text-muted mb-4">You do not have permission to access the Reports section.</p>
                            <p class="text-muted mb-4">Only Chairpersons, Treasurers, Secretaries, and Administrators can access this area.</p>
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

$page_title = 'Reports';
$current_page = 'reports';

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-danger"></i>Reports</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card text-center border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                <h5>Financial Reports</h5>
                                <p class="text-muted">View income, expenses, and financial summaries</p>
                                <button class="btn btn-outline-primary" disabled>Coming Soon</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-success mb-3"></i>
                                <h5>Member Reports</h5>
                                <p class="text-muted">Member statistics and demographics</p>
                                <button class="btn btn-outline-success" disabled>Coming Soon</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-hand-holding-usd fa-3x text-warning mb-3"></i>
                                <h5>Loan Reports</h5>
                                <p class="text-muted">Loan applications, approvals, and repayments</p>
                                <button class="btn btn-outline-warning" disabled>Coming Soon</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /careway/pages/login.php');
    exit();
}

$page_title = 'Members Management';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-users me-2"></i>Members Management</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Members module is under construction. Please check back later.
            </div>
            <a href="/careway/dashboard/home.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
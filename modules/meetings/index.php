<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /careway/pages/login.php');
    exit();
}

$page_title = 'Meetings';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-alt me-2"></i>Meetings Management</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Meetings module is being set up. Check back soon!
            </div>
            <a href="/careway/dashboard/home.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
<?php
require_once dirname(__DIR__) . '/../includes/config.php';
require_once dirname(__DIR__) . '/../includes/functions.php';

$page_title = 'Pending Loan Applications';
include_once dirname(__DIR__) . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Loan Applications</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Your loan applications are being reviewed by the committee. You will be notified once a decision is made.
            </div>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Loans
            </a>
        </div>
    </div>
</div>

<?php include_once dirname(__DIR__) . '/../templates/footer.php'; ?>
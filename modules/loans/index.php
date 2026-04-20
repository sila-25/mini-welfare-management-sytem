<?php
require_once dirname(__DIR__) . '/../includes/config.php';
require_once dirname(__DIR__) . '/../includes/db.php';
require_once dirname(__DIR__) . '/../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Loans Management';
$current_page = 'loans';

// Get user's loans
$myLoans = [];
try {
    $stmt = db()->prepare("
        SELECT l.*, 
               COALESCE(lr.paid, 0) as amount_paid,
               (l.total_repayable - COALESCE(lr.paid, 0)) as balance
        FROM loans l
        LEFT JOIN (
            SELECT loan_id, SUM(amount) as paid
            FROM loan_repayments
            GROUP BY loan_id
        ) lr ON l.id = lr.loan_id
        WHERE l.member_id = (SELECT id FROM members WHERE user_id = ? LIMIT 1)
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $myLoans = $stmt->fetchAll();
} catch (Exception $e) {
    $myLoans = [];
}

include_once dirname(__DIR__) . '/../templates/header.php';
include_once dirname(__DIR__) . '/../templates/sidebar.php';
include_once dirname(__DIR__) . '/../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Page Header with Back Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>/dashboard/home.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Loans</li>
                    </ol>
                </nav>
                <h1 class="h3 mb-0">
                    <i class="fas fa-hand-holding-usd me-2 text-primary"></i>Loans Management
                </h1>
                <p class="text-muted mt-2">Apply for loans, track your applications, and manage repayments</p>
            </div>
            <div>
                <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Action Cards Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-file-alt fa-2x text-primary"></i>
                        </div>
                        <h5>Apply for Loan</h5>
                        <p class="text-muted">Submit a new loan application</p>
                        <a href="apply.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Apply Now
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                        <h5>Pending Applications</h5>
                        <p class="text-muted">Check your application status</p>
                        <a href="pending.php" class="btn btn-warning">
                            <i class="fas fa-search me-2"></i>View Status
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body">
                        <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-calculator fa-2x text-success"></i>
                        </div>
                        <h5>Loan Calculator</h5>
                        <p class="text-muted">Calculate your loan payments</p>
                        <a href="calculator.php" class="btn btn-success">
                            <i class="fas fa-calculator me-2"></i>Calculate
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- My Loans Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-list me-2 text-info"></i>My Loan Applications</h5>
            </div>
            <div class="card-body">
                <?php if (empty($myLoans)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No loan applications found</p>
                        <a href="apply.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Apply for First Loan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Loan #</th>
                                    <th>Amount</th>
                                    <th>Total Payable</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myLoans as $loan): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($loan['loan_number']); ?></code></td>
                                        <td class="text-primary fw-bold"><?php echo formatCurrency($loan['principal_amount']); ?></td>
                                        <td><?php echo formatCurrency($loan['total_repayable']); ?></td>
                                        <td class="text-success"><?php echo formatCurrency($loan['amount_paid'] ?? 0); ?></td>
                                        <td class="text-danger"><?php echo formatCurrency($loan['balance']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($loan['purpose'], 0, 30)); ?>...</td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'disbursed' => 'success',
                                                'completed' => 'secondary',
                                                'rejected' => 'danger'
                                            ];
                                            $color = $statusColors[$loan['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($loan['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($loan['application_date']); ?></td>
                                        <td>
                                            <a href="details.php?id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($loan['status'] === 'disbursed' && $loan['balance'] > 0): ?>
                                                <a href="repay.php?loan_id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-money-bill"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Back to Dashboard Button at Bottom -->
        <div class="mt-4 text-center">
            <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<style>
.breadcrumb {
    background: transparent;
    padding: 0;
}
.breadcrumb-item a {
    color: var(--primary-color);
    text-decoration: none;
}
.breadcrumb-item.active {
    color: #6c757d;
}
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
.btn-primary, .btn-warning, .btn-success {
    transition: all 0.2s;
}
.btn-primary:hover, .btn-warning:hover, .btn-success:hover {
    transform: translateY(-1px);
}
</style>

<?php include_once dirname(__DIR__) . '/../templates/footer.php'; ?>
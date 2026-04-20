<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$loanId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];
$memberId = $_SESSION['member_id'] ?? null;
$userRole = $_SESSION['user_role'];

// Get loan details with member info
$stmt = db()->prepare("
    SELECT l.*, 
           m.first_name, m.last_name, m.member_number, m.phone, m.email,
           m.total_contributions,
           CONCAT(a.first_name, ' ', a.last_name) as approved_by_name_admin,
           CONCAT(d.first_name, ' ', d.last_name) as disbursed_by_name_admin
    FROM loans l
    JOIN members m ON l.member_id = m.id
    LEFT JOIN users a ON l.approved_by = a.id
    LEFT JOIN users d ON l.disbursed_by = d.id
    WHERE l.id = ? AND l.group_id = ?
");
$stmt->execute([$loanId, $groupId]);
$loan = $stmt->fetch();

if (!$loan) {
    setFlash('danger', 'Loan not found');
    redirect('/modules/loans/index.php');
}

// Check permission - member can only view their own loans
if ($userRole === 'member' && $loan['member_id'] != $memberId) {
    setFlash('danger', 'You do not have permission to view this loan');
    redirect('/modules/loans/index.php');
}

// Get guarantors
$stmt = db()->prepare("
    SELECT lg.*, m.first_name, m.last_name, m.member_number, m.phone
    FROM loan_guarantors lg
    JOIN members m ON lg.guarantor_id = m.id
    WHERE lg.loan_id = ?
");
$stmt->execute([$loanId]);
$guarantors = $stmt->fetchAll();

// Get repayment schedule
$stmt = db()->prepare("
    SELECT * FROM loan_repayment_schedule
    WHERE loan_id = ?
    ORDER BY installment_number
");
$stmt->execute([$loanId]);
$schedule = $stmt->fetchAll();

// Get actual repayments made
$stmt = db()->prepare("
    SELECT * FROM loan_repayments
    WHERE loan_id = ?
    ORDER BY payment_date DESC
");
$stmt->execute([$loanId]);
$repayments = $stmt->fetchAll();

// Calculate totals
$totalPaid = array_sum(array_column($repayments, 'amount'));
$totalPrincipalPaid = array_sum(array_column($repayments, 'principal_paid'));
$totalInterestPaid = array_sum(array_column($repayments, 'interest_paid'));
$remainingBalance = $loan['total_repayable'] - $totalPaid;
$remainingPrincipal = $loan['principal_amount'] - $totalPrincipalPaid;
$remainingInterest = $loan['total_repayable'] - $loan['principal_amount'] - $totalInterestPaid;

$page_title = 'Loan Details - ' . $loan['loan_number'];
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <!-- Loan Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Loans</a></li>
                            <li class="breadcrumb-item active">Loan Details</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Loan Application Details</h1>
                    <p class="text-muted">Reference: <?php echo htmlspecialchars($loan['loan_number']); ?></p>
                </div>
                <div>
                    <?php if ($loan['status'] === 'pending' && in_array($userRole, ['chairperson', 'treasurer'])): ?>
                        <a href="approve.php?id=<?php echo $loanId; ?>" class="btn btn-warning">
                            <i class="fas fa-check-circle me-2"></i>Review Application
                        </a>
                    <?php elseif ($loan['status'] === 'approved' && in_array($userRole, ['chairperson', 'treasurer'])): ?>
                        <a href="disburse.php?id=<?php echo $loanId; ?>" class="btn btn-success">
                            <i class="fas fa-hand-holding-usd me-2"></i>Disburse Loan
                        </a>
                    <?php elseif ($loan['status'] === 'disbursed' && $remainingBalance > 0): ?>
                        <a href="repay.php?loan_id=<?php echo $loanId; ?>" class="btn btn-primary">
                            <i class="fas fa-money-bill-wave me-2"></i>Make Repayment
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-secondary ms-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Statement
                    </button>
                </div>
            </div>

            <!-- Status Banner -->
            <div class="alert alert-<?php 
                echo $loan['status'] === 'disbursed' ? 'success' : 
                    ($loan['status'] === 'approved' ? 'info' : 
                    ($loan['status'] === 'pending' ? 'warning' : 
                    ($loan['status'] === 'rejected' ? 'danger' : 'secondary'))); 
            ?> mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-<?php 
                        echo $loan['status'] === 'disbursed' ? 'check-circle' : 
                            ($loan['status'] === 'approved' ? 'clock' : 
                            ($loan['status'] === 'pending' ? 'hourglass-half' : 
                            ($loan['status'] === 'rejected' ? 'times-circle' : 'ban'))); 
                    ?> fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-0">Loan Status: <?php echo ucfirst($loan['status']); ?></h5>
                        <?php if ($loan['status'] === 'rejected' && $loan['rejection_reason']): ?>
                            <p class="mb-0">Reason: <?php echo htmlspecialchars($loan['rejection_reason']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Loan Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Principal Amount</h6>
                            <h3 class="mb-0"><?php echo formatCurrency($loan['principal_amount']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Interest</h6>
                            <h3 class="mb-0"><?php echo formatCurrency($loan['total_repayable'] - $loan['principal_amount']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Payable</h6>
                            <h3 class="mb-0"><?php echo formatCurrency($loan['total_repayable']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Remaining Balance</h6>
                            <h3 class="mb-0"><?php echo formatCurrency($remainingBalance); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loan Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Loan Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>Member Name:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Member Number:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['member_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['phone']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Contributions:</strong></td>
                                    <td><?php echo formatCurrency($loan['total_contributions']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="40%"><strong>Application Date:</strong></td>
                                    <td><?php echo formatDate($loan['application_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Approval Date:</strong></td>
                                    <td><?php echo $loan['approval_date'] ? formatDate($loan['approval_date']) : 'Pending'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Disbursement Date:</strong></td>
                                    <td><?php echo $loan['disbursement_date'] ? formatDate($loan['disbursement_date']) : 'Pending'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Due Date:</strong></td>
                                    <td><?php echo $loan['due_date'] ? formatDate($loan['due_date']) : 'Not set'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Interest Rate:</strong></td>
                                    <td><?php echo $loan['interest_rate']; ?>% per annum</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-12">
                            <strong>Loan Purpose:</strong>
                            <p><?php echo htmlspecialchars($loan['purpose']); ?></p>
                            
                            <?php if ($loan['application_reason']): ?>
                                <strong>Detailed Reason:</strong>
                                <p><?php echo nl2br(htmlspecialchars($loan['application_reason'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Repayment Schedule -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Repayment Schedule</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Due Date</th>
                                    <th>Principal Due</th>
                                    <th>Interest Due</th>
                                    <th>Total Due</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $now = new DateTime();
                                foreach ($schedule as $installment): 
                                    $dueDate = new DateTime($installment['due_date']);
                                    $isOverdue = ($installment['status'] === 'pending' && $dueDate < $now);
                                ?>
                                    <tr class="<?php echo $isOverdue ? 'table-danger' : ''; ?>">
                                        <td><?php echo $installment['installment_number']; ?></td>
                                        <td><?php echo formatDate($installment['due_date']); ?></td>
                                        <td><?php echo formatCurrency($installment['principal_due']); ?></td>
                                        <td><?php echo formatCurrency($installment['interest_due']); ?></td>
                                        <td><?php echo formatCurrency($installment['total_due']); ?></td>
                                        <td>
                                            <?php if ($installment['status'] === 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($isOverdue): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $installment['paid_date'] ? formatDate($installment['paid_date']) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($repayments)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Payment History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Principal Paid</th>
                                    <th>Interest Paid</th>
                                    <th>Payment Method</th>
                                    <th>Receipt #</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repayments as $repayment): ?>
                                    <tr>
                                        <td><?php echo formatDate($repayment['payment_date']); ?></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($repayment['amount']); ?></td>
                                        <td><?php echo formatCurrency($repayment['principal_paid']); ?></td>
                                        <td><?php echo formatCurrency($repayment['interest_paid']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $repayment['payment_method'])); ?></td>
                                        <td><?php echo htmlspecialchars($repayment['receipt_number']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>TOTAL</td>
                                    <td><?php echo formatCurrency($totalPaid); ?></td>
                                    <td><?php echo formatCurrency($totalPrincipalPaid); ?></td>
                                    <td><?php echo formatCurrency($totalInterestPaid); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Guarantors -->
            <?php if (!empty($guarantors)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-handshake me-2"></i>Guarantors</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Member Number</th>
                                    <th>Amount Guaranteed</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guarantors as $guarantor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($guarantor['first_name'] . ' ' . $guarantor['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($guarantor['member_number']); ?></td>
                                        <td><?php echo formatCurrency($guarantor['amount_guaranteed']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $guarantor['status'] === 'approved' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($guarantor['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Payment Progress -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Payment Progress</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h2 class="mb-0"><?php echo round(($totalPaid / $loan['total_repayable']) * 100, 1); ?>%</h2>
                        <small class="text-muted">Paid</small>
                    </div>
                    <div class="progress mb-3" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo ($totalPaid / $loan['total_repayable']) * 100; ?>%">
                            <?php echo formatCurrency($totalPaid); ?>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Remaining</small>
                            <h6 class="text-danger"><?php echo formatCurrency($remainingBalance); ?></h6>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Installments Left</small>
                            <h6><?php echo count(array_filter($schedule, function($s) { return $s['status'] === 'pending'; })); ?></h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Payment Info -->
            <?php 
            $nextPayment = null;
            foreach ($schedule as $installment) {
                if ($installment['status'] === 'pending') {
                    $nextPayment = $installment;
                    break;
                }
            }
            ?>
            <?php if ($nextPayment && $loan['status'] === 'disbursed'): ?>
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Next Payment Due</h5>
                </div>
                <div class="card-body text-center">
                    <h3><?php echo formatCurrency($nextPayment['total_due']); ?></h3>
                    <p class="mb-0">Due by: <strong><?php echo formatDate($nextPayment['due_date']); ?></strong></p>
                    <a href="repay.php?loan_id=<?php echo $loanId; ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-credit-card me-2"></i>Make Payment Now
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Loan Documents -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Loan Statement</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="printStatement()">
                            <i class="fas fa-print me-2"></i>Print Full Statement
                        </button>
                        <button class="btn btn-outline-success" onclick="exportStatement()">
                            <i class="fas fa-download me-2"></i>Download Statement (PDF)
                        </button>
                        <button class="btn btn-outline-info" onclick="sendStatement()">
                            <i class="fas fa-envelope me-2"></i>Email Statement
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .topbar, .sidebar, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
}
</style>

<script>
function printStatement() {
    window.print();
}

function exportStatement() {
    window.location.href = 'export_statement.php?id=<?php echo $loanId; ?>';
}

function sendStatement() {
    Swal.fire({
        title: 'Send Statement by Email',
        text: 'Loan statement will be sent to the member\'s registered email address.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Send'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'send_statement.php',
                method: 'POST',
                data: {
                    loan_id: <?php echo $loanId; ?>,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Statement sent successfully', 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to send', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to send statement', 'error');
                }
            });
        }
    });
}
</script>

<?php include_once '../../templates/footer.php'; ?>
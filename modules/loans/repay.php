<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$loanId = (int)($_GET['loan_id'] ?? 0);
$groupId = $_SESSION['group_id'];
$memberId = $_SESSION['member_id'] ?? null;

// Get loan details
$stmt = db()->prepare("
    SELECT l.*, m.first_name, m.last_name, m.member_number, m.email
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.id = ? AND l.group_id = ? AND l.status = 'disbursed'
");
$stmt->execute([$loanId, $groupId]);
$loan = $stmt->fetch();

if (!$loan) {
    setFlash('danger', 'Loan not found or not active');
    redirect('/modules/loans/index.php');
}

// Check if member owns this loan
if ($memberId && $loan['member_id'] != $memberId && $_SESSION['user_role'] === 'member') {
    setFlash('danger', 'You do not have permission to repay this loan');
    redirect('/modules/loans/index.php');
}

// Get next payment due
$stmt = db()->prepare("
    SELECT * FROM loan_repayment_schedule
    WHERE loan_id = ? AND status = 'pending'
    ORDER BY installment_number
    LIMIT 1
");
$stmt->execute([$loanId]);
$nextPayment = $stmt->fetch();

// Get payment summary
$stmt = db()->prepare("
    SELECT 
        COALESCE(SUM(amount), 0) as total_paid,
        COALESCE(SUM(principal_paid), 0) as total_principal_paid,
        COALESCE(SUM(interest_paid), 0) as total_interest_paid
    FROM loan_repayments
    WHERE loan_id = ?
");
$stmt->execute([$loanId]);
$paymentSummary = $stmt->fetch();

$remainingBalance = $loan['total_repayable'] - $paymentSummary['total_paid'];
$remainingPrincipal = $loan['principal_amount'] - $paymentSummary['total_principal_paid'];

// Handle repayment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/loans/repay.php?loan_id={$loanId}");
    }
    
    try {
        db()->beginTransaction();
        
        $amount = (float)$_POST['amount'];
        $paymentDate = $_POST['payment_date'];
        $paymentMethod = $_POST['payment_method'];
        $referenceNumber = !empty($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
        
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        if ($amount > $remainingBalance) {
            throw new Exception('Amount exceeds remaining balance');
        }
        
        // Calculate how much goes to principal vs interest
        $interestPortion = 0;
        $principalPortion = 0;
        
        // First pay off interest, then principal
        $totalInterest = $loan['total_repayable'] - $loan['principal_amount'];
        $remainingInterest = $totalInterest - $paymentSummary['total_interest_paid'];
        
        if ($amount >= $remainingInterest) {
            $interestPortion = $remainingInterest;
            $principalPortion = $amount - $remainingInterest;
        } else {
            $interestPortion = $amount;
            $principalPortion = 0;
        }
        
        // Generate receipt number
        $receiptNumber = 'LR-' . $loan['loan_number'] . '-' . time();
        
        // Record repayment
        $stmt = db()->prepare("
            INSERT INTO loan_repayments (
                loan_id, amount, principal_paid, interest_paid, payment_date,
                payment_method, receipt_number, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $loanId, $amount, $principalPortion, $interestPortion,
            $paymentDate, $paymentMethod, $receiptNumber, $_SESSION['user_id']
        ]);
        
        // Update repayment schedule
        $scheduleStmt = db()->prepare("
            UPDATE loan_repayment_schedule 
            SET status = 'paid', paid_date = ?, paid_amount = ?
            WHERE loan_id = ? AND status = 'pending' AND total_due <= ?
        ");
        
        $remainingAmount = $amount;
        $scheduleItems = db()->prepare("
            SELECT id, total_due FROM loan_repayment_schedule
            WHERE loan_id = ? AND status = 'pending'
            ORDER BY installment_number
        ");
        $scheduleItems->execute([$loanId]);
        
        while ($scheduleItem = $scheduleItems->fetch()) {
            if ($remainingAmount <= 0) break;
            
            if ($remainingAmount >= $scheduleItem['total_due']) {
                $scheduleStmt->execute([$paymentDate, $scheduleItem['total_due'], $loanId, $scheduleItem['total_due']]);
                $remainingAmount -= $scheduleItem['total_due'];
            } else {
                // Partial payment - don't mark as paid
                break;
            }
        }
        
        // Check if loan is fully paid
        $newTotalPaid = $paymentSummary['total_paid'] + $amount;
        if ($newTotalPaid >= $loan['total_repayable']) {
            $stmt = db()->prepare("
                UPDATE loans SET status = 'completed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$loanId]);
        }
        
        // Record transaction if account selected
        if (!empty($_POST['account_id'])) {
            $accountId = (int)$_POST['account_id'];
            $stmt = db()->prepare("
                INSERT INTO transactions (
                    group_id, account_id, transaction_type, category, amount,
                    description, transaction_date, reference_number, created_by
                ) VALUES (?, ?, 'income', 'loan_repayment', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId, $accountId, $amount,
                "Loan repayment from {$loan['first_name']} {$loan['last_name']} - Loan #{$loan['loan_number']}",
                $paymentDate, $receiptNumber, $_SESSION['user_id']
            ]);
            
            // Update account balance
            $stmt = db()->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ?");
            $stmt->execute([$amount, $accountId]);
        }
        
        // Audit log
        auditLog('loan_repayment', 'loan_repayments', db()->lastInsertId(), null, [
            'loan_id' => $loanId,
            'amount' => $amount,
            'receipt_number' => $receiptNumber
        ]);
        
        db()->commit();
        
        // Send receipt email
        $subject = "Loan Repayment Receipt - {$receiptNumber}";
        $body = "
            <h3>Loan Repayment Receipt</h3>
            <p>Dear {$loan['first_name']} {$loan['last_name']},</p>
            <p>Your loan repayment of <strong>" . formatCurrency($amount) . "</strong> has been received.</p>
            <p><strong>Loan Number:</strong> {$loan['loan_number']}</p>
            <p><strong>Receipt Number:</strong> {$receiptNumber}</p>
            <p><strong>Payment Date:</strong> " . formatDate($paymentDate) . "</p>
            <p><strong>Remaining Balance:</strong> " . formatCurrency($remainingBalance - $amount) . "</p>
            <p><a href='" . APP_URL . "/modules/loans/details.php?id={$loanId}'>View Loan Details</a></p>
        ";
        sendEmail($loan['email'], $subject, $body);
        
        setFlash('success', "Repayment of " . formatCurrency($amount) . " recorded successfully! Receipt: {$receiptNumber}");
        redirect("/modules/loans/details.php?id={$loanId}");
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

// Get accounts for recording
$stmt = db()->prepare("SELECT id, account_name, account_type FROM accounts WHERE group_id = ? AND status = 'active'");
$stmt->execute([$groupId]);
$accounts = $stmt->fetchAll();

$page_title = 'Repay Loan';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Loans</a></li>
                            <li class="breadcrumb-item"><a href="details.php?id=<?php echo $loanId; ?>">Loan Details</a></li>
                            <li class="breadcrumb-item active">Make Repayment</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Make Loan Repayment</h1>
                    <p class="text-muted">Loan #<?php echo htmlspecialchars($loan['loan_number']); ?></p>
                </div>
                <a href="details.php?id=<?php echo $loanId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
            
            <!-- Loan Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <small class="text-muted">Principal Amount</small>
                            <h4><?php echo formatCurrency($loan['principal_amount']); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <small class="text-muted">Total Paid</small>
                            <h4 class="text-success"><?php echo formatCurrency($paymentSummary['total_paid']); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <small class="text-muted">Remaining Balance</small>
                            <h4 class="text-danger"><?php echo formatCurrency($remainingBalance); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Next Payment Info -->
            <?php if ($nextPayment): ?>
            <div class="alert alert-info">
                <i class="fas fa-bell me-2"></i>
                <strong>Next Payment Due:</strong> <?php echo formatCurrency($nextPayment['total_due']); ?> 
                by <?php echo formatDate($nextPayment['due_date']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Repayment Form -->
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">KES</span>
                                    <input type="number" class="form-control" name="amount" step="100" 
                                           min="100" max="<?php echo $remainingBalance; ?>" required>
                                </div>
                                <div class="form-text">Min: 100 | Max: <?php echo formatCurrency($remainingBalance); ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3" id="referenceField" style="display: none;">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number" placeholder="Transaction ID / Cheque Number">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Record to Account (Optional)</label>
                                <select class="form-select" name="account_id">
                                    <option value="">Select Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select account to automatically record this transaction</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-success mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Payment Breakdown:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Your payment will first cover any accrued interest</li>
                                <li>Remaining amount will go towards the principal</li>
                                <li>A receipt will be generated and sent to your email</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-credit-card me-2"></i>Process Payment
                            </button>
                            <a href="details.php?id=<?php echo $loanId; ?>" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Repayment Tips -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Repayment Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Always make payments before the due date to avoid penalties</li>
                        <li>Early repayment reduces the total interest paid</li>
                        <li>Keep your payment receipts for your records</li>
                        <li>Contact the treasurer if you face difficulties in repayment</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('#paymentMethod').change(function() {
    const method = $(this).val();
    if (method === 'bank_transfer' || method === 'mobile_money' || method === 'cheque') {
        $('#referenceField').show();
    } else {
        $('#referenceField').hide();
        $('input[name="reference_number"]').val('');
    }
});

$('input[name="amount"]').on('change', function() {
    const amount = parseFloat($(this).val());
    const maxAmount = <?php echo $remainingBalance; ?>;
    
    if (amount > maxAmount) {
        Swal.fire('Warning', `Maximum payment amount is ${formatCurrency(maxAmount)}`, 'warning');
        $(this).val(maxAmount);
    }
});

function formatCurrency(amount) {
    return 'KES ' + amount.toLocaleString(2);
}
</script>

<?php include_once '../../templates/footer.php'; ?>
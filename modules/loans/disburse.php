<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_finances');
Middleware::requireGroupAccess();

$loanId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get loan details
$stmt = db()->prepare("
    SELECT l.*, m.first_name, m.last_name, m.member_number, m.phone, m.email,
           m.total_contributions
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.id = ? AND l.group_id = ? AND l.status = 'approved'
");
$stmt->execute([$loanId, $groupId]);
$loan = $stmt->fetch();

if (!$loan) {
    setFlash('danger', 'Loan not found or not in approved status');
    redirect('/modules/loans/index.php');
}

// Get accounts for disbursement
$stmt = db()->prepare("SELECT id, account_name, account_type, current_balance FROM accounts WHERE group_id = ? AND status = 'active'");
$stmt->execute([$groupId]);
$accounts = $stmt->fetchAll();

// Handle disbursement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/loans/disburse.php?id={$loanId}");
    }
    
    try {
        db()->beginTransaction();
        
        $disbursementDate = $_POST['disbursement_date'];
        $paymentMethod = $_POST['payment_method'];
        $referenceNumber = !empty($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
        $accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        
        // Calculate due date (loan duration months from disbursement)
        $dueDate = new DateTime($disbursementDate);
        $dueDate->add(new DateInterval('P' . $loan['duration_months'] . 'M'));
        
        // Update loan status
        $stmt = db()->prepare("
            UPDATE loans SET 
                status = 'disbursed',
                disbursement_date = ?,
                due_date = ?,
                disbursed_by = ?,
                disbursed_by_name = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $disbursementDate,
            $dueDate->format('Y-m-d'),
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $loanId
        ]);
        
        // Record transaction if account selected
        if ($accountId) {
            $stmt = db()->prepare("
                INSERT INTO transactions (
                    group_id, account_id, transaction_type, category, amount,
                    description, transaction_date, reference_number, created_by
                ) VALUES (?, ?, 'expense', 'loan_disbursement', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId,
                $accountId,
                $loan['principal_amount'],
                "Loan disbursement to {$loan['first_name']} {$loan['last_name']} - Loan #{$loan['loan_number']}",
                $disbursementDate,
                $referenceNumber ?: 'LOAN-' . $loan['loan_number'],
                $_SESSION['user_id']
            ]);
            
            // Update account balance
            $stmt = db()->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?");
            $stmt->execute([$loan['principal_amount'], $accountId]);
        }
        
        // Update repayment schedule due dates based on disbursement date
        $stmt = db()->prepare("
            SELECT id, installment_number FROM loan_repayment_schedule
            WHERE loan_id = ? ORDER BY installment_number
        ");
        $stmt->execute([$loanId]);
        $schedule = $stmt->fetchAll();
        
        $currentDate = new DateTime($disbursementDate);
        $updateStmt = db()->prepare("UPDATE loan_repayment_schedule SET due_date = ? WHERE id = ?");
        
        foreach ($schedule as $installment) {
            if ($loan['repayment_frequency'] === 'weekly') {
                $currentDate->add(new DateInterval('P7D'));
            } elseif ($loan['repayment_frequency'] === 'monthly') {
                $currentDate->add(new DateInterval('P1M'));
            } else {
                $currentDate->add(new DateInterval('P3M'));
            }
            $updateStmt->execute([$currentDate->format('Y-m-d'), $installment['id']]);
        }
        
        // Audit log
        auditLog('disburse_loan', 'loans', $loanId, (array)$loan, [
            'disbursement_date' => $disbursementDate,
            'payment_method' => $paymentMethod
        ]);
        
        db()->commit();
        
        // Send notification to member
        $subject = "Loan Disbursement Confirmation - {$loan['loan_number']}";
        $body = "
            <h3>Loan Disbursement Confirmation</h3>
            <p>Dear {$loan['first_name']} {$loan['last_name']},</p>
            <p>Your loan of <strong>" . formatCurrency($loan['principal_amount']) . "</strong> has been disbursed to you.</p>
            <p><strong>Loan Number:</strong> {$loan['loan_number']}</p>
            <p><strong>Disbursement Date:</strong> " . formatDate($disbursementDate) . "</p>
            <p><strong>Payment Method:</strong> " . ucfirst(str_replace('_', ' ', $paymentMethod)) . "</p>
            <p><strong>Total Repayable:</strong> " . formatCurrency($loan['total_repayable']) . "</p>
            <p><strong>Due Date:</strong> " . formatDate($dueDate->format('Y-m-d')) . "</p>
            <p>Please ensure you make timely repayments to avoid penalties.</p>
            <p><a href='" . APP_URL . "/modules/loans/details.php?id={$loanId}'>View Loan Details</a></p>
        ";
        sendEmail($loan['email'], $subject, $body);
        
        setFlash('success', "Loan disbursed successfully! Reference: {$loan['loan_number']}");
        redirect("/modules/loans/details.php?id={$loanId}");
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

$page_title = 'Disburse Loan';
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
                            <li class="breadcrumb-item active">Disburse Loan</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Disburse Loan</h1>
                    <p class="text-muted">Loan #<?php echo htmlspecialchars($loan['loan_number']); ?></p>
                </div>
                <a href="details.php?id=<?php echo $loanId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
            
            <!-- Loan Summary Card -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Loan Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted">Member</small>
                                <h6><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted">Principal Amount</small>
                                <h6 class="text-primary"><?php echo formatCurrency($loan['principal_amount']); ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <small class="text-muted">Total Repayable</small>
                                <h6><?php echo formatCurrency($loan['total_repayable']); ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Disbursement Form -->
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="disbursement_date" value="<?php echo date('Y-m-d'); ?>" required>
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
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Disbursement Account</label>
                                <select class="form-select" name="account_id">
                                    <option value="">Select Account (Optional)</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo htmlspecialchars($account['account_name']); ?> 
                                            (<?php echo ucfirst($account['account_type']); ?>) - 
                                            Balance: <?php echo formatCurrency($account['current_balance']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select account to record the disbursement transaction</small>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes about this disbursement"></textarea>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Confirmation:</strong> By disbursing this loan, you confirm that:
                            <ul class="mb-0 mt-2">
                                <li>The member has met all loan requirements</li>
                                <li>The funds are available for disbursement</li>
                                <li>The repayment schedule has been explained to the member</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-hand-holding-usd me-2"></i>Confirm Disbursement
                            </button>
                            <a href="details.php?id=<?php echo $loanId; ?>" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
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
</script>

<?php include_once '../../templates/footer.php'; ?>
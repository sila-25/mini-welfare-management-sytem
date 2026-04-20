<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('record_contributions');
Middleware::requireGroupAccess();

$page_title = 'Record Contribution';
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get members list for dropdown
$stmt = db()->prepare("SELECT id, first_name, last_name, member_number FROM members WHERE group_id = ? AND status = 'active' ORDER BY first_name");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Get contribution types from settings or use defaults
$contributionTypes = [
    'monthly' => 'Monthly Contribution',
    'welfare' => 'Welfare Contribution',
    'special' => 'Special Contribution',
    'registration' => 'Registration Fee'
];

// Get payment methods
$paymentMethods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'mobile_money' => 'Mobile Money',
    'cheque' => 'Cheque'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token. Please try again.');
        redirect('/modules/contributions/add.php');
    }
    
    try {
        db()->beginTransaction();
        
        $memberId = (int)$_POST['member_id'];
        $amount = (float)$_POST['amount'];
        $contributionType = $_POST['contribution_type'];
        $paymentMethod = $_POST['payment_method'];
        $paymentDate = $_POST['payment_date'];
        $referenceNumber = !empty($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        
        // Validate member
        $stmt = db()->prepare("SELECT id, first_name, last_name, member_number FROM members WHERE id = ? AND group_id = ? AND status = 'active'");
        $stmt->execute([$memberId, $groupId]);
        $member = $stmt->fetch();
        
        if (!$member) {
            throw new Exception('Invalid or inactive member selected');
        }
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        // Generate receipt number
        $receiptNumber = generateReceiptNumber();
        
        // Insert contribution
        $stmt = db()->prepare("
            INSERT INTO contributions (
                group_id, member_id, contribution_type, amount, payment_date, 
                payment_method, reference_number, receipt_number, notes, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $groupId,
            $memberId,
            $contributionType,
            $amount,
            $paymentDate,
            $paymentMethod,
            $referenceNumber,
            $receiptNumber,
            $notes,
            $userId
        ]);
        
        $contributionId = db()->lastInsertId();
        
        // Update member's total contributions
        $stmt = db()->prepare("
            UPDATE members SET 
                total_contributions = COALESCE(total_contributions, 0) + ?,
                last_contribution_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $paymentDate, $memberId]);
        
        // Record transaction in accounts if account is selected
        if (!empty($_POST['account_id'])) {
            $accountId = (int)$_POST['account_id'];
            $stmt = db()->prepare("
                INSERT INTO transactions (
                    group_id, account_id, transaction_type, category, amount, 
                    description, transaction_date, reference_number, created_by
                ) VALUES (?, ?, 'income', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId,
                $accountId,
                $contributionType,
                $amount,
                "Contribution from {$member['first_name']} {$member['last_name']} - Receipt: {$receiptNumber}",
                $paymentDate,
                $receiptNumber,
                $userId
            ]);
            
            // Update account balance
            $stmt = db()->prepare("UPDATE accounts SET current_balance = current_balance + ? WHERE id = ? AND group_id = ?");
            $stmt->execute([$amount, $accountId, $groupId]);
        }
        
        // Audit log
        auditLog('record_contribution', 'contributions', $contributionId, null, [
            'member_id' => $memberId,
            'amount' => $amount,
            'receipt_number' => $receiptNumber,
            'contribution_type' => $contributionType
        ]);
        
        db()->commit();
        
        setFlash('success', "Contribution recorded successfully! Receipt Number: {$receiptNumber}");
        redirect("/modules/contributions/view.php?id={$contributionId}");
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error recording contribution: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

// Get accounts for dropdown
$stmt = db()->prepare("SELECT id, account_name, account_type FROM accounts WHERE group_id = ? AND status = 'active'");
$stmt->execute([$groupId]);
$accounts = $stmt->fetchAll();

include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Contributions</a></li>
                            <li class="breadcrumb-item active">Record Contribution</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Record Contribution</h1>
                    <p class="text-muted">Record a new member contribution</p>
                </div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
            
            <!-- Contribution Form -->
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST" id="contributionForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Member <span class="text-danger">*</span></label>
                                <select class="form-select select2" name="member_id" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" <?php echo (isset($_GET['member_id']) && $_GET['member_id'] == $member['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['member_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contribution Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="contribution_type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($contributionTypes as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">KES</span>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($paymentMethods as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3" id="referenceField" style="display: none;">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number" placeholder="Transaction ID / Cheque Number">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Select Account (Optional)</label>
                                <select class="form-select" name="account_id">
                                    <option value="">Select Account (Optional)</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>">
                                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Select an account to automatically record this transaction</small>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about this contribution"></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save me-2"></i>Record Contribution
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Stats Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Today's Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $stmt = db()->prepare("
                            SELECT 
                                COUNT(*) as count,
                                SUM(amount) as total
                            FROM contributions 
                            WHERE group_id = ? AND DATE(payment_date) = CURDATE()
                        ");
                        $stmt->execute([$groupId]);
                        $todayStats = $stmt->fetch();
                        ?>
                        <div class="col-md-4 text-center">
                            <h3 class="text-primary"><?php echo number_format($todayStats['count'] ?? 0); ?></h3>
                            <small>Contributions Today</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h3 class="text-success"><?php echo formatCurrency($todayStats['total'] ?? 0); ?></h3>
                            <small>Total Today</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <h3 class="text-info"><?php echo date('F j, Y'); ?></h3>
                            <small>Current Date</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search for a member...'
    });
    
    // Show/hide reference field based on payment method
    $('#paymentMethod').change(function() {
        if ($(this).val() === 'bank_transfer' || $(this).val() === 'mobile_money' || $(this).val() === 'cheque') {
            $('#referenceField').show();
        } else {
            $('#referenceField').hide();
            $('input[name="reference_number"]').val('');
        }
    });
    
    // Form validation
    $('#contributionForm').on('submit', function(e) {
        const memberId = $('select[name="member_id"]').val();
        const amount = $('input[name="amount"]').val();
        
        if (!memberId) {
            e.preventDefault();
            Swal.fire('Error', 'Please select a member', 'error');
            return false;
        }
        
        if (!amount || amount <= 0) {
            e.preventDefault();
            Swal.fire('Error', 'Please enter a valid amount', 'error');
            return false;
        }
        
        $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
    });
});
</script>

<?php include_once '../../templates/footer.php'; ?>
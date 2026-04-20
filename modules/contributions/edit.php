<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('record_contributions');
Middleware::requireGroupAccess();

$contributionId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get contribution details
$stmt = db()->prepare("
    SELECT c.*, m.first_name, m.last_name, m.member_number, m.email as member_email
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    WHERE c.id = ? AND c.group_id = ?
");
$stmt->execute([$contributionId, $groupId]);
$contribution = $stmt->fetch();

if (!$contribution) {
    setFlash('danger', 'Contribution not found');
    redirect('/modules/contributions/list.php');
}

// Get members list for dropdown
$stmt = db()->prepare("SELECT id, first_name, last_name, member_number FROM members WHERE group_id = ? AND status = 'active' ORDER BY first_name");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Get contribution types
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
        redirect("/modules/contributions/edit.php?id={$contributionId}");
    }
    
    try {
        db()->beginTransaction();
        
        $oldData = (array)$contribution;
        
        $memberId = (int)$_POST['member_id'];
        $amount = (float)$_POST['amount'];
        $contributionType = $_POST['contribution_type'];
        $paymentMethod = $_POST['payment_method'];
        $paymentDate = $_POST['payment_date'];
        $referenceNumber = !empty($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        
        // Validate member
        $stmt = db()->prepare("SELECT id, first_name, last_name FROM members WHERE id = ? AND group_id = ? AND status = 'active'");
        $stmt->execute([$memberId, $groupId]);
        $member = $stmt->fetch();
        
        if (!$member) {
            throw new Exception('Invalid or inactive member selected');
        }
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        // Update contribution
        $stmt = db()->prepare("
            UPDATE contributions SET
                member_id = ?,
                contribution_type = ?,
                amount = ?,
                payment_date = ?,
                payment_method = ?,
                reference_number = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ? AND group_id = ?
        ");
        
        $stmt->execute([
            $memberId,
            $contributionType,
            $amount,
            $paymentDate,
            $paymentMethod,
            $referenceNumber,
            $notes,
            $contributionId,
            $groupId
        ]);
        
        // Update member's total contributions
        $amountDiff = $amount - $contribution['amount'];
        if ($amountDiff != 0) {
            $stmt = db()->prepare("
                UPDATE members 
                SET total_contributions = COALESCE(total_contributions, 0) + ?
                WHERE id = ?
            ");
            $stmt->execute([$amountDiff, $memberId]);
            
            // If member changed, update both old and new member totals
            if ($memberId != $contribution['member_id']) {
                $stmt = db()->prepare("
                    UPDATE members 
                    SET total_contributions = COALESCE(total_contributions, 0) - ?
                    WHERE id = ?
                ");
                $stmt->execute([$contribution['amount'], $contribution['member_id']]);
            }
        }
        
        // Update transaction if exists
        $stmt = db()->prepare("SELECT id FROM transactions WHERE reference_number = ? AND group_id = ?");
        $stmt->execute([$contribution['receipt_number'], $groupId]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            $stmt = db()->prepare("
                UPDATE transactions SET
                    amount = ?,
                    description = ?,
                    transaction_date = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $amount,
                "Contribution from {$member['first_name']} {$member['last_name']} - Receipt: {$contribution['receipt_number']}",
                $paymentDate,
                $transaction['id']
            ]);
        }
        
        // Audit log
        auditLog('edit_contribution', 'contributions', $contributionId, $oldData, [
            'member_id' => $memberId,
            'amount' => $amount,
            'receipt_number' => $contribution['receipt_number']
        ]);
        
        db()->commit();
        
        setFlash('success', 'Contribution updated successfully!');
        redirect("/modules/contributions/view.php?id={$contributionId}");
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error updating contribution: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

$page_title = 'Edit Contribution';
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
                            <li class="breadcrumb-item"><a href="list.php">Contributions</a></li>
                            <li class="breadcrumb-item"><a href="view.php?id=<?php echo $contributionId; ?>">Receipt</a></li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Edit Contribution</h1>
                    <p class="text-muted">Receipt: <?php echo htmlspecialchars($contribution['receipt_number']); ?></p>
                </div>
                <a href="view.php?id=<?php echo $contributionId; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Receipt
                </a>
            </div>
            
            <!-- Edit Form -->
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Editing this contribution will update member statistics and financial records.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Member <span class="text-danger">*</span></label>
                                <select class="form-select select2" name="member_id" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members as $member): ?>
                                        <option value="<?php echo $member['id']; ?>" 
                                            <?php echo $contribution['member_id'] == $member['id'] ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $key; ?>" <?php echo $contribution['contribution_type'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">KES</span>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required value="<?php echo $contribution['amount']; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo $contribution['payment_date']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="">Select Method</option>
                                    <?php foreach ($paymentMethods as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $contribution['payment_method'] == $key ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3" id="referenceField" style="<?php echo in_array($contribution['payment_method'], ['bank_transfer', 'mobile_money', 'cheque']) ? '' : 'display: none;'; ?>">
                                <label class="form-label">Reference Number</label>
                                <input type="text" class="form-control" name="reference_number" value="<?php echo htmlspecialchars($contribution['reference_number']); ?>">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($contribution['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="view.php?id=<?php echo $contributionId; ?>" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Original Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Original Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <td width="30%"><strong>Receipt Number:</strong></td>
                            <td><?php echo htmlspecialchars($contribution['receipt_number']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Recorded By:</strong></td>
                            <td><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'System'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date Recorded:</strong></td>
                            <td><?php echo formatDateTime($contribution['created_at']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Show/hide reference field based on payment method
    $('#paymentMethod').change(function() {
        const method = $(this).val();
        if (method === 'bank_transfer' || method === 'mobile_money' || method === 'cheque') {
            $('#referenceField').show();
        } else {
            $('#referenceField').hide();
        }
    });
});
</script>

<?php include_once '../../templates/footer.php'; ?>
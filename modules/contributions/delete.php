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
$confirm = $_GET['confirm'] ?? '';

if ($contributionId <= 0) {
    setFlash('danger', 'Invalid contribution ID');
    redirect('/modules/contributions/index.php');
}

$groupId = $_SESSION['group_id'];

// Get contribution details
$stmt = db()->prepare("
    SELECT c.*, m.first_name, m.last_name, m.member_number
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    WHERE c.id = ? AND c.group_id = ?
");
$stmt->execute([$contributionId, $groupId]);
$contribution = $stmt->fetch();

if (!$contribution) {
    setFlash('danger', 'Contribution not found');
    redirect('/modules/contributions/index.php');
}

if ($confirm === 'yes') {
    try {
        db()->beginTransaction();
        
        // Check if contribution is linked to any transactions
        $stmt = db()->prepare("SELECT id FROM transactions WHERE reference_number = ? AND group_id = ?");
        $stmt->execute([$contribution['receipt_number'], $groupId]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Reverse the transaction if exists
            $stmt = db()->prepare("
                UPDATE accounts 
                SET current_balance = current_balance - ? 
                WHERE id = (SELECT account_id FROM transactions WHERE id = ?)
            ");
            $stmt->execute([$contribution['amount'], $transaction['id']]);
            
            // Delete transaction
            $stmt = db()->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->execute([$transaction['id']]);
        }
        
        // Update member's total contributions
        $stmt = db()->prepare("
            UPDATE members 
            SET total_contributions = COALESCE(total_contributions, 0) - ?
            WHERE id = ?
        ");
        $stmt->execute([$contribution['amount'], $contribution['member_id']]);
        
        // Delete contribution
        $stmt = db()->prepare("DELETE FROM contributions WHERE id = ? AND group_id = ?");
        $stmt->execute([$contributionId, $groupId]);
        
        // Audit log
        auditLog('delete_contribution', 'contributions', $contributionId, (array)$contribution, null);
        
        db()->commit();
        
        setFlash('success', "Contribution record for {$contribution['first_name']} {$contribution['last_name']} has been deleted.");
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error deleting contribution: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/modules/contributions/index.php');
}

// Show confirmation page
$page_title = 'Confirm Deletion';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-3 mt-5">
                <div class="card-header bg-danger text-white py-3">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> You are about to delete a contribution record. This action cannot be undone.
                    </div>
                    
                    <div class="mb-4">
                        <h5>Contribution Details:</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Receipt Number:</strong></td>
                                <td><?php echo htmlspecialchars($contribution['receipt_number']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Member:</strong></td>
                                <td><?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Member Number:</strong></td>
                                <td><?php echo htmlspecialchars($contribution['member_number']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Amount:</strong></td>
                                <td class="text-danger fw-bold"><?php echo formatCurrency($contribution['amount']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo formatDate($contribution['payment_date']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td><?php echo ucfirst($contribution['contribution_type']); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <a href="?id=<?php echo $contributionId; ?>&confirm=yes" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Yes, Delete Record
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
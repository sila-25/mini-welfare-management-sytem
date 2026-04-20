<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_finances');
Middleware::requireGroupAccess();

$accountId = (int)($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';
$groupId = $_SESSION['group_id'];

// Get account details
$stmt = db()->prepare("SELECT * FROM accounts WHERE id = ? AND group_id = ?");
$stmt->execute([$accountId, $groupId]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('danger', 'Account not found');
    redirect('/modules/accounts/list.php');
}

// Check if account has any transactions
$stmt = db()->prepare("SELECT COUNT(*) as count FROM transactions WHERE account_id = ? AND group_id = ?");
$stmt->execute([$accountId, $groupId]);
$transactionCount = $stmt->fetch()['count'];

// Check if account has any balance
$hasBalance = $account['current_balance'] != 0;

if ($confirm === 'yes') {
    try {
        db()->beginTransaction();
        
        // Check again for transactions (double-check)
        $stmt = db()->prepare("SELECT COUNT(*) as count FROM transactions WHERE account_id = ?");
        $stmt->execute([$accountId]);
        $txCount = $stmt->fetch()['count'];
        
        if ($txCount > 0) {
            throw new Exception("Cannot delete account with existing transactions. Consider deactivating it instead.");
        }
        
        if ($account['current_balance'] != 0) {
            throw new Exception("Cannot delete account with non-zero balance. Please zero out the balance first.");
        }
        
        // Get old data for audit
        $oldData = (array)$account;
        
        // Delete account
        $stmt = db()->prepare("DELETE FROM accounts WHERE id = ? AND group_id = ?");
        $stmt->execute([$accountId, $groupId]);
        
        // Audit log
        auditLog('delete_account', 'accounts', $accountId, $oldData, null);
        
        db()->commit();
        
        setFlash('success', "Account '{$account['account_name']}' has been permanently deleted.");
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error deleting account: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/modules/accounts/list.php');
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
                        Confirm Account Deletion
                    </h4>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> You are about to permanently delete an account. This action cannot be undone.
                    </div>
                    
                    <div class="mb-4">
                        <h5>Account Details:</h5>
                        <table class="table table-borderless">
                            <tr>
                                <td width="40%"><strong>Account Name:</strong></td>
                                <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Account Type:</strong></td>
                                <td>
                                    <?php 
                                    $types = ['bank' => 'Bank Account', 'cash' => 'Cash Account', 'mobile_money' => 'Mobile Money'];
                                    echo $types[$account['account_type']];
                                    ?>
                                </td>
                            </tr>
                            <?php if ($account['account_type'] === 'bank'): ?>
                            <tr>
                                <td><strong>Bank:</strong></td>
                                <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Current Balance:</strong></td>
                                <td class="<?php echo $account['current_balance'] != 0 ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo formatCurrency($account['current_balance']); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Transaction Count:</strong></td>
                                <td><?php echo number_format($transactionCount); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <?php if ($transactionCount > 0): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>Cannot Delete:</strong> This account has <?php echo number_format($transactionCount); ?> transaction(s). 
                            Please deactivate the account instead of deleting it.
                        </div>
                    <?php elseif ($account['current_balance'] != 0): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>Cannot Delete:</strong> This account has a non-zero balance of <?php echo formatCurrency($account['current_balance']); ?>.
                            Please transfer or withdraw the balance before deletion.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Eligible for Deletion:</strong> This account has no transactions and zero balance.
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="list.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        
                        <?php if ($transactionCount == 0 && $account['current_balance'] == 0): ?>
                            <a href="?id=<?php echo $accountId; ?>&confirm=yes" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-2"></i>Yes, Permanently Delete
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock me-2"></i>Deletion Not Available
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
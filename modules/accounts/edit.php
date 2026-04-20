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
$groupId = $_SESSION['group_id'];
$isModal = isset($_GET['modal']) && $_GET['modal'] == 1;

// Get account details
$stmt = db()->prepare("SELECT * FROM accounts WHERE id = ? AND group_id = ?");
$stmt->execute([$accountId, $groupId]);
$account = $stmt->fetch();

if (!$account) {
    if ($isModal) {
        echo '<div class="alert alert-danger m-3">Account not found</div>';
        exit;
    }
    setFlash('danger', 'Account not found');
    redirect('/modules/accounts/list.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token. Please try again.');
        redirect("/modules/accounts/edit.php?id={$accountId}");
    }
    
    try {
        db()->beginTransaction();
        
        $oldData = (array)$account;
        
        $accountName = trim($_POST['account_name']);
        $status = $_POST['status'];
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        
        // Validate
        if (empty($accountName)) {
            throw new Exception('Account name is required');
        }
        
        // Check for duplicate name (excluding current account)
        $stmt = db()->prepare("SELECT id FROM accounts WHERE group_id = ? AND account_name = ? AND id != ?");
        $stmt->execute([$groupId, $accountName, $accountId]);
        if ($stmt->fetch()) {
            throw new Exception('Another account with this name already exists');
        }
        
        // Prepare update based on account type
        if ($account['account_type'] === 'bank') {
            $bankName = !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null;
            $accountNumber = !empty($_POST['account_number']) ? trim($_POST['account_number']) : null;
            
            $stmt = db()->prepare("
                UPDATE accounts SET
                    account_name = ?,
                    bank_name = ?,
                    account_number = ?,
                    status = ?,
                    description = ?
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$accountName, $bankName, $accountNumber, $status, $description, $accountId, $groupId]);
            
        } elseif ($account['account_type'] === 'mobile_money') {
            $mobileNumber = !empty($_POST['mobile_number']) ? trim($_POST['mobile_number']) : null;
            $mobileProvider = $_POST['mobile_provider'] ?? 'M-PESA';
            
            $stmt = db()->prepare("
                UPDATE accounts SET
                    account_name = ?,
                    mobile_number = ?,
                    mobile_provider = ?,
                    status = ?,
                    description = ?
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$accountName, $mobileNumber, $mobileProvider, $status, $description, $accountId, $groupId]);
            
        } else {
            // Cash account
            $stmt = db()->prepare("
                UPDATE accounts SET
                    account_name = ?,
                    status = ?,
                    description = ?
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$accountName, $status, $description, $accountId, $groupId]);
        }
        
        // Audit log
        auditLog('edit_account', 'accounts', $accountId, $oldData, $_POST);
        
        db()->commit();
        
        setFlash('success', "Account '{$accountName}' updated successfully");
        
        if ($isModal) {
            echo '<script>window.parent.location.reload();</script>';
            exit;
        }
        
        redirect('/modules/accounts/list.php');
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error updating account: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

// If this is a modal request, return the form HTML
if ($isModal) {
    ?>
    <form action="edit.php?id=<?php echo $accountId; ?>" method="POST" id="editAccountForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Account Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="account_name" value="<?php echo htmlspecialchars($account['account_name']); ?>" required>
                </div>
                
                <?php if ($account['account_type'] === 'bank'): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Bank Name</label>
                        <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($account['bank_name']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($account['account_number']); ?>">
                    </div>
                <?php elseif ($account['account_type'] === 'mobile_money'): ?>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" class="form-control" name="mobile_number" value="<?php echo htmlspecialchars($account['mobile_number']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Provider</label>
                        <select class="form-select" name="mobile_provider">
                            <option value="M-PESA" <?php echo $account['mobile_provider'] === 'M-PESA' ? 'selected' : ''; ?>>M-PESA</option>
                            <option value="Airtel Money" <?php echo $account['mobile_provider'] === 'Airtel Money' ? 'selected' : ''; ?>>Airtel Money</option>
                            <option value="T-Kash" <?php echo $account['mobile_provider'] === 'T-Kash' ? 'selected' : ''; ?>>T-Kash</option>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?php echo $account['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $account['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Balance</label>
                    <input type="text" class="form-control" value="<?php echo formatCurrency($account['current_balance']); ?>" readonly disabled>
                    <small class="text-muted">Balance cannot be edited directly. Use transactions to adjust balance.</small>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($account['description']); ?></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
    
    <script>
    $('#editAccountForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.includes('window.parent.location.reload()')) {
                    window.parent.location.reload();
                } else {
                    $('#editAccountModal').modal('hide');
                    Swal.fire('Success', 'Account updated successfully', 'success').then(() => {
                        location.reload();
                    });
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update account', 'error');
            }
        });
    });
    </script>
    <?php
    exit;
}

// Regular page view
$page_title = 'Edit Account';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="list.php">Accounts</a></li>
                            <li class="breadcrumb-item active">Edit Account</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Edit Account</h1>
                </div>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Current balance cannot be edited directly. Use transactions to record income and expenses.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="account_name" value="<?php echo htmlspecialchars($account['account_name']); ?>" required>
                        </div>
                        
                        <?php if ($account['account_type'] === 'bank'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($account['bank_name']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Account Number</label>
                                    <input type="text" class="form-control" name="account_number" value="<?php echo htmlspecialchars($account['account_number']); ?>">
                                </div>
                            </div>
                        <?php elseif ($account['account_type'] === 'mobile_money'): ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mobile Number</label>
                                    <input type="tel" class="form-control" name="mobile_number" value="<?php echo htmlspecialchars($account['mobile_number']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Provider</label>
                                    <select class="form-select" name="mobile_provider">
                                        <option value="M-PESA" <?php echo $account['mobile_provider'] === 'M-PESA' ? 'selected' : ''; ?>>M-PESA</option>
                                        <option value="Airtel Money" <?php echo $account['mobile_provider'] === 'Airtel Money' ? 'selected' : ''; ?>>Airtel Money</option>
                                        <option value="T-Kash" <?php echo $account['mobile_provider'] === 'T-Kash' ? 'selected' : ''; ?>>T-Kash</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $account['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $account['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Balance</label>
                                <input type="text" class="form-control" value="<?php echo formatCurrency($account['current_balance']); ?>" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($account['description']); ?></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                            <a href="list.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
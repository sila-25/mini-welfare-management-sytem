<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_finances');
Middleware::requireGroupAccess();

$investmentId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];
$isModal = isset($_GET['modal']) && $_GET['modal'] == 1;

$stmt = db()->prepare("SELECT * FROM investments WHERE id = ? AND group_id = ?");
$stmt->execute([$investmentId, $groupId]);
$investment = $stmt->fetch();

if (!$investment) {
    if ($isModal) {
        echo '<div class="alert alert-danger m-3">Investment not found</div>';
        exit;
    }
    setFlash('danger', 'Investment not found');
    redirect('/modules/investments/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/investments/edit.php?id={$investmentId}");
    }
    
    try {
        db()->beginTransaction();
        
        $oldData = (array)$investment;
        
        $investmentName = trim($_POST['investment_name']);
        $investmentType = $_POST['investment_type'];
        $amountInvested = (float)$_POST['amount_invested'];
        $currentValue = (float)$_POST['current_value'];
        $expectedReturns = !empty($_POST['expected_returns']) ? (float)$_POST['expected_returns'] : null;
        $purchaseDate = $_POST['purchase_date'];
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        $location = !empty($_POST['location']) ? trim($_POST['location']) : null;
        $ownershipPercentage = !empty($_POST['ownership_percentage']) ? (float)$_POST['ownership_percentage'] : 100;
        $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        $status = $_POST['status'];
        
        $stmt = db()->prepare("
            UPDATE investments SET
                investment_name = ?, investment_type = ?, amount_invested = ?,
                current_value = ?, expected_returns = ?, purchase_date = ?,
                description = ?, location = ?, ownership_percentage = ?,
                notes = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND group_id = ?
        ");
        
        $stmt->execute([
            $investmentName, $investmentType, $amountInvested, $currentValue,
            $expectedReturns, $purchaseDate, $description, $location,
            $ownershipPercentage, $notes, $status, $investmentId, $groupId
        ]);
        
        auditLog('edit_investment', 'investments', $investmentId, $oldData, $_POST);
        
        db()->commit();
        setFlash('success', 'Investment updated successfully');
        
        if ($isModal) {
            echo '<script>window.parent.location.reload();</script>';
            exit;
        }
        
        redirect('/modules/investments/list.php');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

if ($isModal) {
    ?>
    <form action="edit.php?id=<?php echo $investmentId; ?>" method="POST" id="editInvestmentForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <div class="modal-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label">Investment Name *</label>
                    <input type="text" class="form-control" name="investment_name" value="<?php echo htmlspecialchars($investment['investment_name']); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Investment Type *</label>
                    <select class="form-select" name="investment_type" required>
                        <option value="land" <?php echo $investment['investment_type'] === 'land' ? 'selected' : ''; ?>>Land/Real Estate</option>
                        <option value="shares" <?php echo $investment['investment_type'] === 'shares' ? 'selected' : ''; ?>>Shares/Stocks</option>
                        <option value="bonds" <?php echo $investment['investment_type'] === 'bonds' ? 'selected' : ''; ?>>Bonds</option>
                        <option value="business" <?php echo $investment['investment_type'] === 'business' ? 'selected' : ''; ?>>Business</option>
                        <option value="other" <?php echo $investment['investment_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date" value="<?php echo $investment['purchase_date']; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Amount Invested *</label>
                    <input type="number" class="form-control" name="amount_invested" step="0.01" value="<?php echo $investment['amount_invested']; ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Value</label>
                    <input type="number" class="form-control" name="current_value" step="0.01" value="<?php echo $investment['current_value']; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Expected Returns</label>
                    <input type="number" class="form-control" name="expected_returns" step="0.01" value="<?php echo $investment['expected_returns']; ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ownership %</label>
                    <input type="number" class="form-control" name="ownership_percentage" step="0.01" min="0" max="100" value="<?php echo $investment['ownership_percentage']; ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($investment['location']); ?>">
                </div>
                
                <div class="col-md-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($investment['description']); ?></textarea>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($investment['notes']); ?></textarea>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?php echo $investment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="liquidated" <?php echo $investment['status'] === 'liquidated' ? 'selected' : ''; ?>>Liquidated</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
    
    <script>
    $('#editInvestmentForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.includes('window.parent.location.reload()')) {
                    window.parent.location.reload();
                } else {
                    $('#editInvestmentModal').modal('hide');
                    Swal.fire('Success', 'Investment updated successfully', 'success').then(() => {
                        location.reload();
                    });
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update investment', 'error');
            }
        });
    });
    </script>
    <?php
    exit;
}

// Regular page view
$page_title = 'Edit Investment';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="list.php">Investments</a></li>
                            <li class="breadcrumb-item active">Edit Investment</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Edit Investment</h1>
                </div>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <!-- Same form fields as modal version -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Investment Name *</label>
                                <input type="text" class="form-control" name="investment_name" value="<?php echo htmlspecialchars($investment['investment_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Investment Type *</label>
                                <select class="form-select" name="investment_type" required>
                                    <option value="land" <?php echo $investment['investment_type'] === 'land' ? 'selected' : ''; ?>>Land/Real Estate</option>
                                    <option value="shares" <?php echo $investment['investment_type'] === 'shares' ? 'selected' : ''; ?>>Shares/Stocks</option>
                                    <option value="bonds" <?php echo $investment['investment_type'] === 'bonds' ? 'selected' : ''; ?>>Bonds</option>
                                    <option value="business" <?php echo $investment['investment_type'] === 'business' ? 'selected' : ''; ?>>Business</option>
                                    <option value="other" <?php echo $investment['investment_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" name="purchase_date" value="<?php echo $investment['purchase_date']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Invested *</label>
                                <input type="number" class="form-control" name="amount_invested" step="0.01" value="<?php echo $investment['amount_invested']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Value</label>
                                <input type="number" class="form-control" name="current_value" step="0.01" value="<?php echo $investment['current_value']; ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?php echo $investment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="liquidated" <?php echo $investment['status'] === 'liquidated' ? 'selected' : ''; ?>>Liquidated</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="list.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
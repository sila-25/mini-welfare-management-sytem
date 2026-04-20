<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$investmentId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

$stmt = db()->prepare("
    SELECT i.*, 
           COUNT(DISTINCT it.id) as transaction_count,
           SUM(CASE WHEN it.transaction_type = 'purchase' THEN it.amount ELSE 0 END) as total_purchases,
           SUM(CASE WHEN it.transaction_type = 'sale' THEN it.amount ELSE 0 END) as total_sales,
           SUM(CASE WHEN it.transaction_type = 'dividend' THEN it.amount ELSE 0 END) as total_dividends,
           SUM(CASE WHEN it.transaction_type = 'expense' THEN it.amount ELSE 0 END) as total_expenses
    FROM investments i
    LEFT JOIN investment_transactions it ON i.id = it.investment_id
    WHERE i.id = ? AND i.group_id = ?
    GROUP BY i.id
");
$stmt->execute([$investmentId, $groupId]);
$investment = $stmt->fetch();

if (!$investment) {
    echo '<div class="alert alert-danger m-3">Investment not found</div>';
    exit;
}

// Get recent transactions
$stmt = db()->prepare("
    SELECT * FROM investment_transactions
    WHERE investment_id = ?
    ORDER BY transaction_date DESC
    LIMIT 10
");
$stmt->execute([$investmentId]);
$transactions = $stmt->fetchAll();

$roi = $investment['amount_invested'] > 0 ? 
    (($investment['current_value'] - $investment['amount_invested']) / $investment['amount_invested'] * 100) : 0;
$gainLoss = $investment['current_value'] - $investment['amount_invested'];
?>

<div class="modal-body">
    <div class="row">
        <div class="col-md-6">
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6 class="card-title text-muted">Investment Summary</h6>
                    <h3 class="mb-0"><?php echo htmlspecialchars($investment['investment_name']); ?></h3>
                    <span class="badge bg-secondary mt-2"><?php echo ucfirst($investment['investment_type']); ?></span>
                    <span class="badge bg-<?php echo $investment['status'] === 'active' ? 'success' : 'secondary'; ?> mt-2">
                        <?php echo ucfirst($investment['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card <?php echo $gainLoss >= 0 ? 'border-success' : 'border-danger'; ?> mb-3">
                <div class="card-body text-center">
                    <h6 class="card-title">Performance</h6>
                    <h2 class="<?php echo $gainLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($gainLoss >= 0 ? '+' : '') . number_format($roi, 1); ?>%
                    </h2>
                    <p class="mb-0"><?php echo ($gainLoss >= 0 ? 'Profit' : 'Loss'); ?>: <?php echo formatCurrency(abs($gainLoss)); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="border rounded p-2 text-center">
                <small class="text-muted">Amount Invested</small>
                <h5 class="mb-0"><?php echo formatCurrency($investment['amount_invested']); ?></h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-2 text-center">
                <small class="text-muted">Current Value</small>
                <h5 class="mb-0"><?php echo formatCurrency($investment['current_value']); ?></h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-2 text-center">
                <small class="text-muted">Expected Returns</small>
                <h5 class="mb-0"><?php echo formatCurrency($investment['expected_returns'] ?? 0); ?></h5>
            </div>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <table class="table table-sm">
                <?php if ($investment['purchase_date']): ?>
                <tr>
                    <td width="40%"><strong>Purchase Date:</strong></td>
                    <td><?php echo formatDate($investment['purchase_date']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($investment['location']): ?>
                <tr>
                    <td><strong>Location:</strong></td>
                    <td><?php echo htmlspecialchars($investment['location']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Ownership:</strong></td>
                    <td><?php echo $investment['ownership_percentage']; ?>%</td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td><?php echo formatDate($investment['created_at']); ?></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm">
                <tr>
                    <td width="40%"><strong>Total Dividends:</strong></td>
                    <td class="text-success"><?php echo formatCurrency($investment['total_dividends'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Sales:</strong></td>
                    <td><?php echo formatCurrency($investment['total_sales'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Expenses:</strong></td>
                    <td class="text-danger"><?php echo formatCurrency($investment['total_expenses'] ?? 0); ?></td>
                </tr>
                <tr>
                    <td><strong>Net Profit:</strong></td>
                    <td class="<?php echo $gainLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency($gainLoss + ($investment['total_dividends'] ?? 0)); ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php if ($investment['description']): ?>
    <div class="mb-3">
        <strong>Description:</strong>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($investment['description'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($investment['notes']): ?>
    <div class="mb-3">
        <strong>Notes:</strong>
        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($investment['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($transactions)): ?>
    <div class="mt-3">
        <strong>Recent Transactions:</strong>
        <div class="table-responsive">
            <table class="table table-sm mt-2">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $transaction['transaction_type'] === 'dividend' ? 'success' : ($transaction['transaction_type'] === 'sale' ? 'info' : 'secondary'); ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td class="<?php echo in_array($transaction['transaction_type'], ['dividend', 'sale']) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($transaction['amount']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <a href="edit.php?id=<?php echo $investmentId; ?>" class="btn btn-primary">
        <i class="fas fa-edit me-2"></i>Edit Investment
    </a>
    <a href="transaction.php?investment_id=<?php echo $investmentId; ?>" class="btn btn-success">
        <i class="fas fa-exchange-alt me-2"></i>Add Transaction
    </a>
</div>
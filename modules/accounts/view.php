<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$accountId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get account details
$stmt = db()->prepare("
    SELECT a.*, 
           COUNT(DISTINCT t.id) as transaction_count,
           SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as total_income,
           SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as total_expense
    FROM accounts a
    LEFT JOIN transactions t ON a.id = t.account_id AND t.group_id = a.group_id
    WHERE a.id = ? AND a.group_id = ?
    GROUP BY a.id
");
$stmt->execute([$accountId, $groupId]);
$account = $stmt->fetch();

if (!$account) {
    echo '<div class="alert alert-danger m-3">Account not found</div>';
    exit;
}

// Get recent transactions
$stmt = db()->prepare("
    SELECT * FROM transactions
    WHERE account_id = ? AND group_id = ?
    ORDER BY transaction_date DESC
    LIMIT 10
");
$stmt->execute([$accountId, $groupId]);
$transactions = $stmt->fetchAll();
?>

<div class="modal-body">
    <!-- Account Header -->
    <div class="text-center mb-4">
        <?php if ($account['account_type'] === 'bank'): ?>
            <i class="fas fa-university fa-4x text-primary"></i>
        <?php elseif ($account['account_type'] === 'cash'): ?>
            <i class="fas fa-money-bill-wave fa-4x text-success"></i>
        <?php else: ?>
            <i class="fas fa-mobile-alt fa-4x text-warning"></i>
        <?php endif; ?>
        <h3 class="mt-2"><?php echo htmlspecialchars($account['account_name']); ?></h3>
        <p class="text-muted">
            <?php 
            $types = ['bank' => 'Bank Account', 'cash' => 'Cash Account', 'mobile_money' => 'Mobile Money'];
            echo $types[$account['account_type']];
            ?>
        </p>
    </div>
    
    <!-- Balance Card -->
    <div class="card bg-gradient-primary text-white mb-4">
        <div class="card-body text-center">
            <h6 class="card-title">Current Balance</h6>
            <h2 class="mb-0"><?php echo formatCurrency($account['current_balance']); ?></h2>
            <small>Opening: <?php echo formatCurrency($account['opening_balance']); ?></small>
        </div>
    </div>
    
    <!-- Account Details -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="border rounded p-3">
                <small class="text-muted">Total Income</small>
                <h4 class="text-success mb-0"><?php echo formatCurrency($account['total_income'] ?? 0); ?></h4>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3">
                <small class="text-muted">Total Expenses</small>
                <h4 class="text-danger mb-0"><?php echo formatCurrency($account['total_expense'] ?? 0); ?></h4>
            </div>
        </div>
    </div>
    
    <!-- Additional Info -->
    <div class="mb-4">
        <h6>Account Information</h6>
        <table class="table table-sm">
            <?php if ($account['account_type'] === 'bank'): ?>
            <tr>
                <td width="40%"><strong>Bank Name:</strong></td>
                <td><?php echo htmlspecialchars($account['bank_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><strong>Account Number:</strong></td>
                <td><?php echo htmlspecialchars($account['account_number'] ?? 'N/A'); ?></td>
            </tr>
            <?php elseif ($account['account_type'] === 'mobile_money'): ?>
            <tr>
                <td><strong>Mobile Number:</strong></td>
                <td><?php echo htmlspecialchars($account['mobile_number'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><strong>Provider:</strong></td>
                <td><?php echo htmlspecialchars($account['mobile_provider'] ?? 'N/A'); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="badge bg-<?php echo $account['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($account['status']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Created:</strong></td>
                <td><?php echo formatDateTime($account['created_at']); ?></td>
            </tr>
            <?php if ($account['description']): ?>
            <tr>
                <td><strong>Description:</strong></td>
                <td><?php echo nl2br(htmlspecialchars($account['description'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- Recent Transactions -->
    <?php if (!empty($transactions)): ?>
    <div>
        <h6>Recent Transactions</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $transaction['transaction_type'] === 'income' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars(substr($transaction['description'], 0, 40)); ?></td>
                        <td class="text-end <?php echo $transaction['transaction_type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($transaction['amount']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($account['transaction_count'] ?? 0) > 10): ?>
            <div class="text-center mt-2">
                <small class="text-muted">Showing 10 of <?php echo number_format($account['transaction_count']); ?> transactions</small>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <a href="../transactions/add.php?account_id=<?php echo $accountId; ?>" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Add Transaction
    </a>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
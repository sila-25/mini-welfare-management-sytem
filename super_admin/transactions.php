<?php
$transactions = [];
$stmt = db()->prepare("
    SELECT pt.*, g.group_name, g.group_code 
    FROM payment_transactions pt 
    JOIN groups g ON pt.group_id = g.id 
    ORDER BY pt.created_at DESC 
    LIMIT 100
");
$stmt->execute();
$transactions = $stmt->fetchAll();
?>

<div class="transactions-management">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Payment Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Group</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?php echo $t['id']; ?> </div></div></div></div></div></div></div></div></div></div></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['group_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo $t['group_code']; ?></small>
                                 </div></div></div></div></div></div></div></div></div></div></td>
                                <td class="text-success fw-bold"><?php echo formatCurrency($t['amount']); ?> </div></div></div></div></div></div></div></div></div></div></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $t['payment_method'])); ?> </div></div></div></div></div></div></div></div></div></div></td>
                                <td><code><?php echo $t['transaction_id']; ?></code> </div></div></div></div></div></div></div></div></div></div></td>
                                <td><?php echo formatDateTime($t['payment_date']); ?> </div></div></div></div></div></div></div></div></div></div></td>
                                <td>
                                    <span class="badge bg-<?php echo $t['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($t['status']); ?>
                                    </span>
                                 </div></div></div></div></div></div></div></div></div></div></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                 </div>
                </div>
            </div>
        </div>
    </div>
</div>
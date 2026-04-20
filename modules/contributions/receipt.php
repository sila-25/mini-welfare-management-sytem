<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$contributionId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

$stmt = db()->prepare("
    SELECT c.*, 
           m.first_name, m.last_name, m.member_number, m.phone, m.email,
           u.first_name as recorded_by_name
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    LEFT JOIN users u ON c.recorded_by = u.id
    WHERE c.id = ? AND c.group_id = ?
");
$stmt->execute([$contributionId, $groupId]);
$contribution = $stmt->fetch();

if (!$contribution) {
    echo '<div class="alert alert-danger">Contribution not found</div>';
    exit;
}

$stmt = db()->prepare("SELECT group_name FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();
?>

<div class="receipt-container">
    <div class="text-center mb-3">
        <h4><?php echo htmlspecialchars($group['group_name']); ?></h4>
        <p class="mb-0"><strong>OFFICIAL RECEIPT</strong></p>
        <small>Receipt No: <?php echo htmlspecialchars($contribution['receipt_number']); ?></small>
    </div>
    
    <hr>
    
    <div class="row mb-2">
        <div class="col-5"><strong>Date:</strong></div>
        <div class="col-7"><?php echo formatDate($contribution['payment_date']); ?></div>
    </div>
    <div class="row mb-2">
        <div class="col-5"><strong>Member:</strong></div>
        <div class="col-7"><?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?></div>
    </div>
    <div class="row mb-2">
        <div class="col-5"><strong>Member No:</strong></div>
        <div class="col-7"><?php echo htmlspecialchars($contribution['member_number']); ?></div>
    </div>
    
    <hr>
    
    <div class="row mb-2">
        <div class="col-5"><strong>Description:</strong></div>
        <div class="col-7"><?php echo ucfirst($contribution['contribution_type']); ?> Contribution</div>
    </div>
    <div class="row mb-2">
        <div class="col-5"><strong>Amount:</strong></div>
        <div class="col-7 text-success fw-bold">KES <?php echo number_format($contribution['amount'], 2); ?></div>
    </div>
    <div class="row mb-2">
        <div class="col-5"><strong>Payment Method:</strong></div>
        <div class="col-7"><?php echo ucfirst(str_replace('_', ' ', $contribution['payment_method'])); ?></div>
    </div>
    
    <?php if ($contribution['reference_number']): ?>
    <div class="row mb-2">
        <div class="col-5"><strong>Reference:</strong></div>
        <div class="col-7"><?php echo htmlspecialchars($contribution['reference_number']); ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($contribution['notes']): ?>
    <div class="row mb-2">
        <div class="col-5"><strong>Notes:</strong></div>
        <div class="col-7"><?php echo nl2br(htmlspecialchars($contribution['notes'])); ?></div>
    </div>
    <?php endif; ?>
    
    <hr>
    
    <div class="text-center mt-3">
        <small class="text-muted">
            Recorded by: <?php echo htmlspecialchars($contribution['recorded_by_name'] ?? 'System'); ?><br>
            <?php echo formatDateTime($contribution['created_at']); ?>
        </small>
    </div>
</div>

<style>
.receipt-container {
    font-size: 14px;
    line-height: 1.5;
}
</style>
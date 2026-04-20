<?php
$message = '';

if (isset($_GET['activate']) && isset($_GET['id'])) {
    $stmt = db()->prepare("UPDATE subscription_plans SET is_active = 1 WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $message = "Plan activated!";
}
if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $stmt = db()->prepare("UPDATE subscription_plans SET is_active = 0 WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $message = "Plan deactivated!";
}

$plans = [];
$stmt = db()->prepare("SELECT * FROM subscription_plans ORDER BY duration_days");
$stmt->execute();
$plans = $stmt->fetchAll();
?>

<div class="plans-management">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <?php foreach ($plans as $plan): 
            $features = json_decode($plan['features'], true);
        ?>
            <div class="col-md-3 mb-4">
                <div class="card h-100">
                    <div class="card-header text-center bg-<?php echo $plan['plan_code'] === 'annual' ? 'warning' : 'primary'; ?> text-white">
                        <h4><?php echo $plan['plan_name']; ?></h4>
                        <h3><?php echo formatCurrency($plan['price']); ?></h3>
                        <small><?php echo $plan['duration_days']; ?> days</small>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-users me-2"></i> Max Members: <?php echo $features['max_members'] ?? 100; ?></li>
                            <li><i class="fas fa-hand-holding-usd me-2"></i> Max Loans: <?php echo $features['max_loans'] ?? 50; ?></li>
                            <li><i class="fas fa-chart-bar me-2"></i> Reports: <?php echo ($features['reports'] ?? false) ? 'Yes' : 'No'; ?></li>
                            <li><i class="fas fa-headset me-2"></i> Support: <?php echo ucfirst($features['support'] ?? 'email'); ?></li>
                        </ul>
                    </div>
                    <div class="card-footer text-center">
                        <?php if ($plan['is_active']): ?>
                            <span class="badge bg-success me-2">Active</span>
                            <a href="?section=plans&deactivate=1&id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this plan?')">Deactivate</a>
                        <?php else: ?>
                            <span class="badge bg-secondary me-2">Inactive</span>
                            <a href="?section=plans&activate=1&id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate this plan?')">Activate</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
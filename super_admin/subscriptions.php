<?php
// This file is included within super admin dashboard

$message = '';
$error = '';

// Get all subscription plans
$stmt = db()->prepare("SELECT * FROM subscription_plans ORDER BY duration_days");
$stmt->execute();
$plans = $stmt->fetchAll();

// Handle plan activation/deactivation
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $planId = (int)$_GET['id'];
    $stmt = db()->prepare("UPDATE subscription_plans SET is_active = 1 WHERE id = ?");
    $stmt->execute([$planId]);
    $message = "Plan activated successfully!";
}

if (isset($_GET['deactivate']) && isset($_GET['id'])) {
    $planId = (int)$_GET['id'];
    $stmt = db()->prepare("UPDATE subscription_plans SET is_active = 0 WHERE id = ?");
    $stmt->execute([$planId]);
    $message = "Plan deactivated successfully!";
}

// Get plan statistics
$planStats = [];
$stmt = db()->prepare("
    SELECT subscription_plan, COUNT(*) as count 
    FROM groups 
    GROUP BY subscription_plan
");
$stmt->execute();
while ($row = $stmt->fetch()) {
    $planStats[$row['subscription_plan']] = $row['count'];
}
?>

<div class="subscriptions-management">
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <?php foreach ($plans as $plan): 
            $features = json_decode($plan['features'], true);
        ?>
            <div class="col-md-3 mb-4">
                <div class="card plan-card h-100">
                    <div class="card-header text-center bg-<?php echo $plan['plan_code'] === 'annual' ? 'warning' : 'primary'; ?> text-white">
                        <h4 class="mb-0"><?php echo htmlspecialchars($plan['plan_name']); ?></h4>
                        <h2 class="mt-2"><?php echo formatCurrency($plan['price']); ?></h2>
                        <small>for <?php echo $plan['duration_days']; ?> days</small>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-users text-primary me-2"></i>
                                Max Members: <strong><?php echo number_format($features['max_members'] ?? 100); ?></strong>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-hand-holding-usd text-primary me-2"></i>
                                Max Loans: <strong><?php echo number_format($features['max_loans'] ?? 50); ?></strong>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-chart-bar text-primary me-2"></i>
                                Reports: <strong><?php echo ($features['reports'] ?? false) ? 'Yes' : 'No'; ?></strong>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-headset text-primary me-2"></i>
                                Support: <strong><?php echo ucfirst($features['support'] ?? 'email'); ?></strong>
                            </li>
                            <li class="mt-3">
                                <i class="fas fa-building text-info me-2"></i>
                                Active Groups: <strong><?php echo number_format($planStats[$plan['plan_code']] ?? 0); ?></strong>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <?php if ($plan['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                            <a href="?section=subscriptions&deactivate=1&id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Deactivate this plan?')">
                                <i class="fas fa-ban me-1"></i> Deactivate
                            </a>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <a href="?section=subscriptions&activate=1&id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate this plan?')">
                                <i class="fas fa-check me-1"></i> Activate
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.plan-card {
    transition: transform 0.3s, box-shadow 0.3s;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.plan-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
</style>
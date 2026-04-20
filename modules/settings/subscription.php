<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Only chairperson and super admin can access
if ($_SESSION['user_role'] !== 'chairperson' && $_SESSION['user_role'] !== 'super_admin') {
    setFlash('danger', 'You do not have permission to access settings');
    redirect('/dashboard/home.php');
}

Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];

// Get current group subscription
$stmt = db()->prepare("
    SELECT s.*, sp.plan_name, sp.duration_days, sp.price as plan_price, sp.features
    FROM subscriptions s
    JOIN subscription_plans sp ON s.plan = sp.plan_code
    WHERE s.group_id = ? AND s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->execute([$groupId]);
$currentSubscription = $stmt->fetch();

// Get subscription history
$stmt = db()->prepare("
    SELECT s.*, sp.plan_name, sp.price as plan_price
    FROM subscriptions s
    JOIN subscription_plans sp ON s.plan = sp.plan_code
    WHERE s.group_id = ?
    ORDER BY s.created_at DESC
    LIMIT 5
");
$stmt->execute([$groupId]);
$subscriptionHistory = $stmt->fetchAll();

// Get available plans
$stmt = db()->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY duration_days");
$stmt->execute();
$plans = $stmt->fetchAll();

// Handle subscription renewal/purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/settings/subscriptions.php');
    }
    
    try {
        db()->beginTransaction();
        
        $planCode = $_POST['plan_code'];
        $paymentMethod = $_POST['payment_method'];
        
        // Get plan details
        $stmt = db()->prepare("SELECT * FROM subscription_plans WHERE plan_code = ?");
        $stmt->execute([$planCode]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            throw new Exception('Invalid subscription plan');
        }
        
        // Calculate dates
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));
        
        // Create subscription record
        $stmt = db()->prepare("
            INSERT INTO subscriptions (group_id, plan, amount, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$groupId, $planCode, $plan['price'], $startDate, $endDate]);
        $subscriptionId = db()->lastInsertId();
        
        // Create payment transaction record
        $transactionId = 'TXN_' . time() . '_' . $groupId;
        $stmt = db()->prepare("
            INSERT INTO payment_transactions (group_id, subscription_id, amount, payment_method, transaction_id, payment_date, status)
            VALUES (?, ?, ?, ?, ?, NOW(), 'completed')
        ");
        $stmt->execute([$groupId, $subscriptionId, $plan['price'], $paymentMethod, $transactionId]);
        
        // Update group subscription info
        $stmt = db()->prepare("
            UPDATE groups SET 
                subscription_plan = ?,
                subscription_status = 'active',
                subscription_start = ?,
                subscription_end = ?
            WHERE id = ?
        ");
        $stmt->execute([$planCode, $startDate, $endDate, $groupId]);
        
        auditLog('subscription_' . $_POST['action'], 'subscriptions', $subscriptionId, null, [
            'plan' => $planCode,
            'amount' => $plan['price'],
            'payment_method' => $paymentMethod
        ]);
        
        db()->commit();
        
        // Send confirmation email
        $stmt = db()->prepare("SELECT group_name, email FROM groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        $subject = "Subscription Confirmation - {$plan['plan_name']} Plan";
        $body = "
            <h3>Subscription Confirmation</h3>
            <p>Dear {$group['group_name']} Administrator,</p>
            <p>Your subscription to the <strong>{$plan['plan_name']}</strong> plan has been activated.</p>
            <p><strong>Start Date:</strong> " . formatDate($startDate) . "</p>
            <p><strong>End Date:</strong> " . formatDate($endDate) . "</p>
            <p><strong>Amount Paid:</strong> " . formatCurrency($plan['price']) . "</p>
            <p><strong>Transaction ID:</strong> {$transactionId}</p>
            <p>Thank you for choosing " . APP_NAME . "!</p>
        ";
        sendEmail($group['email'], $subject, $body);
        
        setFlash('success', "Successfully subscribed to the {$plan['plan_name']} plan!");
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/modules/settings/subscriptions.php');
}

$page_title = 'Subscriptions';
?>
<div class="settings-section">
    <!-- Current Subscription Status -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Subscription</h5>
        </div>
        <div class="card-body">
            <?php if ($currentSubscription): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Plan:</strong> <?php echo htmlspecialchars($currentSubscription['plan_name']); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php echo $currentSubscription['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($currentSubscription['status']); ?>
                            </span>
                        </p>
                        <p><strong>Start Date:</strong> <?php echo formatDate($currentSubscription['start_date']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>End Date:</strong> <?php echo formatDate($currentSubscription['end_date']); ?></p>
                        <p><strong>Days Remaining:</strong> 
                            <?php 
                            $daysLeft = (strtotime($currentSubscription['end_date']) - time()) / 86400;
                            if ($daysLeft > 0) {
                                echo ceil($daysLeft) . ' days';
                            } else {
                                echo '<span class="text-danger">Expired</span>';
                            }
                            ?>
                        </p>
                        <p><strong>Amount Paid:</strong> <?php echo formatCurrency($currentSubscription['amount']); ?></p>
                    </div>
                </div>
                <?php 
                $features = json_decode($currentSubscription['features'], true);
                if ($features): 
                ?>
                <div class="mt-3">
                    <strong>Features Included:</strong>
                    <ul class="mb-0">
                        <?php foreach ($features as $key => $value): ?>
                            <li><?php echo ucfirst(str_replace('_', ' ', $key)) . ': ' . ($value === true ? 'Yes' : $value); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No active subscription found. Please select a plan below to activate your subscription.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Available Plans -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Available Subscription Plans</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($plans as $plan): 
                    $features = json_decode($plan['features'], true);
                ?>
                    <div class="col-md-3 mb-4">
                        <div class="card plan-card h-100">
                            <div class="card-header text-center bg-<?php echo $plan['plan_code'] === 'annual' ? 'warning' : 'light'; ?>">
                                <h4 class="mb-0"><?php echo htmlspecialchars($plan['plan_name']); ?></h4>
                                <h2 class="text-primary mt-2"><?php echo formatCurrency($plan['price']); ?></h2>
                                <small>for <?php echo $plan['duration_days']; ?> days</small>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <?php if ($features): ?>
                                        <?php foreach ($features as $key => $value): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $key)); ?>: 
                                                <strong><?php echo $value === true ? '✓' : $value; ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <button class="btn btn-<?php echo $plan['plan_code'] === 'annual' ? 'warning' : 'primary'; ?> w-100" 
                                        onclick="subscribeToPlan('<?php echo $plan['plan_code']; ?>', '<?php echo addslashes($plan['plan_name']); ?>', <?php echo $plan['price']; ?>)">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    <?php echo $currentSubscription && $currentSubscription['plan'] === $plan['plan_code'] ? 'Current Plan' : 'Subscribe Now'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Subscription History -->
    <?php if (!empty($subscriptionHistory)): ?>
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-history me-2"></i>Subscription History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptionHistory as $history): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($history['plan_name']); ?></td>
                                <td><?php echo formatCurrency($history['amount']); ?></td>
                                <td><?php echo formatDate($history['start_date']); ?></td>
                                <td><?php echo formatDate($history['end_date']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $history['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Complete Subscription</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="purchase">
                <input type="hidden" name="plan_code" id="selected_plan_code">
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <h4 id="plan_name_display"></h4>
                        <h3 class="text-primary" id="plan_price_display"></h3>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money (M-PESA)</option>
                            <option value="card">Credit/Debit Card</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        After payment confirmation, your subscription will be activated immediately.
                        You will receive a confirmation email with your invoice.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Payment</button>
                </div>
            </form>
        </div>
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
.plan-card .card-header {
    border-bottom: none;
}
</style>

<script>
function subscribeToPlan(planCode, planName, planPrice) {
    document.getElementById('selected_plan_code').value = planCode;
    document.getElementById('plan_name_display').innerHTML = planName + ' Plan';
    document.getElementById('plan_price_display').innerHTML = formatCurrency(planPrice);
    $('#paymentModal').modal('show');
}

function formatCurrency(amount) {
    return 'KES ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php include_once '../../templates/footer.php'; ?>
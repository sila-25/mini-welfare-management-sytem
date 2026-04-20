<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

// Only super admin can access
if ($_SESSION['user_role'] !== 'super_admin') {
    setFlash('danger', 'Access denied. Super admin privileges required.');
    redirect('/dashboard/home.php');
}

Middleware::requireLogin();

$page_title = 'Subscription Plans';
$current_page = 'admin';

// Handle plan management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/admin/subscriptions.php');
    }
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $planName = trim($_POST['plan_name']);
            $planCode = strtolower(trim($_POST['plan_code']));
            $durationDays = (int)$_POST['duration_days'];
            $price = (float)$_POST['price'];
            $features = json_encode([
                'max_members' => (int)$_POST['max_members'],
                'max_loans' => (int)$_POST['max_loans'],
                'reports' => isset($_POST['reports']) ? true : false,
                'support' => $_POST['support']
            ]);
            
            $stmt = db()->prepare("
                INSERT INTO subscription_plans (plan_name, plan_code, duration_days, price, features)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$planName, $planCode, $durationDays, $price, $features]);
            setFlash('success', 'Plan added successfully');
            
        } elseif ($action === 'edit') {
            $planId = (int)$_POST['plan_id'];
            $planName = trim($_POST['plan_name']);
            $durationDays = (int)$_POST['duration_days'];
            $price = (float)$_POST['price'];
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $features = json_encode([
                'max_members' => (int)$_POST['max_members'],
                'max_loans' => (int)$_POST['max_loans'],
                'reports' => isset($_POST['reports']) ? true : false,
                'support' => $_POST['support']
            ]);
            
            $stmt = db()->prepare("
                UPDATE subscription_plans SET 
                    plan_name = ?, duration_days = ?, price = ?, 
                    features = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$planName, $durationDays, $price, $features, $isActive, $planId]);
            setFlash('success', 'Plan updated successfully');
        }
        
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/admin/subscriptions.php');
}

// Get all plans
$stmt = db()->prepare("SELECT * FROM subscription_plans ORDER BY duration_days");
$stmt->execute();
$plans = $stmt->fetchAll();

// Get subscription statistics
$stmt = db()->prepare("
    SELECT 
        sp.plan_name,
        COUNT(g.id) as group_count,
        SUM(CASE WHEN g.subscription_status = 'active' THEN 1 ELSE 0 END) as active_count
    FROM subscription_plans sp
    LEFT JOIN groups g ON sp.plan_code = g.subscription_plan
    GROUP BY sp.id
");
$stmt->execute();
$planStats = $stmt->fetchAll();

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-tags me-2"></i>Subscription Plans</h1>
            <p class="text-muted mt-2">Manage subscription plans and pricing</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
            <i class="fas fa-plus me-2"></i>Add New Plan
        </button>
    </div>

    <!-- Plans Grid -->
    <div class="row">
        <?php foreach ($plans as $plan):
            $features = json_decode($plan['features'], true);
            $stats = array_filter($planStats, function($s) use ($plan) { return $s['plan_name'] === $plan['plan_name']; });
            $stats = array_shift($stats);
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
                                Groups: <strong><?php echo number_format($stats['group_count'] ?? 0); ?></strong>
                                <br><small>(<?php echo number_format($stats['active_count'] ?? 0); ?> active)</small>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <button class="btn btn-sm btn-primary" onclick="editPlan(<?php echo htmlspecialchars(json_encode($plan)); ?>)">
                            <i class="fas fa-edit me-1"></i> Edit Plan
                        </button>
                        <?php if ($plan['is_active']): ?>
                            <button class="btn btn-sm btn-secondary" onclick="togglePlan(<?php echo $plan['id']; ?>, false)">
                                <i class="fas fa-ban me-1"></i> Disable
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-success" onclick="togglePlan(<?php echo $plan['id']; ?>, true)">
                                <i class="fas fa-check me-1"></i> Enable
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Subscription Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plan Name</label>
                            <input type="text" class="form-control" name="plan_name" required placeholder="e.g., Premium">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plan Code</label>
                            <input type="text" class="form-control" name="plan_code" required placeholder="e.g., premium">
                            <small class="text-muted">Unique identifier (lowercase, no spaces)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" class="form-control" name="duration_days" required value="30">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (KES)</label>
                            <input type="number" class="form-control" name="price" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Members</label>
                            <input type="number" class="form-control" name="max_members" value="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Loans</label>
                            <input type="number" class="form-control" name="max_loans" value="50">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="reports" id="reports" checked>
                                <label class="form-check-label" for="reports">
                                    Include Reports
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Support Level</label>
                            <select class="form-select" name="support">
                                <option value="email">Email Support</option>
                                <option value="priority_email">Priority Email</option>
                                <option value="phone">Phone Support</option>
                                <option value="dedicated">Dedicated Support</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="plan_id" id="edit_plan_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plan Name</label>
                            <input type="text" class="form-control" name="plan_name" id="edit_plan_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plan Code</label>
                            <input type="text" class="form-control" id="edit_plan_code" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (Days)</label>
                            <input type="number" class="form-control" name="duration_days" id="edit_duration_days" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price (KES)</label>
                            <input type="number" class="form-control" name="price" id="edit_price" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Members</label>
                            <input type="number" class="form-control" name="max_members" id="edit_max_members">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Loans</label>
                            <input type="number" class="form-control" name="max_loans" id="edit_max_loans">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" name="reports" id="edit_reports">
                                <label class="form-check-label" for="edit_reports">
                                    Include Reports
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Support Level</label>
                            <select class="form-select" name="support" id="edit_support">
                                <option value="email">Email Support</option>
                                <option value="priority_email">Priority Email</option>
                                <option value="phone">Phone Support</option>
                                <option value="dedicated">Dedicated Support</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Plan Active
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
</style>

<script>
function editPlan(plan) {
    document.getElementById('edit_plan_id').value = plan.id;
    document.getElementById('edit_plan_name').value = plan.plan_name;
    document.getElementById('edit_plan_code').value = plan.plan_code;
    document.getElementById('edit_duration_days').value = plan.duration_days;
    document.getElementById('edit_price').value = plan.price;
    
    const features = JSON.parse(plan.features || '{}');
    document.getElementById('edit_max_members').value = features.max_members || 100;
    document.getElementById('edit_max_loans').value = features.max_loans || 50;
    document.getElementById('edit_reports').checked = features.reports || false;
    document.getElementById('edit_support').value = features.support || 'email';
    document.getElementById('edit_is_active').checked = plan.is_active == 1;
    
    $('#editPlanModal').modal('show');
}

function togglePlan(id, activate) {
    const action = activate ? 'activate' : 'deactivate';
    Swal.fire({
        title: `${activate ? 'Activate' : 'Deactivate'} Plan?`,
        text: `Are you sure you want to ${action} this subscription plan?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: `Yes, ${action}`
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `subscriptions.php?${action}=yes&id=${id}`;
        }
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>
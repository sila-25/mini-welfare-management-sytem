<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is chairperson
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'chairperson') {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit();
}

$page_title = 'Group Dashboard';
$current_page = 'admin';
$active_tab = $_GET['tab'] ?? 'overview';
$groupId = $_SESSION['group_id'];

// Get group details
$stmt = db()->prepare("SELECT * FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

// Get member statistics
$stmt = db()->prepare("
    SELECT 
        COUNT(*) as total_members,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members
    FROM members WHERE group_id = ?
");
$stmt->execute([$groupId]);
$memberStats = $stmt->fetch();
$memberStats['total_members'] = $memberStats['total_members'] ?? 0;
$memberStats['active_members'] = $memberStats['active_members'] ?? 0;

// Get loan statistics
$stmt = db()->prepare("
    SELECT 
        COUNT(*) as total_loans,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_loans,
        SUM(CASE WHEN status = 'disbursed' THEN 1 ELSE 0 END) as active_loans
    FROM loans WHERE group_id = ?
");
$stmt->execute([$groupId]);
$loanStats = $stmt->fetch();
$loanStats['total_loans'] = $loanStats['total_loans'] ?? 0;
$loanStats['pending_loans'] = $loanStats['pending_loans'] ?? 0;
$loanStats['active_loans'] = $loanStats['active_loans'] ?? 0;

// Get current loan settings
$interest_rate_monthly = getGroupSetting($groupId, 'interest_rate_monthly', 1);
$min_loan_amount = getGroupSetting($groupId, 'min_loan_amount', 1000);
$max_loan_amount = getGroupSetting($groupId, 'max_loan_amount', 100000);
$min_loan_duration = getGroupSetting($groupId, 'min_loan_duration', 1);
$max_loan_duration = getGroupSetting($groupId, 'max_loan_duration', 12);
$processing_fee = getGroupSetting($groupId, 'processing_fee', 0);
$max_loan_ratio = getGroupSetting($groupId, 'max_loan_ratio', 3);
$late_penalty_rate = getGroupSetting($groupId, 'late_penalty_rate', 5);

// Check subscription status
$subscription_active = ($group['subscription_status'] === 'active' && strtotime($group['subscription_end']) > time());

// Handle loan settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_loan_settings') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/admin/dashboard.php?tab=loan-settings');
    }
    
    try {
        db()->beginTransaction();
        
        $settingStmt = db()->prepare("
            INSERT INTO group_settings (group_id, setting_key, setting_value, setting_type)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        $settingStmt->execute([$groupId, 'interest_rate_monthly', $_POST['interest_rate_monthly'], 'number']);
        $settingStmt->execute([$groupId, 'min_loan_amount', $_POST['min_loan_amount'], 'number']);
        $settingStmt->execute([$groupId, 'max_loan_amount', $_POST['max_loan_amount'], 'number']);
        $settingStmt->execute([$groupId, 'min_loan_duration', $_POST['min_loan_duration'], 'number']);
        $settingStmt->execute([$groupId, 'max_loan_duration', $_POST['max_loan_duration'], 'number']);
        $settingStmt->execute([$groupId, 'processing_fee', $_POST['processing_fee'], 'number']);
        $settingStmt->execute([$groupId, 'max_loan_ratio', $_POST['max_loan_ratio'], 'number']);
        $settingStmt->execute([$groupId, 'late_penalty_rate', $_POST['late_penalty_rate'], 'number']);
        
        db()->commit();
        setFlash('success', 'Loan settings updated successfully!');
        redirect('/admin/dashboard.php?tab=loan-settings');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

include_once '../templates/header.php';
include_once '../templates/sidebar.php';
include_once '../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-building me-2 text-primary"></i>Group Dashboard</h1>
                <p class="text-muted mt-2">Manage <?php echo htmlspecialchars($group['group_name']); ?></p>
            </div>
            <div>
                <a href="<?php echo APP_URL; ?>/pages/logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </div>
        </div>

        <?php if (!$subscription_active): ?>
            <div class="alert alert-warning mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Subscription Expired!</strong> Please renew to continue using all features.
                <a href="?tab=subscription" class="alert-link ms-3">Renew Now</a>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h6 class="card-title">Total Members</h6>
                        <h2 class="mb-0"><?php echo number_format($memberStats['total_members']); ?></h2>
                        <small><?php echo number_format($memberStats['active_members']); ?> active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h6 class="card-title">Pending Loans</h6>
                        <h2 class="mb-0"><?php echo number_format($loanStats['pending_loans']); ?></h2>
                        <small>Awaiting approval</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h6 class="card-title">Active Loans</h6>
                        <h2 class="mb-0"><?php echo number_format($loanStats['active_loans']); ?></h2>
                        <small>Currently active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h6 class="card-title">Group Code</h6>
                        <h2 class="mb-0"><?php echo $group['group_code']; ?></h2>
                        <small>Share with members</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Tabs -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'overview' ? 'active' : ''; ?>" href="?tab=overview">Overview</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'members' ? 'active' : ''; ?>" href="?tab=members">Members</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'loans' ? 'active' : ''; ?>" href="?tab=loans">Loans</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'contributions' ? 'active' : ''; ?>" href="?tab=contributions">Contributions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'meetings' ? 'active' : ''; ?>" href="?tab=meetings">Meetings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'elections' ? 'active' : ''; ?>" href="?tab=elections">Elections</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'loan-settings' ? 'active' : ''; ?>" href="?tab=loan-settings">
                            <i class="fas fa-hand-holding-usd me-1"></i>Loan Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $active_tab === 'subscription' ? 'active' : ''; ?>" href="?tab=subscription">Subscription</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <?php if ($active_tab === 'overview'): ?>
                    <div class="alert alert-info">
                        <strong>Group Information:</strong><br>
                        Name: <?php echo htmlspecialchars($group['group_name']); ?><br>
                        Code: <?php echo $group['group_code']; ?><br>
                        Plan: <?php echo ucfirst($group['subscription_plan']); ?><br>
                        Status: <span class="badge bg-<?php echo $subscription_active ? 'success' : 'danger'; ?>">
                            <?php echo $subscription_active ? 'Active' : 'Expired'; ?>
                        </span><br>
                        Expires: <?php echo formatDate($group['subscription_end']); ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Quick Links</div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="?tab=members" class="btn btn-outline-primary">Manage Members</a>
                                        <a href="?tab=loans" class="btn btn-outline-warning">Review Loan Applications</a>
                                        <a href="?tab=contributions" class="btn btn-outline-success">View Contributions</a>
                                        <a href="?tab=meetings" class="btn btn-outline-info">Schedule Meeting</a>
                                        <a href="?tab=loan-settings" class="btn btn-outline-secondary">Configure Loan Terms</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Current Loan Terms</div>
                                <div class="card-body">
                                    <p><strong>Interest Rate:</strong> <?php echo $interest_rate_monthly; ?>% per month</p>
                                    <p><strong>Loan Range:</strong> <?php echo formatCurrency($min_loan_amount); ?> - <?php echo formatCurrency($max_loan_amount); ?></p>
                                    <p><strong>Loan Period:</strong> <?php echo $min_loan_duration; ?> - <?php echo $max_loan_duration; ?> months</p>
                                    <p><strong>Processing Fee:</strong> <?php echo formatCurrency($processing_fee); ?></p>
                                    <p><strong>Max Loan Ratio:</strong> <?php echo $max_loan_ratio; ?>x contributions</p>
                                    <a href="?tab=loan-settings" class="btn btn-sm btn-primary mt-2">Edit Loan Terms</a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'loan-settings'): ?>
                    <!-- Loan Settings Form -->
                    <div class="card border-0">
                        <div class="card-header bg-white px-0 pt-0">
                            <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Configure Loan Terms</h5>
                            <p class="text-muted mt-2">Set the loan terms that will apply to all members of this group</p>
                        </div>
                        <div class="card-body px-0">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="update_loan_settings">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Interest Rate (Monthly) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="interest_rate_monthly" step="0.5" min="0" max="20" 
                                                   value="<?php echo $interest_rate_monthly; ?>" required>
                                            <span class="input-group-text">% per month</span>
                                        </div>
                                        <small class="text-muted">Monthly interest charged on loans (0-20%)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Processing Fee</label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number" class="form-control" name="processing_fee" step="50" min="0" 
                                                   value="<?php echo $processing_fee; ?>">
                                        </div>
                                        <small class="text-muted">One-time fee charged when loan is disbursed</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Minimum Loan Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number" class="form-control" name="min_loan_amount" step="100" min="100" 
                                                   value="<?php echo $min_loan_amount; ?>" required>
                                        </div>
                                        <small class="text-muted">Lowest amount a member can borrow</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Maximum Loan Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number" class="form-control" name="max_loan_amount" step="1000" min="1000" 
                                                   value="<?php echo $max_loan_amount; ?>" required>
                                        </div>
                                        <small class="text-muted">Highest amount a member can borrow</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Minimum Loan Period <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="min_loan_duration" step="1" min="1" max="12" 
                                                   value="<?php echo $min_loan_duration; ?>" required>
                                            <span class="input-group-text">months</span>
                                        </div>
                                        <small class="text-muted">Shortest allowed repayment period (1-12 months)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Maximum Loan Period <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="max_loan_duration" step="1" min="1" max="36" 
                                                   value="<?php echo $max_loan_duration; ?>" required>
                                            <span class="input-group-text">months</span>
                                        </div>
                                        <small class="text-muted">Longest allowed repayment period (1-36 months)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Maximum Loan Ratio</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="max_loan_ratio" step="0.5" min="1" max="10" 
                                                   value="<?php echo $max_loan_ratio; ?>">
                                            <span class="input-group-text">x contributions</span>
                                        </div>
                                        <small class="text-muted">Multiple of member's total contributions</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Late Penalty Rate</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="late_penalty_rate" step="0.5" min="0" max="20" 
                                                   value="<?php echo $late_penalty_rate; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Additional interest on overdue payments</small>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Note:</strong> Changes will apply to new loan applications only. Existing loans are not affected.
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Loan Settings</button>
                                    <a href="?tab=overview" class="btn btn-secondary ms-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($active_tab === 'members'): ?>
                    <?php include_once '../modules/members/list.php'; ?>
                <?php elseif ($active_tab === 'loans'): ?>
                    <?php include_once '../modules/loans/index.php'; ?>
                <?php elseif ($active_tab === 'contributions'): ?>
                    <?php include_once '../modules/contributions/list.php'; ?>
                <?php elseif ($active_tab === 'meetings'): ?>
                    <?php include_once '../modules/meetings/index.php'; ?>
                <?php elseif ($active_tab === 'elections'): ?>
                    <?php include_once '../modules/elections/index.php'; ?>
                <?php elseif ($active_tab === 'settings'): ?>
                    <?php include_once '../modules/settings/index.php'; ?>
                <?php elseif ($active_tab === 'subscription'): ?>
                    <div class="alert alert-info">
                        <strong>Current Subscription:</strong><br>
                        Plan: <?php echo ucfirst($group['subscription_plan']); ?><br>
                        Status: <span class="badge bg-<?php echo $subscription_active ? 'success' : 'danger'; ?>">
                            <?php echo $subscription_active ? 'Active' : 'Expired'; ?>
                        </span><br>
                        Valid Until: <?php echo formatDate($group['subscription_end']); ?>
                    </div>
                    <div class="card">
                        <div class="card-header">Renew Subscription</div>
                        <div class="card-body">
                            <form method="POST" action="<?php echo APP_URL; ?>/modules/settings/subscriptions.php">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="renew">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label>Select Plan</label>
                                        <select name="plan" class="form-select">
                                            <option value="monthly">Monthly - KES 29.99</option>
                                            <option value="quarterly">Quarterly - KES 79.99</option>
                                            <option value="biannual">Biannual - KES 149.99</option>
                                            <option value="annual">Annual - KES 299.99</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Payment Method</label>
                                        <select name="payment_method" class="form-select">
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="mobile_money">Mobile Money</option>
                                            <option value="card">Credit/Debit Card</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">Renew Subscription</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link {
    color: #495057;
    border: none;
    padding: 10px 20px;
}
.nav-tabs .nav-link:hover {
    border-bottom: 2px solid #667eea;
}
.nav-tabs .nav-link.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
    background: transparent;
}
</style>

<?php include_once '../templates/footer.php'; ?>
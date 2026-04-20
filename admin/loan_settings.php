<?php
// This file is included within admin dashboard
// Handle loan settings for all groups

// Get all groups
$stmt = db()->prepare("SELECT id, group_name, group_code FROM groups ORDER BY group_name");
$stmt->execute();
$groups = $stmt->fetchAll();

$selected_group = $_GET['group_id'] ?? ($groups[0]['id'] ?? 0);
$message = '';
$error = '';

// Get settings for selected group
$settings = [];
if ($selected_group) {
    $stmt = db()->prepare("SELECT setting_key, setting_value FROM group_settings WHERE group_id = ?");
    $stmt->execute([$selected_group]);
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper function
function getSetting($settings, $key, $default = '') {
    return $settings[$key] ?? $default;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_loan_settings') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid security token';
    } else {
        $group_id = (int)$_POST['group_id'];
        
        try {
            db()->beginTransaction();
            
            $settingStmt = db()->prepare("
                INSERT INTO group_settings (group_id, setting_key, setting_value, setting_type)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $loanSettings = [
                'default_interest_rate' => 'number',
                'min_loan_amount' => 'number',
                'max_loan_amount' => 'number',
                'max_loan_ratio' => 'number',
                'late_penalty_rate' => 'number',
                'processing_fee' => 'number',
                'min_loan_duration' => 'number',
                'max_loan_duration' => 'number'
            ];
            
            foreach ($loanSettings as $key => $type) {
                if (isset($_POST[$key])) {
                    $settingStmt->execute([$group_id, $key, $_POST[$key], $type]);
                }
            }
            
            auditLog('update_loan_settings', 'group_settings', $group_id, null, $_POST);
            
            db()->commit();
            $message = 'Loan settings updated successfully!';
            
            // Refresh settings
            $stmt = db()->prepare("SELECT setting_key, setting_value FROM group_settings WHERE group_id = ?");
            $stmt->execute([$group_id]);
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
        } catch (Exception $e) {
            db()->rollback();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="loan-settings-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2 text-primary"></i>Loan Terms Configuration</h5>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Select Group -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h6 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>Select Group</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="section" value="loan-settings">
                <div class="col-md-6">
                    <select class="form-select" name="group_id" onchange="this.form.submit()">
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>" <?php echo $selected_group == $group['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['group_name']); ?> (<?php echo $group['group_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <a href="?section=groups" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i>Add New Group
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Loan Settings Form -->
    <?php if ($selected_group): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h6 class="mb-0"><i class="fas fa-sliders-h me-2 text-success"></i>Configure Loan Terms</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="save_loan_settings">
                <input type="hidden" name="group_id" value="<?php echo $selected_group; ?>">
                
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>These settings will apply to all members of <?php echo htmlspecialchars($groups[array_search($selected_group, array_column($groups, 'id'))]['group_name'] ?? 'this group'); ?></strong>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Default Interest Rate (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="default_interest_rate" step="0.5" min="0" max="50" 
                                   value="<?php echo getSetting($settings, 'default_interest_rate', 12); ?>">
                            <span class="input-group-text">% per annum</span>
                        </div>
                        <small class="text-muted">Annual interest rate charged on loans</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Processing Fee (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="processing_fee" step="50" min="0" 
                                   value="<?php echo getSetting($settings, 'processing_fee', 0); ?>">
                        </div>
                        <small class="text-muted">One-time fee charged when loan is disbursed</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Minimum Loan Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="min_loan_amount" step="100" min="100" 
                                   value="<?php echo getSetting($settings, 'min_loan_amount', 1000); ?>">
                        </div>
                        <small class="text-muted">Lowest amount a member can borrow</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Maximum Loan Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="max_loan_amount" step="1000" min="1000" 
                                   value="<?php echo getSetting($settings, 'max_loan_amount', 100000); ?>">
                        </div>
                        <small class="text-muted">Highest amount a member can borrow</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Maximum Loan Ratio</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="max_loan_ratio" step="0.5" min="1" max="10" 
                                   value="<?php echo getSetting($settings, 'max_loan_ratio', 3); ?>">
                            <span class="input-group-text">x contributions</span>
                        </div>
                        <small class="text-muted">Multiple of member's total contributions (e.g., 3x means up to 3 times their contributions)</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Late Penalty Rate (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="late_penalty_rate" step="0.5" min="0" max="20" 
                                   value="<?php echo getSetting($settings, 'late_penalty_rate', 5); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Additional interest charged on overdue payments</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Minimum Loan Duration</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="min_loan_duration" step="1" min="1" max="12" 
                                   value="<?php echo getSetting($settings, 'min_loan_duration', 1); ?>">
                            <span class="input-group-text">months</span>
                        </div>
                        <small class="text-muted">Shortest allowed repayment period</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Maximum Loan Duration</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="max_loan_duration" step="1" min="1" max="36" 
                                   value="<?php echo getSetting($settings, 'max_loan_duration', 12); ?>">
                            <span class="input-group-text">months</span>
                        </div>
                        <small class="text-muted">Longest allowed repayment period</small>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Changes to these settings will apply to all new loan applications. Existing loans will not be affected.
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="fas fa-save me-2"></i>Save Loan Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Current Loan Settings Summary -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h6 class="mb-0"><i class="fas fa-chart-line me-2 text-info"></i>Current Loan Policy Summary</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Interest Rate</small>
                        <h4 class="text-primary mb-0"><?php echo getSetting($settings, 'default_interest_rate', 12); ?>%</h4>
                        <small>per annum</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Processing Fee</small>
                        <h4 class="text-warning mb-0"><?php echo formatCurrency(getSetting($settings, 'processing_fee', 0)); ?></h4>
                        <small>one-time fee</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Loan Range</small>
                        <h4 class="text-success mb-0">
                            <?php echo formatCurrency(getSetting($settings, 'min_loan_amount', 1000)); ?> - 
                            <?php echo formatCurrency(getSetting($settings, 'max_loan_amount', 100000)); ?>
                        </h4>
                        <small>minimum - maximum</small>
                    </div>
                </div>
                <div class="col-md-4 mt-3">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Max Loan Ratio</small>
                        <h4 class="text-info mb-0"><?php echo getSetting($settings, 'max_loan_ratio', 3); ?>x</h4>
                        <small>of member's contributions</small>
                    </div>
                </div>
                <div class="col-md-4 mt-3">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Late Penalty</small>
                        <h4 class="text-danger mb-0"><?php echo getSetting($settings, 'late_penalty_rate', 5); ?>%</h4>
                        <small>on overdue payments</small>
                    </div>
                </div>
                <div class="col-md-4 mt-3">
                    <div class="border rounded p-3 text-center">
                        <small class="text-muted">Loan Period</small>
                        <h4 class="text-secondary mb-0">
                            <?php echo getSetting($settings, 'min_loan_duration', 1); ?> - 
                            <?php echo getSetting($settings, 'max_loan_duration', 12); ?> months
                        </h4>
                        <small>minimum - maximum</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.loan-settings-section .card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.loan-settings-section .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
</style>
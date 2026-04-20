<?php
// This file is included within index.php after permission check

$groupId = $_SESSION['group_id'];

// Get group details
$stmt = db()->prepare("SELECT * FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

// Get all settings
$stmt = db()->prepare("SELECT setting_key, setting_value, setting_type FROM group_settings WHERE group_id = ?");
$stmt->execute([$groupId]);
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row;
}

function getSettingValue($settings, $key, $default = '') {
    return isset($settings[$key]) ? $settings[$key]['setting_value'] : $default;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'general') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/settings/index.php?tab=general');
    }
    
    try {
        db()->beginTransaction();
        
        // Update group basic info
        $stmt = db()->prepare("
            UPDATE groups SET 
                group_name = ?, email = ?, phone = ?, address = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['group_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $groupId
        ]);
        
        $settingStmt = db()->prepare("
            INSERT INTO group_settings (group_id, setting_key, setting_value, setting_type)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        // Loan Settings
        if (isset($_POST['interest_rate_monthly'])) {
            $settingStmt->execute([$groupId, 'interest_rate_monthly', $_POST['interest_rate_monthly'], 'number']);
        }
        if (isset($_POST['min_loan_amount'])) {
            $settingStmt->execute([$groupId, 'min_loan_amount', $_POST['min_loan_amount'], 'number']);
        }
        if (isset($_POST['max_loan_amount'])) {
            $settingStmt->execute([$groupId, 'max_loan_amount', $_POST['max_loan_amount'], 'number']);
        }
        if (isset($_POST['min_loan_duration'])) {
            $settingStmt->execute([$groupId, 'min_loan_duration', $_POST['min_loan_duration'], 'number']);
        }
        if (isset($_POST['max_loan_duration'])) {
            $settingStmt->execute([$groupId, 'max_loan_duration', $_POST['max_loan_duration'], 'number']);
        }
        if (isset($_POST['max_loan_ratio'])) {
            $settingStmt->execute([$groupId, 'max_loan_ratio', $_POST['max_loan_ratio'], 'number']);
        }
        if (isset($_POST['processing_fee'])) {
            $settingStmt->execute([$groupId, 'processing_fee', $_POST['processing_fee'], 'number']);
        }
        if (isset($_POST['late_penalty_rate'])) {
            $settingStmt->execute([$groupId, 'late_penalty_rate', $_POST['late_penalty_rate'], 'number']);
        }
        
        // Contribution settings
        $contribSettings = ['monthly_contribution_amount', 'welfare_contribution_amount', 'registration_fee', 'contribution_due_day'];
        foreach ($contribSettings as $key) {
            if (isset($_POST[$key])) {
                $settingStmt->execute([$groupId, $key, $_POST[$key], 'number']);
            }
        }
        
        // Meeting settings
        $meetingSettings = ['default_meeting_venue', 'meeting_reminder_days', 'quorum_percentage'];
        foreach ($meetingSettings as $key) {
            if (isset($_POST[$key])) {
                $settingStmt->execute([$groupId, $key, $_POST[$key], 'text']);
            }
        }
        if (isset($_POST['require_attendance_confirmation'])) {
            $settingStmt->execute([$groupId, 'require_attendance_confirmation', '1', 'boolean']);
        } else {
            $settingStmt->execute([$groupId, 'require_attendance_confirmation', '0', 'boolean']);
        }
        
        // Notification settings
        $notifSettings = ['email_notifications', 'sms_notifications', 'loan_approval_notifications', 'payment_receipts', 'meeting_reminders'];
        foreach ($notifSettings as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            $settingStmt->execute([$groupId, $key, $value, 'boolean']);
        }
        
        auditLog('update_general_settings', 'groups', $groupId, null, $_POST);
        
        db()->commit();
        setFlash('success', 'General settings updated successfully!');
        redirect('/modules/settings/index.php?tab=general');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}
?>

<div class="settings-section">
    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="general">
        
        <!-- Basic Information -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>Basic Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="group_name" value="<?php echo htmlspecialchars($group['group_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Group Code</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($group['group_code']); ?>" readonly disabled>
                        <small class="text-muted">Unique identifier for your group</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($group['email']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($group['phone']); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Physical Address</label>
                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($group['address']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Loan Terms & Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-hand-holding-usd me-2 text-success"></i>Loan Terms & Settings</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Configure the loan terms that will apply to all members of this group.
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Interest Rate (Monthly) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="interest_rate_monthly" step="0.5" min="0" max="20" 
                                   value="<?php echo getSettingValue($settings, 'interest_rate_monthly', 1); ?>" required>
                            <span class="input-group-text">% per month</span>
                        </div>
                        <small class="text-muted">Monthly interest rate charged on loans</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Processing Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="processing_fee" step="50" min="0" 
                                   value="<?php echo getSettingValue($settings, 'processing_fee', 0); ?>">
                        </div>
                        <small class="text-muted">One-time fee when loan is disbursed</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Late Penalty Rate</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="late_penalty_rate" step="0.5" min="0" max="20" 
                                   value="<?php echo getSettingValue($settings, 'late_penalty_rate', 5); ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">Additional interest on overdue payments</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Maximum Loan Ratio</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="max_loan_ratio" step="0.5" min="1" max="10" 
                                   value="<?php echo getSettingValue($settings, 'max_loan_ratio', 3); ?>">
                            <span class="input-group-text">x contributions</span>
                        </div>
                        <small class="text-muted">Multiple of member's total contributions</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Minimum Loan Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="min_loan_amount" step="100" min="100" 
                                   value="<?php echo getSettingValue($settings, 'min_loan_amount', 1000); ?>" required>
                        </div>
                        <small class="text-muted">Lowest amount a member can borrow</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Maximum Loan Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" class="form-control" name="max_loan_amount" step="1000" min="1000" 
                                   value="<?php echo getSettingValue($settings, 'max_loan_amount', 100000); ?>" required>
                        </div>
                        <small class="text-muted">Highest amount a member can borrow</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Minimum Loan Period <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="min_loan_duration" step="1" min="1" max="12" 
                                   value="<?php echo getSettingValue($settings, 'min_loan_duration', 1); ?>" required>
                            <span class="input-group-text">months</span>
                        </div>
                        <small class="text-muted">Shortest allowed repayment period</small>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Maximum Loan Period <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="max_loan_duration" step="1" min="1" max="36" 
                                   value="<?php echo getSettingValue($settings, 'max_loan_duration', 12); ?>" required>
                            <span class="input-group-text">months</span>
                        </div>
                        <small class="text-muted">Longest allowed repayment period</small>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-2">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Changes will apply to new loan applications only. Existing loans are not affected.
                </div>
            </div>
        </div>
        
        <!-- Contribution Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-coins me-2 text-warning"></i>Contribution Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Monthly Contribution (KES)</label>
                        <input type="number" class="form-control" name="monthly_contribution_amount" step="100" min="0" 
                               value="<?php echo getSettingValue($settings, 'monthly_contribution_amount', 500); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Welfare Contribution (KES)</label>
                        <input type="number" class="form-control" name="welfare_contribution_amount" step="100" min="0" 
                               value="<?php echo getSettingValue($settings, 'welfare_contribution_amount', 200); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Registration Fee (KES)</label>
                        <input type="number" class="form-control" name="registration_fee" step="100" min="0" 
                               value="<?php echo getSettingValue($settings, 'registration_fee', 1000); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Contribution Due Day</label>
                        <select class="form-select" name="contribution_due_day">
                            <?php for ($i = 1; $i <= 28; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo getSettingValue($settings, 'contribution_due_day', 5) == $i ? 'selected' : ''; ?>>
                                    Day <?php echo $i; ?> of each month
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Meeting Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-info"></i>Meeting Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Default Meeting Venue</label>
                        <input type="text" class="form-control" name="default_meeting_venue" 
                               value="<?php echo htmlspecialchars(getSettingValue($settings, 'default_meeting_venue', 'Group Hall')); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Meeting Reminder (Days before)</label>
                        <input type="number" class="form-control" name="meeting_reminder_days" min="1" max="7" 
                               value="<?php echo getSettingValue($settings, 'meeting_reminder_days', 2); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quorum Percentage (%)</label>
                        <input type="number" class="form-control" name="quorum_percentage" min="30" max="100" 
                               value="<?php echo getSettingValue($settings, 'quorum_percentage', 51); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" name="require_attendance_confirmation" id="requireAttendance" 
                                   <?php echo getSettingValue($settings, 'require_attendance_confirmation', '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="requireAttendance">Require Attendance Confirmation</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notification Settings -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-bell me-2 text-danger"></i>Notification Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="email_notifications" id="emailNotif" <?php echo getSettingValue($settings, 'email_notifications', '1') == '1' ? 'checked' : ''; ?>>
                            <label for="emailNotif">Email Notifications</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="sms_notifications" id="smsNotif" <?php echo getSettingValue($settings, 'sms_notifications', '0') == '1' ? 'checked' : ''; ?>>
                            <label for="smsNotif">SMS Notifications</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="loan_approval_notifications" id="loanNotif" <?php echo getSettingValue($settings, 'loan_approval_notifications', '1') == '1' ? 'checked' : ''; ?>>
                            <label for="loanNotif">Loan Approval Notifications</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="payment_receipts" id="paymentNotif" <?php echo getSettingValue($settings, 'payment_receipts', '1') == '1' ? 'checked' : ''; ?>>
                            <label for="paymentNotif">Payment Receipts</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="meeting_reminders" id="meetingNotif" <?php echo getSettingValue($settings, 'meeting_reminders', '1') == '1' ? 'checked' : ''; ?>>
                            <label for="meetingNotif">Meeting Reminders</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="fas fa-save me-2"></i>Save All Settings
            </button>
        </div>
    </form>
</div>
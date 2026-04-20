<?php
$message = '';
$error = '';

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $group_name = trim($_POST['group_name']);
        $group_code = strtoupper(trim($_POST['group_code']));
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $subscription_plan = $_POST['subscription_plan'];
        $admin_first_name = trim($_POST['admin_first_name']);
        $admin_last_name = trim($_POST['admin_last_name']);
        $admin_email = trim($_POST['admin_email']);
        $admin_username = trim($_POST['admin_username']);
        $admin_password = $_POST['admin_password'];
        $auto_generate = isset($_POST['auto_generate']) ? true : false;
        
        if ($auto_generate) {
            $admin_password = generateRandomPassword(10);
        }
        
        if (empty($group_name) || empty($group_code)) {
            $error = 'Group name and code are required';
        } elseif (empty($admin_email) || empty($admin_username)) {
            $error = 'Admin email and username are required';
        } elseif (!$auto_generate && (empty($admin_password) || strlen($admin_password) < PASSWORD_MIN_LENGTH)) {
            $error = 'Admin password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        } else {
            try {
                db()->beginTransaction();
                
                // Check if group code exists
                $stmt = db()->prepare("SELECT id FROM groups WHERE group_code = ?");
                $stmt->execute([$group_code]);
                if ($stmt->fetch()) {
                    throw new Exception('Group code already exists');
                }
                
                // Check if username exists
                $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$admin_username]);
                if ($stmt->fetch()) {
                    throw new Exception('Username already exists');
                }
                
                // Create group
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+30 days'));
                
                $stmt = db()->prepare("
                    INSERT INTO groups (group_name, group_code, email, phone, address, subscription_plan, subscription_status, subscription_start, subscription_end, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
                ");
                $stmt->execute([$group_name, $group_code, $email, $phone, $address, $subscription_plan, $start_date, $end_date]);
                $groupId = db()->lastInsertId();
                
                // Create admin user (chairperson)
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                $stmt = db()->prepare("
                    INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, role, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'chairperson', 'active', NOW())
                ");
                $stmt->execute([$groupId, $admin_username, $admin_email, $password_hash, $admin_first_name, $admin_last_name]);
                $userId = db()->lastInsertId();
                
                // Create member record for admin
                $member_number = 'MBR' . date('Y') . $groupId . '001';
                $stmt = db()->prepare("
                    INSERT INTO members (group_id, user_id, member_number, first_name, last_name, email, phone, join_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')
                ");
                $stmt->execute([$groupId, $userId, $member_number, $admin_first_name, $admin_last_name, $admin_email, $phone]);
                
                db()->commit();
                
                // Send welcome email
                sendEmail($admin_email, "Welcome as Group Administrator - {$group_name}", "Dear {$admin_first_name},\n\nYou have been appointed as the Group Administrator for {$group_name}.\n\nYour Login Credentials:\nGroup Code: {$group_code}\nUsername: {$admin_username}\nPassword: {$admin_password}\n\nLogin URL: " . APP_URL . "/admin/login.php\n\nPlease change your password after login.");
                
                $message = "Group '{$group_name}' created successfully! Credentials sent to {$admin_email}.";
                $_POST = [];
                
            } catch (Exception $e) {
                db()->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="create-group-section">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Create New Welfare Group</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_group">
                
                <h6 class="mb-3">Group Information</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Group Name *</label>
                        <input type="text" class="form-control" name="group_name" value="<?php echo htmlspecialchars($_POST['group_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Group Code *</label>
                        <input type="text" class="form-control text-uppercase" name="group_code" value="<?php echo htmlspecialchars($_POST['group_code'] ?? ''); ?>" required placeholder="e.g., WELF001">
                        <small class="text-muted">Unique identifier for the group</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label>Address</label>
                        <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Subscription Plan *</label>
                        <select class="form-select" name="subscription_plan" required>
                            <option value="monthly">Monthly - KES 29.99 (30 days)</option>
                            <option value="quarterly">Quarterly - KES 79.99 (90 days)</option>
                            <option value="biannual">Biannual - KES 149.99 (180 days)</option>
                            <option value="annual">Annual - KES 299.99 (365 days)</option>
                        </select>
                    </div>
                </div>
                
                <hr>
                
                <h6 class="mb-3">Group Administrator (Chairperson) Account</h6>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>First Name *</label>
                        <input type="text" class="form-control" name="admin_first_name" value="<?php echo htmlspecialchars($_POST['admin_first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Last Name *</label>
                        <input type="text" class="form-control" name="admin_last_name" value="<?php echo htmlspecialchars($_POST['admin_last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email *</label>
                        <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Username *</label>
                        <input type="text" class="form-control" name="admin_username" value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="auto_generate" id="autoGenerate" value="1">
                            <label class="form-check-label" for="autoGenerate">Auto-generate password</label>
                        </div>
                        <div id="manualPasswordField">
                            <label>Password *</label>
                            <input type="text" class="form-control" name="admin_password" placeholder="Enter password or leave blank for auto-generate">
                            <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    The group administrator will receive login credentials via email and can manage all group operations.
                </div>
                
                <div class="text-end mt-4">
                    <a href="?section=groups" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-2">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('autoGenerate')?.addEventListener('change', function() {
    const manualField = document.getElementById('manualPasswordField');
    if (this.checked) {
        manualField.style.display = 'none';
        document.querySelector('input[name="admin_password"]').removeAttribute('required');
    } else {
        manualField.style.display = 'block';
        document.querySelector('input[name="admin_password"]').setAttribute('required', 'required');
    }
});
</script>
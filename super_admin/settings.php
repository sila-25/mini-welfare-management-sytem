<?php
$message = '';

// Get settings
$settings = [];
$stmt = db()->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid token";
    } else {
        $settingStmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $settingStmt->execute(['site_name', $_POST['site_name']]);
        $settingStmt->execute(['site_email', $_POST['site_email']]);
        $settingStmt->execute(['site_phone', $_POST['site_phone']]);
        $settingStmt->execute(['site_address', $_POST['site_address']]);
        $settingStmt->execute(['currency', $_POST['currency']]);
        $settingStmt->execute(['timezone', $_POST['timezone']]);
        $settingStmt->execute(['date_format', $_POST['date_format']]);
        $settingStmt->execute(['maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0']);
        $settingStmt->execute(['allow_registration', isset($_POST['allow_registration']) ? '1' : '0']);
        $message = "Settings saved successfully!";
    }
}
?>

<div class="platform-settings">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">General Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? APP_NAME); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Site Email</label>
                        <input type="email" name="site_email" class="form-control" value="<?php echo htmlspecialchars($settings['site_email'] ?? 'admin@careway.com'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Site Phone</label>
                        <input type="text" name="site_phone" class="form-control" value="<?php echo htmlspecialchars($settings['site_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Currency</label>
                        <select name="currency" class="form-select">
                            <option value="KES" <?php echo ($settings['currency'] ?? 'KES') === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                            <option value="USD" <?php echo ($settings['currency'] ?? 'KES') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? 'KES') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <label>Address</label>
                        <textarea name="site_address" class="form-control" rows="2"><?php echo htmlspecialchars($settings['site_address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Regional Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Timezone</label>
                        <select name="timezone" class="form-select">
                            <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                            <option value="Africa/Kampala">Africa/Kampala</option>
                            <option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Date Format</label>
                        <select name="date_format" class="form-select">
                            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                            <option value="d/m/Y">DD/MM/YYYY</option>
                            <option value="m/d/Y">MM/DD/YYYY</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">System Settings</h5>
            </div>
            <div class="card-body">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="maintenance_mode" id="maintenance" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="maintenance">Maintenance Mode (only admins can access)</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="allow_registration" id="registration" <?php echo ($settings['allow_registration'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="registration">Allow New Group Registrations</label>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary btn-lg">Save All Settings</button>
    </form>
</div>
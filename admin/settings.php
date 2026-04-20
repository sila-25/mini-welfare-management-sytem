<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit();
}

// Check if user is super admin
if ($_SESSION['user_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/admin/login.php?unauthorized=1');
    exit();
}

$page_title = 'System Settings';
$current_page = 'admin';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/admin/settings.php');
    }
    
    try {
        // Update system settings in a settings table
        $settings = [
            'site_name' => $_POST['site_name'],
            'site_email' => $_POST['site_email'],
            'site_phone' => $_POST['site_phone'],
            'site_address' => $_POST['site_address'],
            'currency' => $_POST['currency'],
            'timezone' => $_POST['timezone'],
            'date_format' => $_POST['date_format'],
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'allow_registration' => isset($_POST['allow_registration']) ? 1 : 0,
            'require_email_verification' => isset($_POST['require_email_verification']) ? 1 : 0,
            'smtp_host' => $_POST['smtp_host'],
            'smtp_port' => $_POST['smtp_port'],
            'smtp_user' => $_POST['smtp_user'],
            'smtp_password' => $_POST['smtp_password'],
            'smtp_encryption' => $_POST['smtp_encryption']
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = db()->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        setFlash('success', 'System settings updated successfully');
        
    } catch (Exception $e) {
        setFlash('danger', 'Error updating settings: ' . $e->getMessage());
    }
    
    redirect('/admin/settings.php');
}

// Get current settings
$settings = [];
try {
    $stmt = db()->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

include_once '../templates/header.php';
include_once '../templates/sidebar.php';
include_once '../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-cog me-2"></i>System Settings
                </h1>
                <p class="text-muted mt-2">Configure system-wide settings and preferences</p>
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- General Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-globe me-2 text-primary"></i>General Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site Name</label>
                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? APP_NAME); ?>">
                            <small class="text-muted">The name of the system displayed throughout</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site Email</label>
                            <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($settings['site_email'] ?? 'admin@careway.com'); ?>">
                            <small class="text-muted">System email address for notifications</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Site Phone</label>
                            <input type="tel" class="form-control" name="site_phone" value="<?php echo htmlspecialchars($settings['site_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency">
                                <option value="KES" <?php echo ($settings['currency'] ?? 'KES') === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? 'KES') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? 'KES') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                <option value="GBP" <?php echo ($settings['currency'] ?? 'KES') === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Site Address</label>
                            <textarea class="form-control" name="site_address" rows="2"><?php echo htmlspecialchars($settings['site_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Regional Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-info"></i>Regional Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" name="timezone">
                                <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi</option>
                                <option value="Africa/Kampala" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Kampala' ? 'selected' : ''; ?>>Africa/Kampala</option>
                                <option value="Africa/Dar_es_Salaam" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Dar_es_Salaam' ? 'selected' : ''; ?>>Africa/Dar_es_Salaam</option>
                                <option value="Africa/Addis_Ababa" <?php echo ($settings['timezone'] ?? 'Africa/Nairobi') === 'Africa/Addis_Ababa' ? 'selected' : ''; ?>>Africa/Addis_Ababa</option>
                                <option value="UTC">UTC</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Format</label>
                            <select class="form-select" name="date_format">
                                <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-01-15)</option>
                                <option value="d/m/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (15/01/2024)</option>
                                <option value="m/d/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (01/15/2024)</option>
                                <option value="F d, Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'F d, Y' ? 'selected' : ''; ?>>January 15, 2024</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-server me-2 text-warning"></i>System Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" name="maintenance_mode" id="maintenanceMode" <?php echo ($settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenanceMode">
                                    <i class="fas fa-tools me-2"></i>Maintenance Mode
                                </label>
                                <div class="form-text">When enabled, only admins can access the system</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" name="allow_registration" id="allowRegistration" <?php echo ($settings['allow_registration'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allowRegistration">
                                    <i class="fas fa-user-plus me-2"></i>Allow New Registrations
                                </label>
                                <div class="form-text">Allow new groups to register</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" name="require_email_verification" id="requireEmailVerification" <?php echo ($settings['require_email_verification'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requireEmailVerification">
                                    <i class="fas fa-envelope me-2"></i>Require Email Verification
                                </label>
                                <div class="form-text">New users must verify their email address</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Settings (SMTP) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2 text-success"></i>Email Settings (SMTP)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                            <small class="text-muted">Your email server hostname</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="text" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                            <small class="text-muted">Common ports: 587 (TLS), 465 (SSL)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                            <small class="text-muted">Your email address</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                            <small class="text-muted">Your email password or app password</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SMTP Encryption</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>For Gmail:</strong> Use App Password instead of your regular password. Enable 2FA and generate an App Password from your Google Account settings.
                    </div>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
                <button type="button" class="btn btn-secondary btn-lg px-4 ms-2" onclick="window.location.reload()">
                    <i class="fas fa-undo me-2"></i>Reset
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>
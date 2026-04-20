<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';
require_once '../includes/auth.php';

Middleware::requireLogin();

$page_title = 'My Profile';
$current_page = 'profile';
$active_tab = $_GET['tab'] ?? 'profile';

$userId = $_SESSION['user_id'];
$groupId = $_SESSION['group_id'];

// Get user details
$stmt = db()->prepare("
    SELECT u.*, m.id as member_id, m.member_number, m.join_date, m.total_contributions,
           g.group_name, g.group_code
    FROM users u
    LEFT JOIN members m ON u.id = m.user_id AND m.group_id = u.group_id
    LEFT JOIN groups g ON u.group_id = g.id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found');
    redirect('/dashboard/home.php');
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/dashboard/profile.php');
    }
    
    try {
        if ($_POST['action'] === 'update_profile') {
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            
            // Check if email already exists for other users
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Email already in use by another account');
            }
            
            // Update user
            $stmt = db()->prepare("
                UPDATE users SET 
                    first_name = ?, last_name = ?, phone = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $phone, $email, $userId]);
            
            // Update session
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            
            // Update member if exists
            if ($user['member_id']) {
                $stmt = db()->prepare("
                    UPDATE members SET 
                        first_name = ?, last_name = ?, phone = ?, email = ?
                    WHERE id = ?
                ");
                $stmt->execute([$firstName, $lastName, $phone, $email, $user['member_id']]);
            }
            
            setFlash('success', 'Profile updated successfully');
            
        } elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            $result = Auth::changePassword($userId, $currentPassword, $newPassword);
            
            if ($result['success']) {
                setFlash('success', $result['message']);
            } else {
                throw new Exception($result['message']);
            }
            
        } elseif ($_POST['action'] === 'update_notifications') {
            $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
            $loanAlerts = isset($_POST['loan_alerts']) ? 1 : 0;
            $meetingReminders = isset($_POST['meeting_reminders']) ? 1 : 0;
            $paymentReceipts = isset($_POST['payment_receipts']) ? 1 : 0;
            
            $stmt = db()->prepare("
                INSERT INTO user_settings (user_id, email_notifications, loan_alerts, meeting_reminders, payment_receipts)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                email_notifications = VALUES(email_notifications),
                loan_alerts = VALUES(loan_alerts),
                meeting_reminders = VALUES(meeting_reminders),
                payment_receipts = VALUES(payment_receipts)
            ");
            $stmt->execute([$userId, $emailNotifications, $loanAlerts, $meetingReminders, $paymentReceipts]);
            
            setFlash('success', 'Notification preferences updated');
        }
        
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    
    redirect("/dashboard/profile.php?tab={$active_tab}");
}

// Get user settings
$stmt = db()->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$settings = $stmt->fetch();

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-user-circle me-2"></i>My Profile
                    </h1>
                    <p class="text-muted mt-2">Manage your account settings and preferences</p>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" href="?tab=profile">
                                <i class="fas fa-user me-2"></i>Profile Information
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'security' ? 'active' : ''; ?>" href="?tab=security">
                                <i class="fas fa-lock me-2"></i>Security
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" href="?tab=notifications">
                                <i class="fas fa-bell me-2"></i>Notifications
                            </a>
                        </li>
                        <?php if ($user['member_id']): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $active_tab === 'member' ? 'active' : ''; ?>" href="?tab=member">
                                <i class="fas fa-id-card me-2"></i>Member Details
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($active_tab === 'profile'): ?>
                        <!-- Profile Information -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" readonly disabled>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Group</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['group_name']); ?> (<?php echo $user['group_code']; ?>)" readonly disabled>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                        
                    <?php elseif ($active_tab === 'security'): ?>
                        <!-- Change Password -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-8 mx-auto">
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        For security, please use a strong password with at least <?php echo PASSWORD_MIN_LENGTH; ?> characters.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" id="new_password" required>
                                        <div class="password-strength mt-1"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                        <div class="invalid-feedback">Passwords do not match</div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                    <?php elseif ($active_tab === 'notifications'): ?>
                        <!-- Notification Preferences -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="row">
                                <div class="col-md-8 mx-auto">
                                    <div class="alert alert-info mb-4">
                                        <i class="fas fa-bell me-2"></i>
                                        Choose how you want to receive notifications.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="email_notifications" id="emailNotifications" 
                                                   <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="emailNotifications">
                                                <i class="fas fa-envelope me-2"></i>Email Notifications
                                            </label>
                                        </div>
                                        <small class="text-muted">Receive important updates via email</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="loan_alerts" id="loanAlerts" 
                                                   <?php echo ($settings['loan_alerts'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="loanAlerts">
                                                <i class="fas fa-hand-holding-usd me-2"></i>Loan Alerts
                                            </label>
                                        </div>
                                        <small class="text-muted">Get notified about loan application status</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="meeting_reminders" id="meetingReminders" 
                                                   <?php echo ($settings['meeting_reminders'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="meetingReminders">
                                                <i class="fas fa-calendar-alt me-2"></i>Meeting Reminders
                                            </label>
                                        </div>
                                        <small class="text-muted">Receive reminders before scheduled meetings</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" class="form-check-input" name="payment_receipts" id="paymentReceipts" 
                                                   <?php echo ($settings['payment_receipts'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="paymentReceipts">
                                                <i class="fas fa-receipt me-2"></i>Payment Receipts
                                            </label>
                                        </div>
                                        <small class="text-muted">Get receipts for contributions and loan payments</small>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Preferences
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                    <?php elseif ($active_tab === 'member' && $user['member_id']): ?>
                        <!-- Member Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Member Number:</strong></td>
                                        <td><?php echo htmlspecialchars($user['member_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Join Date:</strong></td>
                                        <td><?php echo formatDate($user['join_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Contributions:</strong></td>
                                        <td class="text-success fw-bold"><?php echo formatCurrency($user['total_contributions'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge bg-success">Active Member</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Member Benefits:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Access to group loans</li>
                                        <li>Voting rights in elections</li>
                                        <li>Attendance at meetings</li>
                                        <li>Investment opportunities</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="../modules/members/view.php?id=<?php echo $user['member_id']; ?>" class="btn btn-primary">
                                <i class="fas fa-id-card me-2"></i>View Full Member Profile
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength checker
document.getElementById('new_password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = this.parentElement.querySelector('.password-strength');
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    let message = '';
    let className = '';
    
    if (strength <= 2) {
        message = 'Weak password';
        className = 'text-danger';
    } else if (strength <= 4) {
        message = 'Medium password';
        className = 'text-warning';
    } else {
        message = 'Strong password';
        className = 'text-success';
    }
    
    strengthDiv.innerHTML = '<i class="fas fa-shield-alt me-1"></i>' + message;
    strengthDiv.className = 'password-strength ' + className;
});

// Confirm password validation
const confirmPassword = document.getElementById('confirm_password');
if (confirmPassword) {
    confirmPassword.addEventListener('input', function() {
        const password = document.getElementById('new_password').value;
        if (this.value !== password) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>
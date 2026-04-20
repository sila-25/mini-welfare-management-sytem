<?php
$message = '';
$error = '';

// Handle adding new group admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_group_admin') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $group_id = (int)$_POST['group_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $auto_generate = isset($_POST['auto_generate']) ? true : false;
        
        if ($auto_generate) {
            $password = generateRandomPassword(10);
        }
        
        if (empty($group_id) || empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
            $error = 'Please fill all required fields';
        } elseif (!$auto_generate && (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH)) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        } else {
            try {
                db()->beginTransaction();
                
                // Check if username exists
                $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    throw new Exception('Username already exists');
                }
                
                // Check if email exists in this group
                $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND group_id = ?");
                $stmt->execute([$email, $group_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists in this group');
                }
                
                // Get group details
                $stmt = db()->prepare("SELECT group_name, group_code FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                $group = $stmt->fetch();
                if (!$group) {
                    throw new Exception('Group not found');
                }
                
                // Create user (chairperson)
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare("
                    INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, phone, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'chairperson', 'active')
                ");
                $stmt->execute([$group_id, $username, $email, $hash, $first_name, $last_name, $phone]);
                $userId = db()->lastInsertId();
                
                // Create member record
                $member_number = 'MBR' . date('Y') . $group_id . str_pad($userId, 3, '0', STR_PAD_LEFT);
                $stmt = db()->prepare("
                    INSERT INTO members (group_id, user_id, member_number, first_name, last_name, email, phone, join_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')
                ");
                $stmt->execute([$group_id, $userId, $member_number, $first_name, $last_name, $email, $phone]);
                
                db()->commit();
                
                // Send welcome email
                sendEmail($email, "Welcome as Group Administrator - {$group['group_name']}", 
                    "Dear {$first_name},\n\nYou have been appointed as the Group Administrator (Chairperson) for {$group['group_name']}.\n\nYour Login Credentials:\nGroup Code: {$group['group_code']}\nUsername: {$username}\nPassword: {$password}\n\nLogin URL: " . APP_URL . "/admin/login.php\n\nPlease change your password after login.");
                
                $message = "Group admin added successfully! Credentials sent to {$email}.";
                
            } catch (Exception $e) {
                db()->rollback();
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Handle suspend
if (isset($_GET['suspend']) && isset($_GET['id'])) {
    $stmt = db()->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'chairperson'");
    $stmt->execute([(int)$_GET['id']]);
    $message = "Group admin suspended!";
}
// Handle activate
if (isset($_GET['activate']) && isset($_GET['id'])) {
    $stmt = db()->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'chairperson'");
    $stmt->execute([(int)$_GET['id']]);
    $message = "Group admin activated!";
}
// Handle reset password
if (isset($_GET['reset']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $new_pass = generateRandomPassword(10);
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $id]);
    
    $stmt = db()->prepare("SELECT u.email, u.first_name, u.last_name, g.group_name FROM users u JOIN groups g ON u.group_id = g.id WHERE u.id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    if ($admin) {
        sendEmail($admin['email'], "Password Reset - {$admin['group_name']}", 
            "Dear {$admin['first_name']},\n\nYour password has been reset.\n\nNew Password: {$new_pass}\n\nLogin URL: " . APP_URL . "/admin/login.php\n\nPlease change your password after login.");
    }
    $message = "Password reset and emailed to admin!";
}

// Get all groups for dropdown
$stmt = db()->prepare("SELECT id, group_name, group_code FROM groups ORDER BY group_name");
$stmt->execute();
$allGroups = $stmt->fetchAll();

// Get all group admins
$search = $_GET['search'] ?? '';
$query = "SELECT u.*, g.group_name, g.group_code FROM users u JOIN groups g ON u.group_id = g.id WHERE u.role = 'chairperson'";
if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR g.group_name LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
    $stmt = db()->prepare($query);
    $stmt->execute($params);
} else {
    $stmt = db()->prepare($query);
    $stmt->execute();
}
$admins = $stmt->fetchAll();
?>

<div class="group-admins-management">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Search -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="section" value="group-admins">
                        <div class="col-md-10">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, username, email, or group..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Group Admins List -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Group Administrators (Chairpersons)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Group</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admins)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No group admins found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($admin['group_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo $admin['group_code']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($admin['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $admin['last_login'] ? formatDateTime($admin['last_login']) : 'Never'; ?></td>
                                        <td>
                                            <button onclick="resetPass(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['first_name']); ?>')" class="btn btn-sm btn-warning" title="Reset Password">Reset</button>
                                            <?php if ($admin['status'] === 'active'): ?>
                                                <button onclick="suspendAdmin(<?php echo $admin['id']; ?>)" class="btn btn-sm btn-danger" title="Suspend">Suspend</button>
                                            <?php else: ?>
                                                <button onclick="activateAdmin(<?php echo $admin['id']; ?>)" class="btn btn-sm btn-success" title="Activate">Activate</button>
                                            <?php endif; ?>
                                            <a href="?section=groups&edit=1&id=<?php echo $admin['group_id']; ?>" class="btn btn-sm btn-info" title="Edit Group">Edit Group</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Add Group Admin Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add Group Administrator</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_group_admin">
                        
                        <div class="mb-3">
                            <label>Select Group *</label>
                            <select name="group_id" class="form-select" required>
                                <option value="">-- Select Group --</option>
                                <?php foreach ($allGroups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo htmlspecialchars($group['group_name']); ?> (<?php echo $group['group_code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>First Name *</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <label>Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="mb-2">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="mb-2">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        
                        <div class="mb-2">
                            <div class="form-check mb-2">
                                <input type="checkbox" class="form-check-input" name="auto_generate" id="autoGenerate" value="1">
                                <label class="form-check-label" for="autoGenerate">Auto-generate password</label>
                            </div>
                            <div id="manualPasswordDiv">
                                <label>Password</label>
                                <input type="text" name="password" class="form-control" placeholder="Enter password or leave empty for auto-generate">
                                <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 mt-3">
                            <i class="fas fa-user-plus me-2"></i>Add Group Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function resetPass(id, name) {
    if(confirm(`Reset password for ${name}? A new password will be emailed.`)) {
        window.location.href = `?section=group-admins&reset=yes&id=${id}`;
    }
}
function suspendAdmin(id) {
    if(confirm('Suspend this admin?')) window.location.href = `?section=group-admins&suspend=yes&id=${id}`;
}
function activateAdmin(id) {
    if(confirm('Activate this admin?')) window.location.href = `?section=group-admins&activate=yes&id=${id}`;
}

document.getElementById('autoGenerate')?.addEventListener('change', function() {
    const manualDiv = document.getElementById('manualPasswordDiv');
    if (this.checked) {
        manualDiv.style.display = 'none';
        document.querySelector('input[name="password"]').removeAttribute('required');
    } else {
        manualDiv.style.display = 'block';
        document.querySelector('input[name="password"]').setAttribute('required', 'required');
    }
});
</script>
<?php
$message = '';
$error = '';

// Add new system admin (super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_system_admin') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $first = $_POST['first_name'];
        $last = $_POST['last_name'];
        $user = $_POST['username'];
        $email = $_POST['email'];
        $pass = $_POST['password'];
        
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = db()->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, 'super_admin', 'active')");
            $stmt->execute([$user, $email, $hash, $first, $last]);
            $message = "System admin added successfully!";
            sendEmail($email, "Welcome to Careway System Administration", "Hello {$first},\n\nYou have been added as a System Administrator.\n\nUsername: {$user}\nPassword: {$pass}\n\nLogin: " . APP_URL . "/super_admin/login.php");
        }
    }
}

// Delete system admin
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id != $_SESSION['user_id'] && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        $stmt = db()->prepare("DELETE FROM users WHERE id = ? AND role = 'super_admin'");
        $stmt->execute([$id]);
        $message = "System admin deleted!";
    }
}

// Get all system admins (super admins only)
$stmt = db()->prepare("SELECT * FROM users WHERE role = 'super_admin' ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll();
?>

<div class="system-admins-management">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">System Administrators (Super Admins)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
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
                                        <td colspan="7" class="text-center py-4">No system admins found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                        <td><?php echo $admin['last_login'] ? formatDateTime($admin['last_login']) : 'Never'; ?></td>
                                        <td>
                                            <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo addslashes($admin['first_name']); ?>')" class="btn btn-sm btn-danger">Delete</button>
                                            <?php else: ?>
                                                <span class="text-muted">Current</span>
                                            <?php endif; ?>
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Add System Admin</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_system_admin">
                        <div class="mb-2"><input type="text" name="first_name" class="form-control" placeholder="First Name" required></div>
                        <div class="mb-2"><input type="text" name="last_name" class="form-control" placeholder="Last Name" required></div>
                        <div class="mb-2"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                        <div class="mb-2"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                        <div class="mb-2"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                        <button type="submit" class="btn btn-primary w-100">Add System Admin</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteAdmin(id, name) {
    if(confirm(`Delete ${name}? This cannot be undone.`)) {
        window.location.href = `?section=system-admins&delete=yes&id=${id}&confirm=yes`;
    }
}
</script>
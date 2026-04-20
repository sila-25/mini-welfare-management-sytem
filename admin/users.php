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

$page_title = 'User Management';
$current_page = 'admin';

// Handle add/edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/admin/users.php');
    }
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $role = $_POST['role'];
            $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            
            // Check if username exists
            $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('Username already exists');
            }
            
            // Check if email exists
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            $password = generateRandomPassword(10);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = db()->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, role, group_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $role, $group_id]);
            
            $userId = db()->lastInsertId();
            
            // Send welcome email
            $subject = "Welcome to " . APP_NAME . " Admin Panel";
            $body = "
                <h3>Welcome to " . APP_NAME . "</h3>
                <p>Your admin account has been created.</p>
                <p><strong>Username:</strong> {$username}</p>
                <p><strong>Password:</strong> {$password}</p>
                <p>Please login and change your password immediately.</p>
                <p><a href='" . APP_URL . "/pages/login.php'>Login Here</a></p>
            ";
            sendEmail($email, $subject, $body);
            
            setFlash('success', 'User created successfully. Credentials sent via email.');
            
        } elseif ($action === 'edit') {
            $userId = (int)$_POST['user_id'];
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $role = $_POST['role'];
            $status = $_POST['status'];
            $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
            
            $stmt = db()->prepare("
                UPDATE users SET 
                    first_name = ?, last_name = ?, role = ?, status = ?, group_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $role, $status, $group_id, $userId]);
            
            setFlash('success', 'User updated successfully');
        }
        
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    
    redirect('/admin/users.php');
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $confirm = $_GET['confirm'] ?? '';
    
    if ($userId == $_SESSION['user_id']) {
        setFlash('danger', 'Cannot delete your own account');
        redirect('/admin/users.php');
    }
    
    if ($confirm === 'yes') {
        $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        setFlash('success', 'User deleted successfully');
        redirect('/admin/users.php');
    }
}

// Get users with filters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "SELECT u.*, g.group_name 
          FROM users u
          LEFT JOIN groups g ON u.group_id = g.id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role) {
    $query .= " AND u.role = ?";
    $params[] = $role;
}
if ($status) {
    $query .= " AND u.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get groups for dropdown
$stmt = db()->prepare("SELECT id, group_name FROM groups ORDER BY group_name");
$stmt->execute();
$groups = $stmt->fetchAll();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users WHERE 1=1";
$countParams = [];
if ($search) {
    $countQuery .= " AND (username LIKE ? OR email LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}
if ($role) {
    $countQuery .= " AND role = ?";
    $countParams[] = $role;
}
if ($status) {
    $countQuery .= " AND status = ?";
    $countParams[] = $status;
}

$stmt = db()->prepare($countQuery);
$stmt->execute($countParams);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-users me-2"></i>User Management</h1>
            <p class="text-muted mt-2">Manage system administrators and users</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, username, email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="super_admin" <?php echo $role === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="chairperson" <?php echo $role === 'chairperson' ? 'selected' : ''; ?>>Chairperson</option>
                        <option value="treasurer" <?php echo $role === 'treasurer' ? 'selected' : ''; ?>>Treasurer</option>
                        <option value="secretary" <?php echo $role === 'secretary' ? 'selected' : ''; ?>>Secretary</option>
                        <option value="member" <?php echo $role === 'member' ? 'selected' : ''; ?>>Member</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Group</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                    <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'super_admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['group_name']): ?>
                                        <?php echo htmlspecialchars($user['group_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">System Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-info" onclick="resetPassword(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role; ?>&status=<?php echo $status; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="super_admin">Super Admin</option>
                                <option value="chairperson">Chairperson</option>
                                <option value="treasurer">Treasurer</option>
                                <option value="secretary">Secretary</option>
                                <option value="vice_secretary">Vice Secretary</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group (Optional)</label>
                            <select class="form-select" name="group_id">
                                <option value="">No Group (System Admin)</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Login credentials will be sent to the user's email address.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role">
                                <option value="super_admin">Super Admin</option>
                                <option value="chairperson">Chairperson</option>
                                <option value="treasurer">Treasurer</option>
                                <option value="secretary">Secretary</option>
                                <option value="vice_secretary">Vice Secretary</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Group</label>
                            <select class="form-select" name="group_id" id="edit_group_id">
                                <option value="">No Group (System Admin)</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['group_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
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

<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_group_id').value = user.group_id || '';
    $('#editUserModal').modal('show');
}

function deleteUser(id, name) {
    Swal.fire({
        title: 'Delete User?',
        html: `Are you sure you want to delete <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `users.php?delete=yes&id=${id}&confirm=yes`;
        }
    });
}

function resetPassword(id) {
    Swal.fire({
        title: 'Reset Password?',
        text: 'A new password will be generated and sent to the user\'s email.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, reset'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `users.php?reset=yes&id=${id}`;
        }
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>
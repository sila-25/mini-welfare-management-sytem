<?php
// This file is included within super admin dashboard

$message = '';
$error = '';

// Get all system users (super admins)
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';

$query = "SELECT * FROM users WHERE role = 'super_admin'";
$params = [];

if ($search) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";
$stmt = db()->prepare($query);
$stmt->execute($params);
$systemUsers = $stmt->fetchAll();
?>

<div class="users-management">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h5 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>System Administrators</h5>
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
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($systemUsers)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No system users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($systemUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
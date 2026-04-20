<?php
$message = '';
$error = '';
$edit_group = null;
$edit_mode = false;

// Handle edit - load data
if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_mode = true;
    $stmt = db()->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $edit_group = $stmt->fetch();
}

// Handle update group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_group') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        try {
            $groupId = (int)$_POST['group_id'];
            $stmt = db()->prepare("
                UPDATE groups SET 
                    group_name = ?, email = ?, phone = ?, address = ?,
                    subscription_plan = ?, subscription_status = ?, subscription_end = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['group_name'], $_POST['email'], $_POST['phone'], $_POST['address'],
                $_POST['subscription_plan'], $_POST['subscription_status'], $_POST['subscription_end'], $groupId
            ]);
            
            // Reset admin password if requested
            if (isset($_POST['reset_password']) && $_POST['reset_password'] == 1) {
                $new_password = !empty($_POST['new_password']) ? $_POST['new_password'] : generateRandomPassword(10);
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE group_id = ? AND role = 'chairperson'");
                $stmt->execute([$new_hash, $groupId]);
                
                $stmt = db()->prepare("SELECT email, username, first_name, last_name FROM users WHERE group_id = ? AND role = 'chairperson'");
                $stmt->execute([$groupId]);
                $admin = $stmt->fetch();
                if ($admin) {
                    sendEmail($admin['email'], "Password Reset - " . $_POST['group_name'], "Dear {$admin['first_name']},\n\nYour password has been reset.\n\nNew Password: {$new_password}\n\nLogin URL: " . APP_URL . "/admin/login.php\n\nPlease change your password after login.");
                }
                $message = "Group updated and new password sent to admin!";
            } else {
                $message = "Group updated successfully!";
            }
            
            echo "<script>window.location.href='?section=groups&success=1';</script>";
            exit();
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle delete group
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        try {
            db()->beginTransaction();
            $stmt = db()->prepare("DELETE FROM users WHERE group_id = ?");
            $stmt->execute([$id]);
            $stmt = db()->prepare("DELETE FROM members WHERE group_id = ?");
            $stmt->execute([$id]);
            $stmt = db()->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$id]);
            db()->commit();
            $message = "Group deleted successfully!";
        } catch (Exception $e) {
            db()->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get all groups
$search = $_GET['search'] ?? '';
$query = "SELECT g.*, COUNT(DISTINCT u.id) as user_count FROM groups g LEFT JOIN users u ON g.id = u.group_id WHERE 1=1";
$params = [];
if ($search) {
    $query .= " AND (g.group_name LIKE ? OR g.group_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " GROUP BY g.id ORDER BY g.created_at DESC";
$stmt = db()->prepare($query);
$stmt->execute($params);
$groups = $stmt->fetchAll();
?>

<div class="groups-management">
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="section" value="groups">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="search" placeholder="Search by group name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
                <div class="col-md-3">
                    <a href="?section=create-group" class="btn btn-success w-100">Create New Group</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Groups</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Group Name</th>
                            <th>Code</th>
                            <th>Plan</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($groups)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">No groups found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?php echo $group['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($group['group_name']); ?></strong></td>
                                <td><code><?php echo $group['group_code']; ?></code></td>
                                <td><?php echo ucfirst($group['subscription_plan']); ?></td>
                                <td><?php echo $group['user_count']; ?> users</td>
                                <td>
                                    <span class="badge bg-<?php echo $group['subscription_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($group['subscription_status']); ?>
                                    </span>
                                 </td>
                                <td><?php echo $group['subscription_end'] ? formatDate($group['subscription_end']) : 'N/A'; ?></td>
                                <td>
                                    <a href="?section=groups&edit=1&id=<?php echo $group['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <button onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo addslashes($group['group_name']); ?>')" class="btn btn-sm btn-danger">Delete</button>
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

<!-- Edit Group Modal -->
<?php if ($edit_mode && $edit_group): ?>
<div class="modal fade show" id="editModal" tabindex="-1" style="display:block; background:rgba(0,0,0,0.5)">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Group: <?php echo htmlspecialchars($edit_group['group_name']); ?></h5>
                <a href="?section=groups" class="btn-close btn-close-white"></a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_group">
                    <input type="hidden" name="group_id" value="<?php echo $edit_group['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Group Name</label>
                            <input type="text" name="group_name" class="form-control" value="<?php echo htmlspecialchars($edit_group['group_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Group Code</label>
                            <input type="text" class="form-control" value="<?php echo $edit_group['group_code']; ?>" disabled>
                            <small class="text-muted">Code cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_group['email']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($edit_group['phone']); ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label>Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($edit_group['address']); ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Subscription Plan</label>
                            <select name="subscription_plan" class="form-select">
                                <option value="monthly" <?php echo $edit_group['subscription_plan'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="quarterly" <?php echo $edit_group['subscription_plan'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                <option value="biannual" <?php echo $edit_group['subscription_plan'] === 'biannual' ? 'selected' : ''; ?>>Biannual</option>
                                <option value="annual" <?php echo $edit_group['subscription_plan'] === 'annual' ? 'selected' : ''; ?>>Annual</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Status</label>
                            <select name="subscription_status" class="form-select">
                                <option value="active" <?php echo $edit_group['subscription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $edit_group['subscription_status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="suspended" <?php echo $edit_group['subscription_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Subscription End Date</label>
                            <input type="date" name="subscription_end" class="form-control" value="<?php echo $edit_group['subscription_end']; ?>">
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Group Admin (Chairperson) Password</h6>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="reset_password" id="resetPassword" value="1">
                        <label class="form-check-label" for="resetPassword">Reset Admin Password</label>
                    </div>
                    <div id="newPasswordDiv" style="display:none;">
                        <label>New Password (leave empty to auto-generate)</label>
                        <input type="text" name="new_password" class="form-control" placeholder="Auto-generate if left empty">
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="?section=groups" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('resetPassword')?.addEventListener('change', function() {
    document.getElementById('newPasswordDiv').style.display = this.checked ? 'block' : 'none';
});
</script>
<?php endif; ?>

<script>
function deleteGroup(id, name) {
    if(confirm(`Are you sure you want to delete "${name}"? This will delete all group data and cannot be undone.`)) {
        window.location.href = `?section=groups&delete=yes&id=${id}&confirm=yes`;
    }
}
</script>
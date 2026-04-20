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

$page_title = 'Manage Groups';
$current_page = 'admin';

// Handle add/edit group
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/admin/groups.php');
    }
    
    try {
        $action = $_POST['action'];
        
        if ($action === 'add') {
            $groupName = trim($_POST['group_name']);
            $groupCode = strtoupper(trim($_POST['group_code']));
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $subscriptionPlan = $_POST['subscription_plan'];
            $maxMembers = (int)$_POST['max_members'];
            
            // Check if group code exists
            $stmt = db()->prepare("SELECT id FROM groups WHERE group_code = ?");
            $stmt->execute([$groupCode]);
            if ($stmt->fetch()) {
                throw new Exception('Group code already exists');
            }
            
            $stmt = db()->prepare("
                INSERT INTO groups (group_name, group_code, email, phone, address, subscription_plan, max_members)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$groupName, $groupCode, $email, $phone, $address, $subscriptionPlan, $maxMembers]);
            
            setFlash('success', 'Group created successfully');
            
        } elseif ($action === 'edit') {
            $groupId = (int)$_POST['group_id'];
            $groupName = trim($_POST['group_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $subscriptionPlan = $_POST['subscription_plan'];
            $subscriptionStatus = $_POST['subscription_status'];
            $maxMembers = (int)$_POST['max_members'];
            
            $stmt = db()->prepare("
                UPDATE groups SET 
                    group_name = ?, email = ?, phone = ?, address = ?,
                    subscription_plan = ?, subscription_status = ?, max_members = ?
                WHERE id = ?
            ");
            $stmt->execute([$groupName, $email, $phone, $address, $subscriptionPlan, $subscriptionStatus, $maxMembers, $groupId]);
            
            setFlash('success', 'Group updated successfully');
        }
        
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
    }
    
    redirect('/admin/groups.php');
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $groupId = (int)$_GET['id'];
    $confirm = $_GET['confirm'] ?? '';
    
    if ($confirm === 'yes') {
        try {
            // First check if group has any users
            $stmt = db()->prepare("SELECT COUNT(*) as count FROM users WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $userCount = $stmt->fetch()['count'];
            
            if ($userCount > 0) {
                throw new Exception('Cannot delete group with existing users. Reassign or delete users first.');
            }
            
            $stmt = db()->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$groupId]);
            setFlash('success', 'Group deleted successfully');
        } catch (Exception $e) {
            setFlash('danger', $e->getMessage());
        }
        redirect('/admin/groups.php');
    }
}

// Get groups with filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$plan = $_GET['plan'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "SELECT g.*, 
          COUNT(DISTINCT u.id) as user_count,
          COUNT(DISTINCT m.id) as member_count
          FROM groups g
          LEFT JOIN users u ON g.id = u.group_id
          LEFT JOIN members m ON g.id = m.group_id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (g.group_name LIKE ? OR g.group_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $query .= " AND g.subscription_status = ?";
    $params[] = $status;
}
if ($plan) {
    $query .= " AND g.subscription_plan = ?";
    $params[] = $plan;
}

$query .= " GROUP BY g.id ORDER BY g.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($query);
$stmt->execute($params);
$groups = $stmt->fetchAll();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM groups WHERE 1=1";
$countParams = [];
if ($search) {
    $countQuery .= " AND (group_name LIKE ? OR group_code LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}
if ($status) {
    $countQuery .= " AND subscription_status = ?";
    $countParams[] = $status;
}
if ($plan) {
    $countQuery .= " AND subscription_plan = ?";
    $countParams[] = $plan;
}

$stmt = db()->prepare($countQuery);
$stmt->execute($countParams);
$totalGroups = $stmt->fetch()['total'];
$totalPages = ceil($totalGroups / $limit);

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-building me-2"></i>Manage Groups</h1>
            <p class="text-muted mt-2">View and manage all registered groups</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
            <i class="fas fa-plus me-2"></i>Add New Group
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="plan">
                        <option value="">All Plans</option>
                        <option value="monthly" <?php echo $plan === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $plan === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="biannual" <?php echo $plan === 'biannual' ? 'selected' : ''; ?>>Biannual</option>
                        <option value="annual" <?php echo $plan === 'annual' ? 'selected' : ''; ?>>Annual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Groups Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Group Name</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th>Plan</th>
                            <th>Users/Members</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><?php echo $group['id']; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($group['group_name']); ?></td>
                                <td><code><?php echo $group['group_code']; ?></code></td>
                                <td>
                                    <?php if ($group['email']): ?>
                                        <i class="fas fa-envelope text-muted"></i> <?php echo htmlspecialchars($group['email']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($group['phone']): ?>
                                        <i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($group['phone']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($group['subscription_plan']); ?></span>
                                </td>
                                <td>
                                    <?php echo number_format($group['user_count']); ?> users<br>
                                    <?php echo number_format($group['member_count']); ?> members
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $group['subscription_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($group['subscription_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($group['subscription_end']): ?>
                                        <?php echo formatDate($group['subscription_end']); ?>
                                        <?php if (strtotime($group['subscription_end']) < time()): ?>
                                            <br><span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editGroup(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['group_name']); ?>')">
                                        <i class="fas fa-trash"></i>
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&plan=<?php echo $plan; ?>">
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

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group Name *</label>
                            <input type="text" class="form-control" name="group_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group Code *</label>
                            <input type="text" class="form-control" name="group_code" required placeholder="e.g., WELF001">
                            <small class="text-muted">Unique identifier for the group</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subscription Plan</label>
                            <select class="form-select" name="subscription_plan">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="biannual">Biannual</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Members</label>
                            <input type="number" class="form-control" name="max_members" value="100">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="group_id" id="edit_group_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group Name *</label>
                            <input type="text" class="form-control" name="group_name" id="edit_group_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Group Code</label>
                            <input type="text" class="form-control" id="edit_group_code" disabled>
                            <small class="text-muted">Code cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Subscription Plan</label>
                            <select class="form-select" name="subscription_plan" id="edit_plan">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="biannual">Biannual</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="subscription_status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Members</label>
                            <input type="number" class="form-control" name="max_members" id="edit_max_members">
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
function editGroup(group) {
    document.getElementById('edit_group_id').value = group.id;
    document.getElementById('edit_group_name').value = group.group_name;
    document.getElementById('edit_group_code').value = group.group_code;
    document.getElementById('edit_email').value = group.email || '';
    document.getElementById('edit_phone').value = group.phone || '';
    document.getElementById('edit_address').value = group.address || '';
    document.getElementById('edit_plan').value = group.subscription_plan;
    document.getElementById('edit_status').value = group.subscription_status;
    document.getElementById('edit_max_members').value = group.max_members;
    $('#editGroupModal').modal('show');
}

function deleteGroup(id, name) {
    Swal.fire({
        title: 'Delete Group?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>This will also delete all associated data.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `groups.php?delete=yes&id=${id}&confirm=yes`;
        }
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>
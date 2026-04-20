<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Only chairperson and super admin can access
if ($_SESSION['user_role'] !== 'chairperson' && $_SESSION['user_role'] !== 'super_admin') {
    setFlash('danger', 'You do not have permission to access settings');
    redirect('/dashboard/home.php');
}

Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];

// Get all positions
$stmt = db()->prepare("SELECT * FROM group_positions WHERE group_id = ? ORDER BY hierarchy_level, position_name");
$stmt->execute([$groupId]);
$positions = $stmt->fetchAll();

// Handle add/edit position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/settings/positions.php');
    }
    
    try {
        db()->beginTransaction();
        
        if ($_POST['action'] === 'add') {
            $stmt = db()->prepare("
                INSERT INTO group_positions (group_id, position_name, description, hierarchy_level, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId,
                $_POST['position_name'],
                $_POST['description'],
                $_POST['hierarchy_level'],
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $message = "Position added successfully";
            
        } elseif ($_POST['action'] === 'edit') {
            $stmt = db()->prepare("
                UPDATE group_positions SET
                    position_name = ?,
                    description = ?,
                    hierarchy_level = ?,
                    is_active = ?
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([
                $_POST['position_name'],
                $_POST['description'],
                $_POST['hierarchy_level'],
                isset($_POST['is_active']) ? 1 : 0,
                $_POST['position_id'],
                $groupId
            ]);
            $message = "Position updated successfully";
        }
        
        auditLog($_POST['action'] . '_position', 'group_positions', $_POST['position_id'] ?? null, null, $_POST);
        
        db()->commit();
        setFlash('success', $message);
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/modules/settings/positions.php');
}

// Handle delete position
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $positionId = (int)$_GET['id'];
    $confirm = $_GET['confirm'] ?? '';
    
    if ($confirm === 'yes') {
        try {
            // Check if position is assigned to any users
            $stmt = db()->prepare("SELECT COUNT(*) as count FROM users WHERE group_id = ? AND role = (SELECT position_name FROM group_positions WHERE id = ?)");
            $stmt->execute([$groupId, $positionId]);
            $userCount = $stmt->fetch()['count'];
            
            if ($userCount > 0) {
                throw new Exception("Cannot delete position as it is assigned to {$userCount} user(s). Reassign users first.");
            }
            
            $stmt = db()->prepare("DELETE FROM group_positions WHERE id = ? AND group_id = ?");
            $stmt->execute([$positionId, $groupId]);
            
            setFlash('success', 'Position deleted successfully');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        redirect('/modules/settings/positions.php');
    }
}

$page_title = 'Positions Management';
?>
<div class="settings-section">
    <div class="row">
        <div class="col-lg-8">
            <!-- Positions List -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Group Positions</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Position Name</th>
                                    <th>Description</th>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($positions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                                            <p class="text-muted mb-0">No positions defined yet</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($positions as $position): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($position['position_name']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($position['description'] ?? '', 0, 50)); ?></td>
                                            <td><?php echo $position['hierarchy_level']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $position['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $position['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editPosition(<?php echo htmlspecialchars(json_encode($position)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deletePosition(<?php echo $position['id']; ?>, '<?php echo htmlspecialchars($position['position_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
            <!-- Add Position Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Position</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label">Position Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="position_name" required placeholder="e.g., Organizing Secretary">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Responsibilities and duties..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hierarchy Level</label>
                            <input type="number" class="form-control" name="hierarchy_level" value="10" min="1" max="100">
                            <small class="text-muted">Lower numbers indicate higher authority (1 = highest)</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active Position
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Add Position
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Position Modal -->
<div class="modal fade" id="editPositionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Position</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="position_id" id="edit_position_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Position Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="position_name" id="edit_position_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hierarchy Level</label>
                        <input type="number" class="form-control" name="hierarchy_level" id="edit_hierarchy_level" min="1" max="100">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active Position
                            </label>
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
function editPosition(position) {
    document.getElementById('edit_position_id').value = position.id;
    document.getElementById('edit_position_name').value = position.position_name;
    document.getElementById('edit_description').value = position.description || '';
    document.getElementById('edit_hierarchy_level').value = position.hierarchy_level;
    document.getElementById('edit_is_active').checked = position.is_active == 1;
    $('#editPositionModal').modal('show');
}

function deletePosition(id, name) {
    Swal.fire({
        title: 'Delete Position?',
        html: `Are you sure you want to delete <strong>${name}</strong>?<br><br>This action cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `positions.php?delete=yes&id=${id}&confirm=yes`;
        }
    });
}
</script>

<?php include_once '../../templates/footer.php'; ?>
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
$stmt = db()->prepare("SELECT id, position_name FROM group_positions WHERE group_id = ? AND is_active = 1 ORDER BY hierarchy_level");
$stmt->execute([$groupId]);
$positions = $stmt->fetchAll();

// Define available permissions modules
$permissionModules = [
    'members' => [
        'label' => 'Members Management',
        'icon' => 'fas fa-users',
        'permissions' => ['view', 'create', 'edit', 'delete']
    ],
    'contributions' => [
        'label' => 'Contributions',
        'icon' => 'fas fa-coins',
        'permissions' => ['view', 'create', 'edit', 'delete']
    ],
    'loans' => [
        'label' => 'Loans Management',
        'icon' => 'fas fa-hand-holding-usd',
        'permissions' => ['view', 'apply', 'approve', 'disburse', 'repay']
    ],
    'meetings' => [
        'label' => 'Meetings',
        'icon' => 'fas fa-calendar-alt',
        'permissions' => ['view', 'create', 'edit', 'delete', 'attendance']
    ],
    'elections' => [
        'label' => 'Elections',
        'icon' => 'fas fa-vote-yea',
        'permissions' => ['view', 'create', 'vote', 'manage_candidates', 'view_results']
    ],
    'reports' => [
        'label' => 'Reports',
        'icon' => 'fas fa-chart-bar',
        'permissions' => ['view', 'export', 'financial', 'member', 'loan']
    ],
    'accounts' => [
        'label' => 'Accounts',
        'icon' => 'fas fa-university',
        'permissions' => ['view', 'create', 'edit', 'delete', 'reconcile']
    ],
    'investments' => [
        'label' => 'Investments',
        'icon' => 'fas fa-chart-pie',
        'permissions' => ['view', 'create', 'edit', 'delete', 'performance']
    ],
    'settings' => [
        'label' => 'System Settings',
        'icon' => 'fas fa-cog',
        'permissions' => ['view', 'edit', 'positions', 'permissions']
    ]
];

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/settings/permissions.php');
    }
    
    try {
        db()->beginTransaction();
        
        // Clear existing permissions for all positions
        $stmt = db()->prepare("DELETE FROM position_permissions WHERE position_id IN (SELECT id FROM group_positions WHERE group_id = ?)");
        $stmt->execute([$groupId]);
        
        // Insert new permissions
        $permStmt = db()->prepare("
            INSERT INTO position_permissions (position_id, permission_key, can_view, can_create, can_edit, can_delete)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($positions as $position) {
            foreach ($permissionModules as $moduleKey => $module) {
                foreach ($module['permissions'] as $perm) {
                    $permKey = "{$moduleKey}_{$perm}";
                    $value = isset($_POST["perm_{$position['id']}_{$permKey}"]) ? 1 : 0;
                    
                    // For view permission, set can_view
                    // For create, set can_create, etc.
                    $canView = ($perm === 'view' && $value) ? 1 : 0;
                    $canCreate = ($perm === 'create' && $value) ? 1 : 0;
                    $canEdit = ($perm === 'edit' && $value) ? 1 : 0;
                    $canDelete = ($perm === 'delete' && $value) ? 1 : 0;
                    
                    $permStmt->execute([$position['id'], $permKey, $canView, $canCreate, $canEdit, $canDelete]);
                }
            }
        }
        
        auditLog('update_permissions', 'group_positions', null, null, $_POST);
        
        db()->commit();
        setFlash('success', 'Permissions updated successfully!');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect('/modules/settings/permissions.php');
}

// Get current permissions
$stmt = db()->prepare("
    SELECT pp.*, gp.position_name 
    FROM position_permissions pp
    JOIN group_positions gp ON pp.position_id = gp.id
    WHERE gp.group_id = ?
");
$stmt->execute([$groupId]);
$existingPermissions = [];
while ($row = $stmt->fetch()) {
    $existingPermissions[$row['position_id']][$row['permission_key']] = $row;
}

$page_title = 'Permissions Management';
?>
<div class="settings-section">
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        Configure what each position can do in the system. Check the boxes to grant permissions.
    </div>
    
    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th style="width: 200px;">Module / Permission</th>
                        <?php foreach ($positions as $position): ?>
                            <th class="text-center"><?php echo htmlspecialchars($position['position_name']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissionModules as $moduleKey => $module): ?>
                        <tr class="table-secondary">
                            <td colspan="<?php echo count($positions) + 1; ?>">
                                <strong><i class="<?php echo $module['icon']; ?> me-2"></i><?php echo $module['label']; ?></strong>
                            </td>
                        </tr>
                        <?php foreach ($module['permissions'] as $perm): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php echo ucfirst($perm); ?>
                                    <small class="text-muted d-block"><?php echo getPermissionDescription($moduleKey, $perm); ?></small>
                                </td>
                                <?php foreach ($positions as $position): ?>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input" 
                                               name="perm_<?php echo $position['id']; ?>_<?php echo $moduleKey . '_' . $perm; ?>"
                                               <?php echo isPermissionGranted($existingPermissions, $position['id'], $moduleKey . '_' . $perm) ? 'checked' : ''; ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Save Permissions
            </button>
        </div>
    </form>
</div>

<?php
// Helper functions
function getPermissionDescription($module, $permission) {
    $descriptions = [
        'members_view' => 'View member list and profiles',
        'members_create' => 'Add new members to the group',
        'members_edit' => 'Edit member information',
        'members_delete' => 'Delete or deactivate members',
        'loans_view' => 'View loan applications',
        'loans_apply' => 'Apply for loans',
        'loans_approve' => 'Approve or reject loans',
        'loans_disburse' => 'Disburse approved loans',
        'loans_repay' => 'Make loan repayments',
        'reports_view' => 'Access reports section',
        'reports_export' => 'Export reports to CSV/PDF',
        'reports_financial' => 'View financial reports',
        'settings_view' => 'View system settings',
        'settings_edit' => 'Edit system settings',
        'settings_positions' => 'Manage positions',
        'settings_permissions' => 'Manage permissions'
    ];
    
    $key = $module . '_' . $permission;
    return $descriptions[$key] ?? 'Grant this permission';
}

function isPermissionGranted($permissions, $positionId, $permissionKey) {
    return isset($permissions[$positionId][$permissionKey]) && 
           ($permissions[$positionId][$permissionKey]['can_view'] || 
            $permissions[$positionId][$permissionKey]['can_create'] ||
            $permissions[$positionId][$permissionKey]['can_edit'] ||
            $permissions[$positionId][$permissionKey]['can_delete']);
}
?>

<style>
.table th, .table td {
    vertical-align: middle;
}
.form-check-input {
    cursor: pointer;
    width: 20px;
    height: 20px;
}
</style>

<?php include_once '../../templates/footer.php'; ?>
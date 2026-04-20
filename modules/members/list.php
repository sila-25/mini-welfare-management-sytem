<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

// Check permission - only authorized roles can access members
$allowed_roles = ['chairperson', 'secretary', 'treasurer', 'super_admin'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    $page_title = 'Access Denied';
    include_once '../../templates/header.php';
    include_once '../../templates/sidebar.php';
    include_once '../../templates/topbar.php';
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-0 shadow-lg text-center">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <i class="fas fa-lock fa-4x text-danger"></i>
                            </div>
                            <h2 class="h4 mb-3">Access Denied</h2>
                            <p class="text-muted mb-4">You do not have permission to access the Members section.</p>
                            <p class="text-muted mb-4">Only Chairpersons, Secretaries, Treasurers, and Administrators can access this area.</p>
                            <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include_once '../../templates/footer.php';
    exit();
}

// Rest of the members list code...
$page_title = 'Members List';
$current_page = 'members';

// Get all members
$members = [];
try {
    $stmt = db()->prepare("SELECT * FROM members WHERE group_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['group_id'] ?? 1]);
    $members = $stmt->fetchAll();
} catch (Exception $e) {
    $members = [];
}

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 pb-0">
                <h5 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>Members List</h5>
            </div>
            <div class="card-body">
                <a href="add.php" class="btn btn-success mb-3">
                    <i class="fas fa-plus me-2"></i>Add New Member
                </a>
                
                <?php if (empty($members)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No members found. Click the button above to add your first member.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Member Number</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Join Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo $member['id']; ?></td>
                                        <td><?php echo htmlspecialchars($member['member_number']); ?></td>
                                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo formatDate($member['join_date']); ?></td>
                                        <td>
                                            <span class="badge bg-success"><?php echo ucfirst($member['status']); ?></span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-info">View</a>
                                            <a href="edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_members');
Middleware::requireGroupAccess();

// Get member ID
$memberId = (int)($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';

// Validate member ID
if ($memberId <= 0) {
    setFlash('danger', 'Invalid member ID');
    redirect('/modules/members/list.php');
}

$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get member details before deletion for audit and validation
$stmt = db()->prepare("
    SELECT m.*, 
           COUNT(DISTINCT c.id) as contribution_count,
           COUNT(DISTINCT l.id) as loan_count,
           COALESCE(SUM(c.amount), 0) as total_contributions,
           COALESCE(SUM(l.total_repayable - COALESCE(lr.paid, 0)), 0) as outstanding_balance
    FROM members m
    LEFT JOIN contributions c ON m.id = c.member_id AND c.group_id = m.group_id
    LEFT JOIN loans l ON m.id = l.member_id AND l.group_id = m.group_id AND l.status IN ('disbursed', 'approved')
    LEFT JOIN (
        SELECT loan_id, SUM(amount) as paid
        FROM loan_repayments
        GROUP BY loan_id
    ) lr ON l.id = lr.loan_id
    WHERE m.id = ? AND m.group_id = ?
    GROUP BY m.id
");
$stmt->execute([$memberId, $groupId]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('danger', 'Member not found');
    redirect('/modules/members/list.php');
}

// Check if member has outstanding loans
if ($member['outstanding_balance'] > 0) {
    setFlash('danger', 'Cannot delete/deactivate member with outstanding loan balance of ' . formatCurrency($member['outstanding_balance']) . '. Please settle all loans first.');
    redirect('/modules/members/view.php?id=' . $memberId);
}

// Process deletion if confirmed
if ($confirm === 'yes') {
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Get old data for audit
        $oldData = (array)$member;
        
        // Check if this is hard delete or soft delete
        $hardDelete = isset($_GET['permanent']) && $_GET['permanent'] === 'yes' && $_SESSION['user_role'] === 'super_admin';
        
        if ($hardDelete) {
            // Hard delete - only for super admin
            // Check if member has any related records
            $checkStmt = db()->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM contributions WHERE member_id = ?) as contributions,
                    (SELECT COUNT(*) FROM loans WHERE member_id = ?) as loans,
                    (SELECT COUNT(*) FROM meeting_attendance WHERE member_id = ?) as attendance,
                    (SELECT COUNT(*) FROM votes WHERE voter_id = ?) as votes,
                    (SELECT COUNT(*) FROM candidates WHERE member_id = ?) as candidacies
            ");
            $checkStmt->execute([$memberId, $memberId, $memberId, $memberId, $memberId]);
            $related = $checkStmt->fetch();
            
            if ($related['contributions'] > 0 || $related['loans'] > 0 || $related['attendance'] > 0 || $related['votes'] > 0 || $related['candidacies'] > 0) {
                throw new Exception("Cannot permanently delete member with existing transaction records. Consider deactivation instead.");
            }
            
            // Delete related records
            $deleteStmt = db()->prepare("DELETE FROM member_family WHERE member_id = ?");
            $deleteStmt->execute([$memberId]);
            
            $deleteStmt = db()->prepare("DELETE FROM member_notes WHERE member_id = ?");
            $deleteStmt->execute([$memberId]);
            
            $deleteStmt = db()->prepare("DELETE FROM member_documents WHERE member_id = ?");
            $deleteStmt->execute([$memberId]);
            
            // Delete user account if exists
            if ($member['user_id']) {
                $deleteStmt = db()->prepare("DELETE FROM users WHERE id = ?");
                $deleteStmt->execute([$member['user_id']]);
            }
            
            // Delete member
            $deleteStmt = db()->prepare("DELETE FROM members WHERE id = ? AND group_id = ?");
            $deleteStmt->execute([$memberId, $groupId]);
            
            $message = "Member permanently deleted from the system";
            $action = 'permanent_delete_member';
            
        } else {
            // Soft delete - deactivate member
            $stmt = db()->prepare("
                UPDATE members 
                SET status = 'inactive', 
                    updated_at = NOW() 
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$memberId, $groupId]);
            
            // Deactivate user account if exists
            if ($member['user_id']) {
                $stmt = db()->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$member['user_id']]);
            }
            
            // Add deactivation note
            $noteStmt = db()->prepare("
                INSERT INTO member_notes (group_id, member_id, note_type, note, created_by)
                VALUES (?, ?, 'warning', ?, ?)
            ");
            $noteStmt->execute([
                $groupId,
                $memberId,
                "Member deactivated on " . date('F d, Y H:i:s') . " by " . ($_SESSION['user_name'] ?? 'System'),
                $userId
            ]);
            
            $message = "Member deactivated successfully";
            $action = 'deactivate_member';
        }
        
        // Audit log
        auditLog($action, 'members', $memberId, $oldData, ['status' => $hardDelete ? 'deleted' : 'inactive']);
        
        // Commit transaction
        db()->commit();
        
        // Set success message
        setFlash('success', $message);
        
        // Redirect based on action
        if ($hardDelete) {
            redirect('/modules/members/list.php');
        } else {
            redirect('/modules/members/list.php?status=inactive');
        }
        
    } catch (Exception $e) {
        // Rollback on error
        db()->rollback();
        error_log("Error deleting member: " . $e->getMessage());
        setFlash('danger', 'Error: ' . $e->getMessage());
        redirect('/modules/members/view.php?id=' . $memberId);
    }
}

// If not confirmed, show confirmation page
$page_title = 'Confirm Deactivation';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0 rounded-3 mt-5">
                <div class="card-header bg-danger text-white py-3">
                    <h4 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Member Deactivation
                    </h4>
                </div>
                
                <div class="card-body p-4">
                    <!-- Member Information -->
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Warning:</strong> You are about to deactivate a member. This action can be reversed later.
                    </div>
                    
                    <div class="member-info mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <?php if ($member['profile_photo']): ?>
                                    <img src="<?php echo APP_URL . '/' . $member['profile_photo']; ?>" class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                                        <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($member['member_number']); ?>
                                </p>
                                <?php if ($member['email']): ?>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($member['email']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($member['phone']): ?>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($member['phone']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h5 class="text-primary mb-2">
                                    <?php echo number_format($member['contribution_count'] ?? 0); ?>
                                </h5>
                                <small class="text-muted">Contributions Made</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h5 class="text-success mb-2">
                                    <?php echo formatCurrency($member['total_contributions'] ?? 0); ?>
                                </h5>
                                <small class="text-muted">Total Contributions</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <h5 class="text-danger mb-2">
                                    <?php echo formatCurrency($member['outstanding_balance'] ?? 0); ?>
                                </h5>
                                <small class="text-muted">Outstanding Balance</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Validation Checks -->
                    <?php if ($member['outstanding_balance'] > 0): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>Cannot Deactivate:</strong> This member has an outstanding loan balance of <?php echo formatCurrency($member['outstanding_balance']); ?>.
                            Please collect all pending payments before deactivation.
                        </div>
                    <?php endif; ?>
                    
                    <?php if (($member['contribution_count'] ?? 0) > 0): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This member has <?php echo number_format($member['contribution_count']); ?> contribution record(s).
                            Deactivating will not delete these historical records.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Consequences -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-list-ul me-2"></i>
                                What happens after deactivation?
                            </h6>
                            <ul class="mb-0">
                                <li>The member will no longer be able to log in to the system</li>
                                <li>The member will be excluded from active member lists and reports</li>
                                <li>All historical data (contributions, loans, attendance) will be preserved</li>
                                <li>The member can be reactivated at any time by an administrator</li>
                                <li>No new contributions or loans can be recorded for deactivated members</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Confirmation Form -->
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        
                        <div>
                            <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                                <button type="button" class="btn btn-danger me-2" onclick="confirmPermanentDelete()">
                                    <i class="fas fa-trash-alt me-2"></i>Permanently Delete
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($member['outstanding_balance'] == 0): ?>
                                <a href="?id=<?php echo $memberId; ?>&confirm=yes" class="btn btn-warning">
                                    <i class="fas fa-user-slash me-2"></i>Confirm Deactivation
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock me-2"></i>Deactivation Not Available
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Modal (for multiple members) -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i>Bulk Deactivation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to deactivate multiple members. This action will:</p>
                <ul>
                    <li>Deactivate all selected members</li>
                    <li>Prevent them from accessing the system</li>
                    <li>Keep all historical data intact</li>
                </ul>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Members with outstanding loans cannot be deactivated.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">Confirm Deactivation</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmPermanentDelete() {
    Swal.fire({
        title: 'Permanently Delete Member?',
        html: `
            <div class="text-left">
                <p><strong>Warning:</strong> This action is irreversible and will:</p>
                <ul class="text-left">
                    <li>Permanently remove all member data</li>
                    <li>Delete associated user account</li>
                    <li>Remove family and document records</li>
                    <li>This action cannot be undone</li>
                </ul>
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    Type <strong>PERMANENT</strong> to confirm
                </div>
                <input type="text" id="confirmText" class="form-control" placeholder="Type PERMANENT here">
            </div>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete permanently',
        preConfirm: () => {
            const confirmText = document.getElementById('confirmText').value;
            if (confirmText !== 'PERMANENT') {
                Swal.showValidationMessage('Please type PERMANENT to confirm');
                return false;
            }
            return true;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?id=<?php echo $memberId; ?>&confirm=yes&permanent=yes';
        }
    });
}

// Bulk delete function
function bulkDelete(selectedIds) {
    if (selectedIds.length === 0) {
        Swal.fire('No Selection', 'Please select members to deactivate', 'warning');
        return;
    }
    
    $('#bulkDeleteModal').modal('show');
    
    $('#confirmBulkDelete').off('click').on('click', function() {
        $.ajax({
            url: 'bulk_delete.php',
            method: 'POST',
            data: {
                ids: selectedIds,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'An error occurred', 'error');
            }
        });
    });
}
</script>

<style>
.member-info {
    background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    border-radius: 10px;
    padding: 20px;
}

.card {
    border: none;
}

.alert ul {
    margin-bottom: 0;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
    border: none;
    color: #000;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #e0a800 0%, #c69500 100%);
}
</style>

<?php include_once '../../templates/footer.php'; ?>
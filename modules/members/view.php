<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$memberId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get member details
$stmt = db()->prepare("
    SELECT m.*, u.username as user_account
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.id = ? AND m.group_id = ?
");
$stmt->execute([$memberId, $groupId]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('danger', 'Member not found');
    redirect('/modules/members/index.php');
}

// Get contributions
$stmt = db()->prepare("
    SELECT * FROM contributions
    WHERE member_id = ? AND group_id = ?
    ORDER BY payment_date DESC
    LIMIT 10
");
$stmt->execute([$memberId, $groupId]);
$contributions = $stmt->fetchAll();

// Get loans
$stmt = db()->prepare("
    SELECT l.*, 
           COALESCE(lr.paid, 0) as amount_paid,
           (l.total_repayable - COALESCE(lr.paid, 0)) as balance
    FROM loans l
    LEFT JOIN (
        SELECT loan_id, SUM(amount) as paid
        FROM loan_repayments
        GROUP BY loan_id
    ) lr ON l.id = lr.loan_id
    WHERE l.member_id = ? AND l.group_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$memberId, $groupId]);
$loans = $stmt->fetchAll();

// Get attendance
$stmt = db()->prepare("
    SELECT ma.*, m.title, m.meeting_date
    FROM meeting_attendance ma
    JOIN meetings m ON ma.meeting_id = m.id
    WHERE ma.member_id = ? AND m.group_id = ?
    ORDER BY m.meeting_date DESC
    LIMIT 10
");
$stmt->execute([$memberId, $groupId]);
$attendance = $stmt->fetchAll();

// Get family members
$stmt = db()->prepare("
    SELECT * FROM member_family
    WHERE member_id = ? AND group_id = ?
");
$stmt->execute([$memberId, $groupId]);
$family = $stmt->fetchAll();

// Get documents
$stmt = db()->prepare("
    SELECT * FROM member_documents
    WHERE member_id = ? AND group_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$memberId, $groupId]);
$documents = $stmt->fetchAll();

// Get notes
$stmt = db()->prepare("
    SELECT mn.*, u.first_name, u.last_name
    FROM member_notes mn
    JOIN users u ON mn.created_by = u.id
    WHERE mn.member_id = ? AND mn.group_id = ?
    ORDER BY mn.created_at DESC
");
$stmt->execute([$memberId, $groupId]);
$notes = $stmt->fetchAll();

$page_title = $member['first_name'] . ' ' . $member['last_name'];
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/modules/members/index.php">Members</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($page_title); ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Member Profile</h1>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="editMember(<?php echo $member['id']; ?>)">
                <i class="fas fa-edit me-2"></i>Edit Profile
            </button>
            <button type="button" class="btn btn-success" onclick="recordPayment(<?php echo $member['id']; ?>)">
                <i class="fas fa-money-bill me-2"></i>Record Payment
            </button>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Profile Info -->
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <?php if ($member['profile_photo']): ?>
                        <img src="<?php echo APP_URL . '/' . $member['profile_photo']; ?>" class="rounded-circle img-fluid mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-gradient text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 150px; height: 150px; font-size: 3rem;">
                            <?php echo strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h3>
                    <p class="text-muted">
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($member['member_number']); ?></span>
                        <?php if ($member['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactive</span>
                        <?php endif; ?>
                    </p>
                    
                    <div class="row mt-3">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <h5 class="text-success mb-0"><?php echo formatCurrency(getMemberTotalContributions($member['id'], $groupId)); ?></h5>
                                <small class="text-muted">Total Contributions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <h5 class="text-danger mb-0"><?php echo formatCurrency(getMemberLoanBalance($member['id'], $groupId)); ?></h5>
                                <small class="text-muted">Loan Balance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <i class="fas fa-envelope text-primary me-2"></i>
                        <strong>Email:</strong> <?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-phone text-primary me-2"></i>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-id-card text-primary me-2"></i>
                        <strong>ID Number:</strong> <?php echo htmlspecialchars($member['id_number'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-birthday-cake text-primary me-2"></i>
                        <strong>Date of Birth:</strong> <?php echo formatDate($member['date_of_birth']); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-venus-mars text-primary me-2"></i>
                        <strong>Gender:</strong> <?php echo ucfirst($member['gender'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-briefcase text-primary me-2"></i>
                        <strong>Occupation:</strong> <?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                        <strong>Address:</strong> <?php echo htmlspecialchars($member['physical_address'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        <strong>Joined:</strong> <?php echo formatDate($member['join_date']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Emergency Contact</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <i class="fas fa-user text-primary me-2"></i>
                        <strong>Name:</strong> <?php echo htmlspecialchars($member['emergency_contact'] ?? 'N/A'); ?>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-phone-alt text-primary me-2"></i>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($member['emergency_phone'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>
            
            <!-- Family Members -->
            <?php if (!empty($family)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Family Members</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($family as $familyMember): ?>
                    <div class="mb-2">
                        <strong><?php echo ucfirst($familyMember['relationship']); ?>:</strong>
                        <?php echo htmlspecialchars($familyMember['full_name']); ?>
                        <?php if ($familyMember['date_of_birth']): ?>
                            <br><small class="text-muted">DOB: <?php echo formatDate($familyMember['date_of_birth']); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column - Detailed Information -->
        <div class="col-lg-8">
            <!-- Bio -->
            <?php if ($member['bio']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($member['bio'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Contributions -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Contributions</h5>
                    <a href="../contributions/index.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($contributions)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contributions as $contribution): ?>
                                <tr>
                                    <td><?php echo formatDate($contribution['payment_date']); ?></td>
                                    <td><?php echo ucfirst($contribution['contribution_type']); ?></td>
                                    <td class="text-success fw-bold"><?php echo formatCurrency($contribution['amount']); ?></td>
                                    <td><?php echo ucfirst($contribution['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($contribution['receipt_number']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No contributions recorded yet</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Loans -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Loan History</h5>
                    <a href="../loans/index.php?member_id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($loans)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Loan #</th>
                                    <th>Principal</th>
                                    <th>Total Payable</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                    <td><?php echo formatCurrency($loan['principal_amount']); ?></td>
                                    <td><?php echo formatCurrency($loan['total_repayable']); ?></td>
                                    <td><?php echo formatCurrency($loan['amount_paid'] ?? 0); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($loan['balance']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $loan['status'] === 'disbursed' ? 'success' : 
                                                ($loan['status'] === 'approved' ? 'info' : 
                                                ($loan['status'] === 'pending' ? 'warning' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No loans taken yet</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attendance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Meeting Attendance</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($attendance)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Meeting Date</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Check-in Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $attend): ?>
                                <tr>
                                    <td><?php echo formatDate($attend['meeting_date']); ?></td>
                                    <td><?php echo htmlspecialchars($attend['title']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $attend['status'] === 'present' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($attend['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $attend['check_in_time'] ?? 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No attendance records</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Notes & Comments</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                        <i class="fas fa-plus me-1"></i>Add Note
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($notes)): ?>
                        <?php foreach ($notes as $note): ?>
                        <div class="border-bottom mb-3 pb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?></strong>
                                <small class="text-muted"><?php echo formatDateTime($note['created_at']); ?></small>
                            </div>
                            <span class="badge bg-secondary mb-2"><?php echo ucfirst($note['note_type']); ?></span>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">No notes added yet</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Documents -->
            <?php if (!empty($documents)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Documents</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($documents as $document): ?>
                        <a href="<?php echo APP_URL . '/' . $document['file_path']; ?>" class="list-group-item list-group-item-action" target="_blank">
                            <i class="fas fa-file-alt me-2"></i>
                            <?php echo htmlspecialchars($document['document_name']); ?>
                            <small class="text-muted">(<?php echo formatFileSize($document['file_size']); ?>)</small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Note for <?php echo htmlspecialchars($member['first_name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="add_note.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Note Type</label>
                        <select class="form-select" name="note_type" required>
                            <option value="general">General</option>
                            <option value="complaint">Complaint</option>
                            <option value="achievement">Achievement</option>
                            <option value="warning">Warning</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea class="form-control" name="note" rows="4" required placeholder="Enter your note here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMember(id) {
    window.location.href = 'edit.php?id=' + id;
}

function recordPayment(memberId) {
    window.location.href = '../contributions/add.php?member_id=' + memberId;
}
</script>

<?php include_once '../../templates/footer.php'; ?>
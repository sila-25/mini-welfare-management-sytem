<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_elections');
Middleware::requireGroupAccess();

$electionId = (int)($_GET['election_id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get election details
$stmt = db()->prepare("SELECT * FROM elections WHERE id = ? AND group_id = ?");
$stmt->execute([$electionId, $groupId]);
$election = $stmt->fetch();

if (!$election) {
    setFlash('danger', 'Election not found');
    redirect('/modules/elections/index.php');
}

// Get current candidates
$stmt = db()->prepare("
    SELECT c.*, m.first_name, m.last_name, m.member_number, m.profile_photo
    FROM candidates c
    JOIN members m ON c.member_id = m.id
    WHERE c.election_id = ?
    ORDER BY c.order_position, c.created_at
");
$stmt->execute([$electionId]);
$candidates = $stmt->fetchAll();

// Get members not yet nominated
$stmt = db()->prepare("
    SELECT m.id, m.first_name, m.last_name, m.member_number
    FROM members m
    WHERE m.group_id = ? AND m.status = 'active'
    AND m.id NOT IN (SELECT member_id FROM candidates WHERE election_id = ?)
    ORDER BY m.first_name
");
$stmt->execute([$groupId, $electionId]);
$availableMembers = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/elections/candidates.php?election_id={$electionId}");
    }
    
    try {
        db()->beginTransaction();
        
        $memberId = (int)$_POST['member_id'];
        $manifesto = trim($_POST['manifesto']);
        $party = !empty($_POST['party']) ? trim($_POST['party']) : null;
        $slogan = !empty($_POST['slogan']) ? trim($_POST['slogan']) : null;
        
        // Check if member is already a candidate
        $stmt = db()->prepare("SELECT id FROM candidates WHERE election_id = ? AND member_id = ?");
        $stmt->execute([$electionId, $memberId]);
        if ($stmt->fetch()) {
            throw new Exception('This member is already a candidate for this election');
        }
        
        // Handle photo upload
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/candidates/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $filename = 'candidate_' . $electionId . '_' . $memberId . '_' . time() . '.' . $extension;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photo = 'uploads/candidates/' . $filename;
            }
        }
        
        // Insert candidate
        $stmt = db()->prepare("
            INSERT INTO candidates (election_id, member_id, manifesto, party, slogan, photo, status)
            VALUES (?, ?, ?, ?, ?, ?, 'nominated')
        ");
        $stmt->execute([$electionId, $memberId, $manifesto, $party, $slogan, $photo]);
        
        auditLog('add_candidate', 'candidates', db()->lastInsertId(), null, ['election_id' => $electionId, 'member_id' => $memberId]);
        
        db()->commit();
        setFlash('success', 'Candidate added successfully!');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect("/modules/elections/candidates.php?election_id={$electionId}");
}

// Handle candidate removal
if (isset($_GET['remove']) && isset($_GET['candidate_id'])) {
    $candidateId = (int)$_GET['candidate_id'];
    $confirm = $_GET['confirm'] ?? '';
    
    if ($confirm === 'yes') {
        try {
            $stmt = db()->prepare("DELETE FROM candidates WHERE id = ? AND election_id = ?");
            $stmt->execute([$candidateId, $electionId]);
            setFlash('success', 'Candidate removed successfully');
        } catch (Exception $e) {
            setFlash('danger', 'Error removing candidate');
        }
        redirect("/modules/elections/candidates.php?election_id={$electionId}");
    }
}

$page_title = 'Manage Candidates - ' . $election['title'];
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <!-- Candidates List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-check me-2"></i>
                        Candidates for <?php echo htmlspecialchars($election['position']); ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($candidates)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-friends fa-3x text-muted mb-3 d-block"></i>
                            <h5 class="text-muted">No candidates added yet</h5>
                            <p class="text-muted">Add candidates using the form on the right</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Candidate</th>
                                        <th>Party</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $index => $candidate): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($candidate['photo']): ?>
                                                    <img src="<?php echo APP_URL . '/' . $candidate['photo']; ?>" class="rounded-circle me-2" width="40" height="40" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <?php echo strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></div>
                                                    <small class="text-muted"><?php echo $candidate['member_number']; ?></small>
                                                    <?php if ($candidate['slogan']): ?>
                                                        <br><small class="text-info">"<?php echo htmlspecialchars($candidate['slogan']); ?>"</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?>
                                         </td>
                                        <td>
                                            <span class="badge bg-<?php echo $candidate['status'] === 'approved' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($candidate['status']); ?>
                                            </span>
                                         </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="viewManifesto(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($candidate['manifesto']); ?>')">
                                                    <i class="fas fa-file-alt"></i>
                                                </button>
                                                <?php if ($candidate['status'] === 'nominated'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="approveCandidate(<?php echo $candidate['id']; ?>, <?php echo $electionId; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeCandidate(<?php echo $candidate['id']; ?>, '<?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>', <?php echo $electionId; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Ballot Paper Preview -->
            <?php if (!empty($candidates)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-pdf me-2"></i>
                        Ballot Paper Preview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This is how voters will see the ballot paper. You can print this for manual voting.
                    </div>
                    <a href="ballot.php?election_id=<?php echo $electionId; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print me-2"></i>View/Print Ballot Paper
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- Add Candidate Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Add Candidate
                    </h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Member <span class="text-danger">*</span></label>
                            <select class="form-select select2" name="member_id" required>
                                <option value="">Choose member...</option>
                                <?php foreach ($availableMembers as $member): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['member_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Party/Affiliation</label>
                            <input type="text" class="form-control" name="party" placeholder="e.g., Independent, Reform Party">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Slogan/Campaign Message</label>
                            <input type="text" class="form-control" name="slogan" placeholder="e.g., Vote for Progress">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Manifesto/Promises</label>
                            <textarea class="form-control" name="manifesto" rows="4" placeholder="What the candidate promises to do if elected..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Candidate Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                            <small class="text-muted">Optional. Square image recommended (500x500px)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Candidate
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Election Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Election Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Title:</strong> <?php echo htmlspecialchars($election['title']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($election['position']); ?></p>
                    <p><strong>Date:</strong> <?php echo formatDate($election['election_date']); ?></p>
                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($election['start_time'])); ?> - <?php echo date('h:i A', strtotime($election['end_time'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $election['status'] === 'upcoming' ? 'secondary' : ($election['status'] === 'ongoing' ? 'warning' : 'success'); ?>">
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </p>
                    <hr>
                    <a href="vote.php?election_id=<?php echo $electionId; ?>" class="btn btn-success w-100">
                        <i class="fas fa-vote-yea me-2"></i>Go to Voting Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manifesto Modal -->
<div class="modal fade" id="manifestoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Candidate Manifesto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="manifestoContent"></div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search for a member...'
    });
});

function viewManifesto(id, manifesto) {
    $('#manifestoContent').html('<p>' + (manifesto || 'No manifesto provided') + '</p>');
    $('#manifestoModal').modal('show');
}

function approveCandidate(candidateId, electionId) {
    Swal.fire({
        title: 'Approve Candidate?',
        text: 'Approved candidates will appear on the ballot paper.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'approve_candidate.php?id=' + candidateId + '&election_id=' + electionId;
        }
    });
}

function removeCandidate(candidateId, name, electionId) {
    Swal.fire({
        title: 'Remove Candidate?',
        html: `Are you sure you want to remove <strong>${name}</strong> from this election?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, remove'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'candidates.php?election_id=' + electionId + '&remove=yes&candidate_id=' + candidateId + '&confirm=yes';
        }
    });
}
</script>

<?php include_once '../../templates/footer.php'; ?>
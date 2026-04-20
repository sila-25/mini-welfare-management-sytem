<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is chairperson
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'chairperson') {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Create Election';
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get members for voter list
$stmt = db()->prepare("SELECT id, first_name, last_name, member_number FROM members WHERE group_id = ? AND status = 'active' ORDER BY first_name");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/elections/create.php');
    }
    
    try {
        db()->beginTransaction();
        
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $position = trim($_POST['position']);
        $electionDate = $_POST['election_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $votingMethod = $_POST['voting_method'];
        $maxVotesPerVoter = (int)$_POST['max_votes_per_voter'];
        $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        $stmt = db()->prepare("
            INSERT INTO elections (
                group_id, title, description, position, election_date,
                start_time, end_time, voting_method, max_votes_per_voter,
                is_anonymous, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', ?)
        ");
        $stmt->execute([$groupId, $title, $description, $position, $electionDate, $startTime, $endTime, $votingMethod, $maxVotesPerVoter, $isAnonymous, $userId]);
        $electionId = db()->lastInsertId();
        
        // Add voters
        if (isset($_POST['voters']) && is_array($_POST['voters'])) {
            $voterStmt = db()->prepare("INSERT INTO election_voters (election_id, member_id, has_voted) VALUES (?, ?, 0)");
            foreach ($_POST['voters'] as $memberId) {
                $voterStmt->execute([$electionId, $memberId]);
            }
        } else {
            $voterStmt = db()->prepare("INSERT INTO election_voters (election_id, member_id, has_voted) SELECT ?, id, 0 FROM members WHERE group_id = ? AND status = 'active'");
            $voterStmt->execute([$electionId, $groupId]);
        }
        
        auditLog('create_election', 'elections', $electionId, null, ['title' => $title]);
        
        db()->commit();
        setFlash('success', "Election '{$title}' created successfully!");
        redirect("/modules/elections/candidates.php?election_id={$electionId}");
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">Create New Election</h1>
                        <p class="text-muted mt-2">Set up a new election for your group</p>
                    </div>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                </div>
                
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Election Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" required placeholder="e.g., 2024 Annual General Elections">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Describe the purpose and rules of this election"></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Position <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="position" required placeholder="e.g., Chairperson, Treasurer, Secretary">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Voting Method</label>
                                    <select class="form-select" name="voting_method">
                                        <option value="both">Online & Manual</option>
                                        <option value="online">Online Only</option>
                                        <option value="manual">Manual Only</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Election Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="election_date" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time" value="08:00" required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time" value="17:00" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Max Votes per Voter</label>
                                    <input type="number" class="form-control" name="max_votes_per_voter" value="1" min="1" max="10">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" name="is_anonymous" id="isAnonymous" checked>
                                        <label class="form-check-label" for="isAnonymous">Anonymous Voting</label>
                                    </div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label class="form-label">Eligible Voters</label>
                                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                        <div class="form-check mb-2">
                                            <input type="checkbox" class="form-check-input" id="selectAllVoters">
                                            <label class="form-check-label fw-bold" for="selectAllVoters">Select All Members</label>
                                        </div>
                                        <hr>
                                        <?php foreach ($members as $member): ?>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input voter-checkbox" name="voters[]" value="<?php echo $member['id']; ?>">
                                                <label class="form-check-label"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> (<?php echo $member['member_number']; ?>)</label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">If none selected, all active members will be eligible to vote</small>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Create Election</button>
                                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('#selectAllVoters').change(function() {
    $('.voter-checkbox').prop('checked', $(this).prop('checked'));
});
</script>

<?php include_once '../../templates/footer.php'; ?>
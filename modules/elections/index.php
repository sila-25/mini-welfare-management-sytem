<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Elections Management';
$current_page = 'elections';
$groupId = $_SESSION['group_id'];
$userRole = $_SESSION['user_role'];
$memberId = $_SESSION['member_id'] ?? null;

// Get member ID if not set
if (!$memberId) {
    $stmt = db()->prepare("SELECT id FROM members WHERE user_id = ? AND group_id = ?");
    $stmt->execute([$_SESSION['user_id'], $groupId]);
    $member = $stmt->fetch();
    if ($member) {
        $memberId = $member['id'];
        $_SESSION['member_id'] = $memberId;
    }
}

// Get all elections
$stmt = db()->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as candidates_count,
           COUNT(DISTINCT ev.id) as voters_count,
           SUM(CASE WHEN ev.has_voted = 1 THEN 1 ELSE 0 END) as votes_cast
    FROM elections e
    LEFT JOIN candidates c ON e.id = c.election_id
    LEFT JOIN election_voters ev ON e.id = ev.election_id
    WHERE e.group_id = ?
    GROUP BY e.id
    ORDER BY e.election_date DESC, e.created_at DESC
");
$stmt->execute([$groupId]);
$elections = $stmt->fetchAll();

include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><i class="fas fa-vote-yea me-2 text-primary"></i>Elections Management</h1>
                <p class="text-muted mt-2">View and participate in group elections</p>
            </div>
            <?php if ($userRole === 'chairperson'): ?>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Election</a>
            <?php endif; ?>
        </div>

        <?php if (empty($elections)): ?>
            <div class="card border-0 shadow-sm text-center py-5">
                <i class="fas fa-vote-yea fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No elections found</h5>
                <?php if ($userRole === 'chairperson'): ?>
                    <p class="text-muted">Click the button above to create your first election</p>
                <?php else: ?>
                    <p class="text-muted">No active elections at this time</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($elections as $election): 
                    $canVote = false;
                    $hasVoted = false;
                    
                    if ($memberId && $election['status'] === 'ongoing') {
                        $stmt = db()->prepare("SELECT has_voted FROM election_voters WHERE election_id = ? AND member_id = ?");
                        $stmt->execute([$election['id'], $memberId]);
                        $voter = $stmt->fetch();
                        $hasVoted = $voter && $voter['has_voted'];
                        $canVote = !$hasVoted && $election['voting_method'] !== 'manual';
                    }
                ?>
                    <div class="col-md-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-3">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-<?php echo $election['status'] === 'ongoing' ? 'success' : ($election['status'] === 'upcoming' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                    <span class="badge bg-info"><?php echo $election['candidates_count']; ?> candidates</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($election['title']); ?></h5>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($election['description'] ?? '', 0, 100)); ?></p>
                                <div class="mt-3">
                                    <p class="mb-1"><i class="fas fa-user-tie me-2"></i>Position: <?php echo htmlspecialchars($election['position']); ?></p>
                                    <p class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Date: <?php echo formatDate($election['election_date']); ?></p>
                                    <p class="mb-1"><i class="fas fa-clock me-2"></i>Time: <?php echo date('h:i A', strtotime($election['start_time'])); ?> - <?php echo date('h:i A', strtotime($election['end_time'])); ?></p>
                                    <p class="mb-1"><i class="fas fa-users me-2"></i>Turnout: <?php echo $election['voters_count'] > 0 ? round(($election['votes_cast'] / $election['voters_count']) * 100, 1) : 0; ?>%</p>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 pb-3">
                                <?php if ($election['status'] === 'ongoing' && $canVote): ?>
                                    <a href="vote.php?election_id=<?php echo $election['id']; ?>" class="btn btn-success w-100">
                                        <i class="fas fa-vote-yea me-2"></i>Vote Now
                                    </a>
                                <?php elseif ($election['status'] === 'ongoing' && $hasVoted): ?>
                                    <button class="btn btn-secondary w-100" disabled><i class="fas fa-check me-2"></i>Already Voted</button>
                                <?php elseif ($election['status'] === 'completed'): ?>
                                    <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-info w-100">
                                        <i class="fas fa-chart-bar me-2"></i>View Results
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled><i class="fas fa-clock me-2"></i>Coming Soon</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
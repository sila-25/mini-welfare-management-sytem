<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$electionId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

$stmt = db()->prepare("
    SELECT e.*, COUNT(DISTINCT ev.id) as total_voters, SUM(CASE WHEN ev.has_voted = 1 THEN 1 ELSE 0 END) as votes_cast
    FROM elections e
    LEFT JOIN election_voters ev ON e.id = ev.election_id
    WHERE e.id = ? AND e.group_id = ?
    GROUP BY e.id
");
$stmt->execute([$electionId, $groupId]);
$election = $stmt->fetch();

if (!$election) {
    setFlash('danger', 'Election not found');
    redirect('/modules/elections/index.php');
}

$stmt = db()->prepare("
    SELECT c.*, m.first_name, m.last_name, m.member_number, m.profile_photo,
           (c.votes / NULLIF((SELECT COUNT(*) FROM election_voters WHERE election_id = ? AND has_voted = 1), 0) * 100) as percentage
    FROM candidates c
    JOIN members m ON c.member_id = m.id
    WHERE c.election_id = ? AND c.status = 'approved'
    ORDER BY c.votes DESC
");
$stmt->execute([$electionId, $electionId]);
$results = $stmt->fetchAll();

$totalVotes = $election['votes_cast'];
$winner = !empty($results) ? $results[0] : null;
$turnout = $election['total_voters'] > 0 ? round(($election['votes_cast'] / $election['total_voters']) * 100, 1) : 0;

$page_title = 'Election Results - ' . $election['title'];
include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
include_once '../../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($election['title']); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($election['position']); ?> Election Results</p>
            </div>
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Elections</a>
        </div>

        <?php if ($winner && $election['status'] === 'completed'): ?>
            <div class="card bg-success text-white mb-4">
                <div class="card-body text-center py-4">
                    <i class="fas fa-trophy fa-4x mb-3"></i>
                    <h2>Winner Announced!</h2>
                    <h3 class="mb-0"><?php echo htmlspecialchars($winner['first_name'] . ' ' . $winner['last_name']); ?></h3>
                    <p class="mb-0">Received <?php echo number_format($winner['votes']); ?> votes (<?php echo round($winner['percentage'], 1); ?>%)</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center border-0 shadow-sm"><div class="card-body"><h5>Total Voters</h5><h2><?php echo number_format($election['total_voters']); ?></h2></div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-0 shadow-sm"><div class="card-body"><h5>Votes Cast</h5><h2><?php echo number_format($election['votes_cast']); ?></h2></div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-0 shadow-sm"><div class="card-body"><h5>Turnout</h5><h2><?php echo $turnout; ?>%</h2></div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-0 shadow-sm"><div class="card-body"><h5>Candidates</h5><h2><?php echo count($results); ?></h2></div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Vote Breakdown</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Rank</th><th>Candidate</th><th>Party</th><th>Votes</th><th>Percentage</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $candidate): ?>
                            <tr>
                                <td class="text-center"><?php echo $index == 0 ? '<i class="fas fa-crown text-warning"></i>' : ($index + 1); ?></td>
                                <td><div class="d-flex align-items-center"><div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;"><?php echo strtoupper(substr($candidate['first_name'], 0, 1)); ?></div><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></div></td>
                                <td><?php echo htmlspecialchars($candidate['party'] ?? 'Independent'); ?></td>
                                <td class="fw-bold"><?php echo number_format($candidate['votes']); ?></td>
                                <td><div class="d-flex align-items-center"><div class="progress flex-grow-1 me-2" style="height: 20px;"><div class="progress-bar bg-success" style="width: <?php echo $candidate['percentage']; ?>%"></div></div><span><?php echo round($candidate['percentage'], 1); ?>%</span></div></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../templates/footer.php'; ?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$electionId = (int)($_GET['election_id'] ?? 0);
$groupId = $_SESSION['group_id'];
$memberId = $_SESSION['member_id'] ?? null;

// Get election details
$stmt = db()->prepare("
    SELECT e.*, g.group_name 
    FROM elections e
    JOIN groups g ON e.group_id = g.id
    WHERE e.id = ? AND e.group_id = ?
");
$stmt->execute([$electionId, $groupId]);
$election = $stmt->fetch();

if (!$election) {
    die('Election not found');
}

// Get candidates
$stmt = db()->prepare("
    SELECT c.*, m.first_name, m.last_name, m.member_number, m.profile_photo
    FROM candidates c
    JOIN members m ON c.member_id = m.id
    WHERE c.election_id = ? AND c.status = 'approved'
    ORDER BY c.order_position, c.created_at
");
$stmt->execute([$electionId]);
$candidates = $stmt->fetchAll();

// Check if user has already voted
$hasVoted = false;
if ($memberId) {
    $stmt = db()->prepare("SELECT has_voted FROM election_voters WHERE election_id = ? AND member_id = ?");
    $stmt->execute([$electionId, $memberId]);
    $voter = $stmt->fetch();
    $hasVoted = $voter && $voter['has_voted'];
}

$page_title = 'Ballot Paper - ' . $election['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            .ballot-card { break-inside: avoid; page-break-inside: avoid; }
            body { padding: 0; margin: 0; }
        }
        .ballot-card {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .ballot-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .candidate-option {
            cursor: pointer;
            transition: all 0.2s;
        }
        .candidate-option:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .vote-radio {
            transform: scale(1.2);
            margin-right: 15px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="header text-center">
            <h1 class="mb-2"><?php echo htmlspecialchars($election['group_name']); ?></h1>
            <h3>Official Ballot Paper</h3>
            <p class="mb-0">Election: <?php echo htmlspecialchars($election['title']); ?></p>
            <p>Position: <?php echo htmlspecialchars($election['position']); ?></p>
            <p class="mb-0">
                <small>
                    Date: <?php echo formatDate($election['election_date']); ?> | 
                    Time: <?php echo date('h:i A', strtotime($election['start_time'])); ?> - <?php echo date('h:i A', strtotime($election['end_time'])); ?>
                </small>
            </p>
        </div>
        
        <!-- Instructions -->
        <div class="alert alert-info no-print">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Instructions:</strong> 
            <?php if ($election['max_votes_per_voter'] > 1): ?>
                You may vote for up to <strong><?php echo $election['max_votes_per_voter']; ?></strong> candidate(s).
            <?php else: ?>
                You may vote for only <strong>ONE</strong> candidate.
            <?php endif; ?>
            Select your preferred candidate(s) and click the "Cast Vote" button.
        </div>
        
        <?php if ($hasVoted && $election['status'] !== 'completed'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-check-circle me-2"></i>
                <strong>You have already voted in this election.</strong> You cannot vote again.
            </div>
        <?php elseif ($election['status'] !== 'ongoing'): ?>
            <div class="alert alert-secondary">
                <i class="fas fa-clock me-2"></i>
                <strong>This election is not active.</strong> 
                <?php if ($election['status'] === 'upcoming'): ?>
                    Voting will begin on <?php echo formatDate($election['election_date']); ?> at <?php echo date('h:i A', strtotime($election['start_time'])); ?>.
                <?php elseif ($election['status'] === 'completed'): ?>
                    This election has been completed. <a href="results.php?id=<?php echo $electionId; ?>">View Results</a>
                <?php endif; ?>
            </div>
        <?php elseif (empty($candidates)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                No candidates have been nominated for this election yet.
            </div>
        <?php else: ?>
            <!-- Voting Form -->
            <form action="vote.php" method="POST" id="voteForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="election_id" value="<?php echo $electionId; ?>">
                
                <div class="row">
                    <?php foreach ($candidates as $index => $candidate): ?>
                        <div class="col-md-6 mb-3">
                            <div class="ballot-card candidate-option" onclick="toggleCandidate(<?php echo $candidate['id']; ?>)">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <?php if ($election['max_votes_per_voter'] > 1): ?>
                                            <input type="checkbox" class="vote-checkbox" name="candidates[]" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>">
                                        <?php else: ?>
                                            <input type="radio" class="vote-radio" name="candidates[]" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center">
                                            <?php if ($candidate['photo']): ?>
                                                <img src="<?php echo APP_URL . '/' . $candidate['photo']; ?>" class="rounded-circle me-3" width="60" height="60" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                                    <?php echo strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <h5 class="mb-0"><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></h5>
                                                <div class="text-muted small">
                                                    Member #: <?php echo $candidate['member_number']; ?>
                                                    <?php if ($candidate['party']): ?>
                                                        | Party: <?php echo htmlspecialchars($candidate['party']); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($candidate['slogan']): ?>
                                                    <div class="text-info small mt-1">
                                                        <i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($candidate['slogan']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($candidate['manifesto']): ?>
                                            <div class="mt-2 small text-muted">
                                                <strong>Manifesto:</strong> <?php echo htmlspecialchars(substr($candidate['manifesto'], 0, 150)); ?>...
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-4 no-print">
                    <button type="button" class="btn btn-secondary btn-lg me-3" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Ballot
                    </button>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-vote-yea me-2"></i>Cast Vote
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="text-center mt-5 pt-3 border-top no-print">
            <small class="text-muted">
                This is an official ballot paper for <?php echo htmlspecialchars($election['group_name']); ?>.
                Unauthorized reproduction is prohibited.
            </small>
        </div>
    </div>
    
    <script>
        const maxVotes = <?php echo $election['max_votes_per_voter']; ?>;
        let selectedCount = 0;
        
        <?php if ($election['max_votes_per_voter'] > 1): ?>
        function toggleCandidate(candidateId) {
            const checkbox = document.getElementById('candidate_' + candidateId);
            if (checkbox.checked) {
                checkbox.checked = false;
                selectedCount--;
            } else {
                if (selectedCount < maxVotes) {
                    checkbox.checked = true;
                    selectedCount++;
                } else {
                    Swal.fire({
                        title: 'Maximum Votes Reached',
                        text: `You can only vote for ${maxVotes} candidate(s).`,
                        icon: 'warning',
                        timer: 2000
                    });
                }
            }
        }
        
        document.querySelectorAll('.vote-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedCount++;
                } else {
                    selectedCount--;
                }
            });
        });
        <?php endif; ?>
        
        document.getElementById('voteForm')?.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('input[name="candidates[]"]:checked');
            if (selected.length === 0) {
                e.preventDefault();
                Swal.fire('No Selection', 'Please select at least one candidate to vote for.', 'warning');
            } else if (selected.length > maxVotes) {
                e.preventDefault();
                Swal.fire('Too Many Selections', `You can only vote for ${maxVotes} candidate(s).`, 'warning');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
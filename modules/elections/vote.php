<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];
$memberId = $_SESSION['member_id'] ?? null;

// If member doesn't have a member record, try to find it
if (!$memberId) {
    $stmt = db()->prepare("SELECT id FROM members WHERE user_id = ? AND group_id = ?");
    $stmt->execute([$_SESSION['user_id'], $groupId]);
    $member = $stmt->fetch();
    if ($member) {
        $memberId = $member['id'];
        $_SESSION['member_id'] = $memberId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/elections/index.php');
    }
    
    $electionId = (int)$_POST['election_id'];
    $candidateIds = $_POST['candidates'] ?? [];
    
    if (!is_array($candidateIds)) {
        $candidateIds = [$candidateIds];
    }
    
    try {
        db()->beginTransaction();
        
        // Get election details
        $stmt = db()->prepare("SELECT * FROM elections WHERE id = ? AND group_id = ?");
        $stmt->execute([$electionId, $groupId]);
        $election = $stmt->fetch();
        
        if (!$election) {
            throw new Exception('Election not found');
        }
        
        // Check if election is ongoing
        if ($election['status'] !== 'ongoing') {
            throw new Exception('This election is not currently active');
        }
        
        // Check if member is eligible to vote
        $stmt = db()->prepare("SELECT * FROM election_voters WHERE election_id = ? AND member_id = ?");
        $stmt->execute([$electionId, $memberId]);
        $voter = $stmt->fetch();
        
        if (!$voter) {
            throw new Exception('You are not eligible to vote in this election');
        }
        
        if ($voter['has_voted']) {
            throw new Exception('You have already voted in this election');
        }
        
        // Check number of votes
        if (count($candidateIds) > $election['max_votes_per_voter']) {
            throw new Exception('You have selected too many candidates');
        }
        
        if (count($candidateIds) == 0) {
            throw new Exception('Please select at least one candidate');
        }
        
        // Cast votes
        $voteHash = bin2hex(random_bytes(32));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        foreach ($candidateIds as $candidateId) {
            // Verify candidate belongs to this election
            $stmt = db()->prepare("SELECT id FROM candidates WHERE id = ? AND election_id = ?");
            $stmt->execute([$candidateId, $electionId]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid candidate selected');
            }
            
            // Record vote
            $stmt = db()->prepare("
                INSERT INTO votes (election_id, candidate_id, voter_id, vote_hash, voted_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$electionId, $candidateId, $memberId, $voteHash]);
            
            // Update candidate vote count
            $stmt = db()->prepare("UPDATE candidates SET votes = votes + 1 WHERE id = ?");
            $stmt->execute([$candidateId]);
        }
        
        // Mark voter as having voted
        $stmt = db()->prepare("
            UPDATE election_voters 
            SET has_voted = 1, voted_at = NOW(), ip_address = ?, user_agent = ?
            WHERE election_id = ? AND member_id = ?
        ");
        $stmt->execute([$ipAddress, $userAgent, $electionId, $memberId]);
        
        // Audit log
        auditLog('cast_vote', 'votes', 0, null, [
            'election_id' => $electionId,
            'voter_id' => $memberId,
            'candidates' => $candidateIds
        ]);
        
        db()->commit();
        
        setFlash('success', 'Your vote has been cast successfully!');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect("/modules/elections/index.php");
}

// GET request - redirect to ballot
if (isset($_GET['election_id'])) {
    redirect("/modules/elections/ballot.php?election_id=" . (int)$_GET['election_id']);
}

redirect('/modules/elections/index.php');
?>
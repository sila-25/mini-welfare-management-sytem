<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_elections');
Middleware::requireGroupAccess();

$electionId = (int)($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';

if ($confirm === 'yes') {
    try {
        db()->beginTransaction();
        
        // Get election details for audit
        $stmt = db()->prepare("SELECT * FROM elections WHERE id = ?");
        $stmt->execute([$electionId]);
        $election = $stmt->fetch();
        
        // Delete votes first
        $stmt = db()->prepare("DELETE FROM votes WHERE election_id = ?");
        $stmt->execute([$electionId]);
        
        // Delete candidates
        $stmt = db()->prepare("DELETE FROM candidates WHERE election_id = ?");
        $stmt->execute([$electionId]);
        
        // Delete voters
        $stmt = db()->prepare("DELETE FROM election_voters WHERE election_id = ?");
        $stmt->execute([$electionId]);
        
        // Delete election
        $stmt = db()->prepare("DELETE FROM elections WHERE id = ?");
        $stmt->execute([$electionId]);
        
        auditLog('delete_election', 'elections', $electionId, (array)$election, null);
        
        db()->commit();
        setFlash('success', 'Election deleted successfully');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error deleting election: ' . $e->getMessage());
    }
}

redirect('/modules/elections/index.php');
?>
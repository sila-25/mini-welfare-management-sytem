<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_elections');
Middleware::requireGroupAccess();

$candidateId = (int)($_GET['id'] ?? 0);
$electionId = (int)($_GET['election_id'] ?? 0);

$stmt = db()->prepare("UPDATE candidates SET status = 'approved' WHERE id = ? AND election_id = ?");
$stmt->execute([$candidateId, $electionId]);

setFlash('success', 'Candidate approved successfully');
redirect("/modules/elections/candidates.php?election_id={$electionId}");
?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_elections');
Middleware::requireGroupAccess();

$electionId = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare("UPDATE elections SET results_published = 1, results_published_at = NOW() WHERE id = ?");
$stmt->execute([$electionId]);

auditLog('publish_results', 'elections', $electionId);
setFlash('success', 'Election results published successfully');
redirect('/modules/elections/results.php?id=' . $electionId);
?>
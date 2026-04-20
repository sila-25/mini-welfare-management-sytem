<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/members/index.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token');
    redirect('/modules/members/index.php');
}

$groupId = $_SESSION['group_id'];
$memberId = (int)$_POST['member_id'];
$userId = $_SESSION['user_id'];

$stmt = db()->prepare("
    INSERT INTO member_notes (group_id, member_id, note_type, note, created_by)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $groupId,
    $memberId,
    $_POST['note_type'],
    $_POST['note'],
    $userId
]);

auditLog('add_member_note', 'member_notes', db()->lastInsertId(), null, ['member_id' => $memberId]);
setFlash('success', 'Note added successfully');

redirect('/modules/members/view.php?id=' . $memberId);
?>
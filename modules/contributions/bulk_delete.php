<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Set JSON response header
header('Content-Type: application/json');

// Check permission
if (!Permissions::can('record_contributions')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

Middleware::requireGroupAccess();

// Check CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$ids = $_POST['ids'] ?? [];
$groupId = $_SESSION['group_id'];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No contributions selected']);
    exit();
}

try {
    db()->beginTransaction();
    
    $successCount = 0;
    $failedCount = 0;
    $failedItems = [];
    
    foreach ($ids as $contributionId) {
        $contributionId = (int)$contributionId;
        
        // Get contribution details
        $stmt = db()->prepare("
            SELECT c.*, m.id as member_id
            FROM contributions c
            JOIN members m ON c.member_id = m.id
            WHERE c.id = ? AND c.group_id = ?
        ");
        $stmt->execute([$contributionId, $groupId]);
        $contribution = $stmt->fetch();
        
        if (!$contribution) {
            $failedCount++;
            $failedItems[] = "ID: {$contributionId} (Not found)";
            continue;
        }
        
        // Update member's total contributions
        $stmt = db()->prepare("
            UPDATE members 
            SET total_contributions = GREATEST(COALESCE(total_contributions, 0) - ?, 0)
            WHERE id = ?
        ");
        $stmt->execute([$contribution['amount'], $contribution['member_id']]);
        
        // Delete associated transaction if exists
        $stmt = db()->prepare("SELECT id, account_id FROM transactions WHERE reference_number = ? AND group_id = ?");
        $stmt->execute([$contribution['receipt_number'], $groupId]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Reverse account balance
            $stmt = db()->prepare("UPDATE accounts SET current_balance = current_balance - ? WHERE id = ?");
            $stmt->execute([$contribution['amount'], $transaction['account_id']]);
            
            // Delete transaction
            $stmt = db()->prepare("DELETE FROM transactions WHERE id = ?");
            $stmt->execute([$transaction['id']]);
        }
        
        // Delete contribution
        $stmt = db()->prepare("DELETE FROM contributions WHERE id = ? AND group_id = ?");
        $stmt->execute([$contributionId, $groupId]);
        
        // Audit log
        auditLog('bulk_delete_contribution', 'contributions', $contributionId, (array)$contribution, null);
        
        $successCount++;
    }
    
    db()->commit();
    
    $message = "$successCount contribution(s) deleted successfully.";
    if ($failedCount > 0) {
        $message .= " $failedCount failed: " . implode(', ', $failedItems);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted' => $successCount,
        'failed' => $failedCount
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_finances');
Middleware::requireGroupAccess();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/accounts/list.php');
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    setFlash('danger', 'Invalid security token. Please try again.');
    redirect('/modules/accounts/list.php');
}

$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

try {
    db()->beginTransaction();
    
    $accountType = $_POST['account_type'];
    $accountName = trim($_POST['account_name']);
    $openingBalance = (float)$_POST['opening_balance'];
    $status = $_POST['status'];
    $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
    
    // Validate required fields
    if (empty($accountType) || empty($accountName)) {
        throw new Exception('Account type and name are required');
    }
    
    // Prepare bank-specific fields
    $bankName = null;
    $accountNumber = null;
    $mobileNumber = null;
    $mobileProvider = null;
    
    if ($accountType === 'bank') {
        $bankName = !empty($_POST['bank_name']) ? trim($_POST['bank_name']) : null;
        $accountNumber = !empty($_POST['account_number']) ? trim($_POST['account_number']) : null;
    } elseif ($accountType === 'mobile_money') {
        $mobileNumber = !empty($_POST['mobile_number']) ? trim($_POST['mobile_number']) : null;
        $mobileProvider = $_POST['mobile_provider'] ?? 'M-PESA';
    }
    
    // Check if account with same name exists
    $stmt = db()->prepare("SELECT id FROM accounts WHERE group_id = ? AND account_name = ?");
    $stmt->execute([$groupId, $accountName]);
    if ($stmt->fetch()) {
        throw new Exception('An account with this name already exists');
    }
    
    // Insert account
    $stmt = db()->prepare("
        INSERT INTO accounts (
            group_id, account_name, account_type, bank_name, account_number,
            mobile_number, mobile_provider, opening_balance, current_balance,
            status, description, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $groupId,
        $accountName,
        $accountType,
        $bankName,
        $accountNumber,
        $mobileNumber,
        $mobileProvider,
        $openingBalance,
        $openingBalance, // current balance starts as opening balance
        $status,
        $description
    ]);
    
    $accountId = db()->lastInsertId();
    
    // If opening balance is not zero, create an opening balance transaction
    if ($openingBalance != 0) {
        $stmt = db()->prepare("
            INSERT INTO transactions (
                group_id, account_id, transaction_type, category, amount,
                description, transaction_date, reference_number, created_by
            ) VALUES (?, ?, 'income', 'opening_balance', ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $groupId,
            $accountId,
            $openingBalance,
            "Opening balance for {$accountName}",
            date('Y-m-d'),
            'OPENING-' . date('Ymd') . '-' . $accountId,
            $userId
        ]);
    }
    
    // Audit log
    auditLog('create_account', 'accounts', $accountId, null, [
        'account_name' => $accountName,
        'account_type' => $accountType,
        'opening_balance' => $openingBalance
    ]);
    
    db()->commit();
    
    setFlash('success', "Account '{$accountName}' created successfully with opening balance of " . formatCurrency($openingBalance));
    
} catch (Exception $e) {
    db()->rollback();
    error_log("Error creating account: " . $e->getMessage());
    setFlash('danger', 'Error: ' . $e->getMessage());
}

redirect('/modules/accounts/list.php');
?>
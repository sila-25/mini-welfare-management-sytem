<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];

// Get filter parameters
$account_type = $_GET['account_type'] ?? '';
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        a.account_name,
        CASE a.account_type
            WHEN 'bank' THEN 'Bank Account'
            WHEN 'cash' THEN 'Cash Account'
            WHEN 'mobile_money' THEN 'Mobile Money'
        END as account_type,
        a.bank_name,
        a.account_number,
        a.mobile_number,
        a.mobile_provider,
        a.opening_balance,
        a.current_balance,
        a.status,
        a.description,
        DATE_FORMAT(a.created_at, '%Y-%m-%d %H:%i') as created_date,
        COUNT(DISTINCT t.id) as transaction_count,
        SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE 0 END) as total_income,
        SUM(CASE WHEN t.transaction_type = 'expense' THEN t.amount ELSE 0 END) as total_expense
    FROM accounts a
    LEFT JOIN transactions t ON a.id = t.account_id AND t.group_id = a.group_id
    WHERE a.group_id = ?
";

$params = [$groupId];

if ($account_type) {
    $query .= " AND a.account_type = ?";
    $params[] = $account_type;
}
if ($status !== 'all') {
    $query .= " AND a.status = ?";
    $params[] = $status;
}
if ($search) {
    $query .= " AND (a.account_name LIKE ? OR a.bank_name LIKE ? OR a.account_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY a.id ORDER BY a.account_type, a.account_name";

$stmt = db()->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// Prepare CSV data
$csvData = [];
foreach ($accounts as $account) {
    $csvData[] = [
        'Account Name' => $account['account_name'],
        'Account Type' => $account['account_type'],
        'Bank Name' => $account['bank_name'] ?? 'N/A',
        'Account Number' => $account['account_number'] ?? 'N/A',
        'Mobile Number' => $account['mobile_number'] ?? 'N/A',
        'Mobile Provider' => $account['mobile_provider'] ?? 'N/A',
        'Opening Balance (KES)' => $account['opening_balance'],
        'Current Balance (KES)' => $account['current_balance'],
        'Status' => ucfirst($account['status']),
        'Transaction Count' => $account['transaction_count'],
        'Total Income (KES)' => $account['total_income'] ?? 0,
        'Total Expense (KES)' => $account['total_expense'] ?? 0,
        'Description' => $account['description'] ?? '',
        'Created Date' => $account['created_date']
    ];
}

$filename = 'accounts_export_' . date('Y-m-d') . '.csv';
exportToCSV($csvData, $filename);
?>
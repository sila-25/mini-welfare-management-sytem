<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];

// Get filter parameters
$member_id = $_GET['member_id'] ?? '';
$contribution_type = $_GET['contribution_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        c.receipt_number,
        CONCAT(m.first_name, ' ', m.last_name) as member_name,
        m.member_number,
        c.contribution_type,
        c.amount,
        c.payment_date,
        c.payment_method,
        c.reference_number,
        c.notes,
        c.created_at,
        CONCAT(u.first_name, ' ', u.last_name) as recorded_by
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    LEFT JOIN users u ON c.recorded_by = u.id
    WHERE c.group_id = ?
";

$params = [$groupId];

if ($member_id) {
    $query .= " AND c.member_id = ?";
    $params[] = $member_id;
}
if ($contribution_type) {
    $query .= " AND c.contribution_type = ?";
    $params[] = $contribution_type;
}
if ($date_from) {
    $query .= " AND c.payment_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND c.payment_date <= ?";
    $params[] = $date_to;
}
if ($search) {
    $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ? OR c.receipt_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY c.payment_date DESC";

$stmt = db()->prepare($query);
$stmt->execute($params);
$contributions = $stmt->fetchAll();

// Prepare CSV data
$csvData = [];
foreach ($contributions as $contribution) {
    $csvData[] = [
        'Receipt Number' => $contribution['receipt_number'],
        'Member Name' => $contribution['member_name'],
        'Member Number' => $contribution['member_number'],
        'Contribution Type' => ucfirst($contribution['contribution_type']),
        'Amount (KES)' => $contribution['amount'],
        'Payment Date' => $contribution['payment_date'],
        'Payment Method' => ucfirst(str_replace('_', ' ', $contribution['payment_method'])),
        'Reference Number' => $contribution['reference_number'] ?? 'N/A',
        'Notes' => $contribution['notes'] ?? '',
        'Recorded By' => $contribution['recorded_by'] ?? 'System',
        'Date Recorded' => $contribution['created_at']
    ];
}

$filename = 'contributions_export_' . date('Y-m-d') . '.csv';
exportToCSV($csvData, $filename);
?>
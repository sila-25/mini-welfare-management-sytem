<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$groupId = $_SESSION['group_id'];

// Get filtered members
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$gender = $_GET['gender'] ?? '';

$query = "SELECT 
            m.member_number,
            m.first_name,
            m.last_name,
            m.email,
            m.phone,
            m.id_number,
            m.gender,
            m.occupation,
            m.join_date,
            m.status,
            COALESCE(c.total_contributions, 0) as total_contributions
          FROM members m
          LEFT JOIN (
              SELECT member_id, SUM(amount) as total_contributions
              FROM contributions
              WHERE group_id = ?
              GROUP BY member_id
          ) c ON m.id = c.member_id
          WHERE m.group_id = ?";

$params = [$groupId, $groupId];

if ($status !== 'all') {
    $query .= " AND m.status = ?";
    $params[] = $status;
}

if ($gender) {
    $query .= " AND m.gender = ?";
    $params[] = $gender;
}

if ($search) {
    $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.member_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY m.created_at DESC";

$stmt = db()->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Prepare data for CSV
$csvData = [];
foreach ($members as $member) {
    $csvData[] = [
        'Member Number' => $member['member_number'],
        'First Name' => $member['first_name'],
        'Last Name' => $member['last_name'],
        'Email' => $member['email'],
        'Phone' => $member['phone'],
        'ID Number' => $member['id_number'],
        'Gender' => ucfirst($member['gender']),
        'Occupation' => $member['occupation'],
        'Join Date' => $member['join_date'],
        'Status' => ucfirst($member['status']),
        'Total Contributions' => $member['total_contributions']
    ];
}

$filename = 'members_export_' . date('Y-m-d') . '.csv';
exportToCSV($csvData, $filename);
?>
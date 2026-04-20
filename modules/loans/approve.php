<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Only chairperson and treasurer can approve
Permissions::check('approve_loans');
Middleware::requireGroupAccess();

$loanId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get loan details
$stmt = db()->prepare("
    SELECT l.*, m.first_name, m.last_name, m.member_number, m.phone, m.email,
           m.total_contributions
    FROM loans l
    JOIN members m ON l.member_id = m.id
    WHERE l.id = ? AND l.group_id = ?
");
$stmt->execute([$loanId, $groupId]);
$loan = $stmt->fetch();

if (!$loan) {
    setFlash('danger', 'Loan application not found');
    redirect('/modules/loans/index.php');
}

// Get guarantors
$stmt = db()->prepare("
    SELECT lg.*, m.first_name, m.last_name, m.member_number, m.total_contributions
    FROM loan_guarantors lg
    JOIN members m ON lg.guarantor_id = m.id
    WHERE lg.loan_id = ?
");
$stmt->execute([$loanId]);
$guarantors = $stmt->fetchAll();

// Get repayment schedule
$stmt = db()->prepare("
    SELECT * FROM loan_repayment_schedule
    WHERE loan_id = ?
    ORDER BY installment_number
");
$stmt->execute([$loanId]);
$schedule = $stmt->fetchAll();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/loans/approve.php?id={$loanId}");
    }
    
    $action = $_POST['action'];
    
    try {
        db()->beginTransaction();
        
        if ($action === 'approve') {
            $stmt = db()->prepare("
                UPDATE loans SET 
                    status = 'approved',
                    approval_date = CURDATE(),
                    approved_by = ?,
                    approved_by_name = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_name'], $loanId]);
            
            // Approve guarantors
            $stmt = db()->prepare("UPDATE loan_guarantors SET status = 'approved' WHERE loan_id = ?");
            $stmt->execute([$loanId]);
            
            $message = "Loan application approved successfully";
            $actionLog = 'approve_loan';
            
            // Send notification to member
            $subject = "Loan Application Approved - {$loan['loan_number']}";
            $body = "
                <h3>Loan Application Approved</h3>
                <p>Dear {$loan['first_name']} {$loan['last_name']},</p>
                <p>Your loan application for <strong>" . formatCurrency($loan['principal_amount']) . "</strong> has been approved.</p>
                <p>The loan will be disbursed to you shortly.</p>
                <p><strong>Loan Number:</strong> {$loan['loan_number']}</p>
                <p><strong>Total Repayable:</strong> " . formatCurrency($loan['total_repayable']) . "</p>
                <p><strong>Monthly Installment:</strong> " . formatCurrency($loan['total_repayable'] / $loan['duration_months']) . "</p>
            ";
            sendEmail($loan['email'], $subject, $body);
            
        } elseif ($action === 'reject') {
            $rejectionReason = trim($_POST['rejection_reason']);
            
            $stmt = db()->prepare("
                UPDATE loans SET 
                    status = 'rejected',
                    rejection_reason = ?,
                    approval_date = CURDATE(),
                    approved_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$rejectionReason, $_SESSION['user_id'], $loanId]);
            
            $message = "Loan application rejected";
            $actionLog = 'reject_loan';
            
            // Send notification to member
            $subject = "Loan Application Update - {$loan['loan_number']}";
            $body = "
                <h3>Loan Application Update</h3>
                <p>Dear {$loan['first_name']} {$loan['last_name']},</p>
                <p>Your loan application for <strong>" . formatCurrency($loan['principal_amount']) . "</strong> has been reviewed.</p>
                <p><strong>Status:</strong> Rejected</p>
                <p><strong>Reason:</strong> {$rejectionReason}</p>
                <p>Please contact the committee for more information.</p>
            ";
            sendEmail($loan['email'], $subject, $body);
        }
        
        auditLog($actionLog, 'loans', $loanId, (array)$loan, ['action' => $action]);
        
        db()->commit();
        setFlash('success', $message);
        redirect('/modules/loans/index.php');
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

$page_title = 'Review Loan Application';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <!-- Loan Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Loan Application Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Loan Number:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Member:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']); ?> \\
                                        <small>(<?php echo $loan['member_number']; ?>)</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['phone']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($loan['email']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Application Date:</strong></td>
                                    <td><?php echo formatDate($loan['application_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Contributions:</strong></td>
                                    <td><?php echo formatCurrency($loan['total_contributions']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Requested Amount:</strong></td>
                                    <td class="text-primary fw-bold"><?php echo formatCurrency($loan['principal_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Interest Rate:</strong></td>
                                    <td><?php echo $loan['interest_rate']; ?>% p.a.
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

$contributionId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get contribution details
$stmt = db()->prepare("
    SELECT c.*, 
           m.first_name, m.last_name, m.member_number, m.phone, m.email,
           u.first_name as recorded_by_name, u.last_name as recorded_by_last
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    LEFT JOIN users u ON c.recorded_by = u.id
    WHERE c.id = ? AND c.group_id = ?
");
$stmt->execute([$contributionId, $groupId]);
$contribution = $stmt->fetch();

if (!$contribution) {
    setFlash('danger', 'Contribution not found');
    redirect('/modules/contributions/index.php');
}

// Get group info
$stmt = db()->prepare("SELECT group_name, email, phone FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

$page_title = 'Contribution Receipt';
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Action Buttons -->
            <div class="mb-4">
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to List
                    </a>
                    <div>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Receipt
                        </button>
                        <button class="btn btn-success" onclick="sendEmailReceipt()">
                            <i class="fas fa-envelope me-2"></i>Email Receipt
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Receipt -->
            <div class="card" id="receiptCard">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="mb-0"><?php echo htmlspecialchars($group['group_name']); ?></h2>
                        <p class="text-muted">Welfare Management System</p>
                        <hr>
                        <h4>OFFICIAL RECEIPT</h4>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-6">
                            <strong>Receipt Number:</strong><br>
                            <h4 class="text-primary"><?php echo htmlspecialchars($contribution['receipt_number']); ?></h4>
                        </div>
                        <div class="col-6 text-end">
                            <strong>Date:</strong><br>
                            <?php echo formatDate($contribution['payment_date'], 'F d, Y'); ?>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <table class="table table-borderless">
                                <tr>
                                    <td width="30%"><strong>Member Name:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Member Number:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['member_number']); ?></td>
                                </tr>
                                <?php if ($contribution['phone']): ?>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['phone']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($contribution['email']): ?>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['email']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end">Amount (KES)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <?php 
                                            $typeLabels = [
                                                'monthly' => 'Monthly Contribution',
                                                'welfare' => 'Welfare Contribution',
                                                'special' => 'Special Contribution',
                                                'registration' => 'Registration Fee'
                                            ];
                                            echo $typeLabels[$contribution['contribution_type']] ?? ucfirst($contribution['contribution_type']);
                                            ?>
                                            <?php if ($contribution['notes']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($contribution['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold"><?php echo number_format($contribution['amount'], 2); ?></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th class="text-end">TOTAL:</th>
                                        <th class="text-end">KES <?php echo number_format($contribution['amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <table class="table table-sm">
                                <tr>
                                    <td width="30%"><strong>Payment Method:</strong></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $contribution['payment_method'])); ?></td>
                                </tr>
                                <?php if ($contribution['reference_number']): ?>
                                <tr>
                                    <td><strong>Reference Number:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['reference_number']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Recorded By:</strong></td>
                                    <td><?php echo htmlspecialchars($contribution['recorded_by_name'] . ' ' . ($contribution['recorded_by_last'] ?? '')); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date Recorded:</strong></td>
                                    <td><?php echo formatDateTime($contribution['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5">
                        <div class="border-top pt-3">
                            <p class="text-muted mb-0">
                                Thank you for your contribution!<br>
                                This is a computer-generated receipt and requires no signature.
                            </p>
                            <p class="text-muted mt-2">
                                <small>Generated on: <?php echo date('F d, Y H:i:s'); ?></small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style media="print">
    .btn, .topbar, .sidebar, .breadcrumb, .mb-4 .btn {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    #receiptCard {
        margin: 0 !important;
        padding: 0 !important;
    }
    @page {
        size: A4;
        margin: 1cm;
    }
</style>

<script>
function sendEmailReceipt() {
    Swal.fire({
        title: 'Send Receipt by Email',
        html: `
            <div class="text-left">
                <p>Send receipt to: <strong><?php echo htmlspecialchars($contribution['email'] ?? 'No email on file'); ?></strong></p>
                <?php if (!$contribution['email']): ?>
                    <div class="alert alert-warning">
                        This member does not have an email address on file.
                    </div>
                <?php endif; ?>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        confirmButtonText: 'Send Email',
        preConfirm: () => {
            $.ajax({
                url: 'send_receipt.php',
                method: 'POST',
                data: {
                    id: <?php echo $contributionId; ?>,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Receipt sent successfully!', 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to send email', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to send email', 'error');
                }
            });
        }
    });
}
</script>

<?php include_once '../../templates/footer.php'; ?>
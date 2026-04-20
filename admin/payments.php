<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

// Only super admin can access
if ($_SESSION['user_role'] !== 'super_admin') {
    setFlash('danger', 'Access denied. Super admin privileges required.');
    redirect('/dashboard/home.php');
}

Middleware::requireLogin();

$page_title = 'Payment Transactions';
$current_page = 'admin';

// Handle refund
if (isset($_GET['refund']) && isset($_GET['id'])) {
    $paymentId = (int)$_GET['id'];
    $confirm = $_GET['confirm'] ?? '';
    
    if ($confirm === 'yes') {
        try {
            $stmt = db()->prepare("UPDATE payment_transactions SET status = 'refunded' WHERE id = ?");
            $stmt->execute([$paymentId]);
            setFlash('success', 'Payment refunded successfully');
        } catch (Exception $e) {
            setFlash('danger', 'Error processing refund');
        }
        redirect('/admin/payments.php');
    }
}

// Get payments with filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$query = "SELECT pt.*, g.group_name, g.group_code, sp.plan_name
          FROM payment_transactions pt
          JOIN groups g ON pt.group_id = g.id
          JOIN subscriptions s ON pt.subscription_id = s.id
          JOIN subscription_plans sp ON s.plan = sp.plan_code
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (g.group_name LIKE ? OR g.group_code LIKE ? OR pt.transaction_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $query .= " AND pt.status = ?";
    $params[] = $status;
}
if ($date_from) {
    $query .= " AND pt.payment_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND pt.payment_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY pt.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get summary
$stmt = db()->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount
    FROM payment_transactions
");
$stmt->execute();
$summary = $stmt->fetch();

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-between mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-credit-card me-2"></i>Payment Transactions</h1>
            <p class="text-muted mt-2">View and manage all subscription payments</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Transactions</h6>
                    <h2 class="mb-0"><?php echo number_format($summary['total_transactions']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Revenue</h6>
                    <h2 class="mb-0"><?php echo formatCurrency($summary['total_amount']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Completed</h6>
                    <h2 class="mb-0"><?php echo formatCurrency($summary['completed_amount']); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Pending</h6>
                    <h2 class="mb-0"><?php echo formatCurrency($summary['pending_amount']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" placeholder="Search by group or transaction ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" placeholder="Date From" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" placeholder="Date To" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Transaction ID</th>
                            <th>Group</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['id']; ?></td>
                                <td><code><?php echo $payment['transaction_id']; ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['group_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo $payment['group_code']; ?></small>
                                </td>
                                <td><?php echo $payment['plan_name']; ?></td>
                                <td class="text-success fw-bold"><?php echo formatCurrency($payment['amount']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                <td><?php echo formatDateTime($payment['payment_date']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['status'] === 'completed'): ?>
                                        <a href="#" class="btn btn-sm btn-warning" onclick="refundPayment(<?php echo $payment['id']; ?>)">
                                            <i class="fas fa-undo"></i> Refund
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" class="btn btn-sm btn-info" onclick="viewReceipt(<?php echo $payment['id']; ?>)">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function refundPayment(id) {
    Swal.fire({
        title: 'Refund Payment?',
        text: 'This will refund the payment to the group. This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, refund'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `payments.php?refund=yes&id=${id}&confirm=yes`;
        }
    });
}

function viewReceipt(id) {
    Swal.fire({
        title: 'Payment Receipt',
        html: '<div class="text-center"><i class="fas fa-receipt fa-4x text-primary mb-3"></i><p>Receipt details would be displayed here</p></div>',
        icon: 'info'
    });
}
</script>

<?php include_once '../templates/footer.php'; ?>
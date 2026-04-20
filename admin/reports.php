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

$page_title = 'System Reports';
$current_page = 'admin';

$report_type = $_GET['type'] ?? 'revenue';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

// Get report data based on type
$reportData = [];
$chartLabels = [];
$chartValues = [];

if ($report_type === 'revenue') {
    $stmt = db()->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as revenue,
            COUNT(*) as transactions
        FROM payment_transactions
        WHERE status = 'completed' AND payment_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $reportData = $stmt->fetchAll();
    
    foreach ($reportData as $data) {
        $chartLabels[] = date('M Y', strtotime($data['month'] . '-01'));
        $chartValues[] = $data['revenue'];
    }
    
} elseif ($report_type === 'groups') {
    $stmt = db()->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_groups,
            SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_groups
        FROM groups
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $reportData = $stmt->fetchAll();
    
    foreach ($reportData as $data) {
        $chartLabels[] = date('M Y', strtotime($data['month'] . '-01'));
        $chartValues[] = $data['new_groups'];
    }
    
} elseif ($report_type === 'subscriptions') {
    $stmt = db()->prepare("
        SELECT 
            subscription_plan,
            COUNT(*) as count,
            SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active
        FROM groups
        GROUP BY subscription_plan
    ");
    $stmt->execute();
    $reportData = $stmt->fetchAll();
    
    foreach ($reportData as $data) {
        $chartLabels[] = ucfirst($data['subscription_plan']);
        $chartValues[] = $data['count'];
    }
}

// Get summary statistics
$stmt = db()->prepare("
    SELECT 
        (SELECT COUNT(*) FROM groups) as total_groups,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM payment_transactions WHERE status = 'completed') as total_payments,
        (SELECT SUM(amount) FROM payment_transactions WHERE status = 'completed') as total_revenue,
        (SELECT SUM(amount) FROM payment_transactions WHERE status = 'completed' AND payment_date BETWEEN ? AND ?) as period_revenue,
        (SELECT COUNT(*) FROM payment_transactions WHERE status = 'completed' AND payment_date BETWEEN ? AND ?) as period_transactions
");
$stmt->execute([$date_from, $date_to, $date_from, $date_to]);
$summary = $stmt->fetch();

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><i class="fas fa-chart-bar me-2"></i>System Reports</h1>
            <p class="text-muted mt-2">Generate and view system-wide reports</p>
        </div>
        <button class="btn btn-success" onclick="exportReport()">
            <i class="fas fa-download me-2"></i>Export Report
        </button>
    </div>

    <!-- Report Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                        <option value="groups" <?php echo $report_type === 'groups' ? 'selected' : ''; ?>>Groups Report</option>
                        <option value="subscriptions" <?php echo $report_type === 'subscriptions' ? 'selected' : ''; ?>>Subscriptions Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Period Revenue</h6>
                    <h3><?php echo formatCurrency($summary['period_revenue'] ?? 0); ?></h3>
                    <small><?php echo formatDate($date_from); ?> - <?php echo formatDate($date_to); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Period Transactions</h6>
                    <h3><?php echo number_format($summary['period_transactions'] ?? 0); ?></h3>
                    <small>Completed payments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Groups</h6>
                    <h3><?php echo number_format($summary['total_groups']); ?></h3>
                    <small>Registered groups</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Users</h6>
                    <h3><?php echo number_format($summary['total_users']); ?></h3>
                    <small>System users</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-line me-2"></i>
                <?php echo ucfirst($report_type); ?> Report Chart
            </h5>
        </div>
        <div class="card-body">
            <div class="chart-wrapper" style="height: 400px;">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Report Data</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php if ($report_type === 'revenue'): ?>
                                <th>Month</th>
                                <th>Revenue</th>
                                <th>Transactions</th>
                            <?php elseif ($report_type === 'groups'): ?>
                                <th>Month</th>
                                <th>New Groups</th>
                                <th>Active Groups</th>
                            <?php elseif ($report_type === 'subscriptions'): ?>
                                <th>Plan</th>
                                <th>Total Groups</th>
                                <th>Active Subscriptions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $data): ?>
                            <tr>
                                <?php if ($report_type === 'revenue'): ?>
                                    <td><?php echo date('F Y', strtotime($data['month'] . '-01')); ?></td>
                                    <td class="text-success fw-bold"><?php echo formatCurrency($data['revenue']); ?></td>
                                    <td><?php echo number_format($data['transactions']); ?></td>
                                <?php elseif ($report_type === 'groups'): ?>
                                    <td><?php echo date('F Y', strtotime($data['month'] . '-01')); ?></td>
                                    <td><?php echo number_format($data['new_groups']); ?></td>
                                    <td><?php echo number_format($data['active_groups']); ?></td>
                                <?php elseif ($report_type === 'subscriptions'): ?>
                                    <td class="fw-bold"><?php echo ucfirst($data['subscription_plan']); ?></td>
                                    <td><?php echo number_format($data['count']); ?></td>
                                    <td><?php echo number_format($data['active']); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('reportChart').getContext('2d');
let chartType = 'bar';
<?php if ($report_type === 'revenue'): ?>
chartType = 'line';
<?php endif; ?>

new Chart(ctx, {
    type: chartType,
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: '<?php echo ucfirst($report_type); ?>',
            data: <?php echo json_encode($chartValues); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.5)',
            borderColor: '#667eea',
            borderWidth: 2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (<?php echo $report_type === 'revenue' ? 'true' : 'false'; ?>) {
                            label += 'KES ' + context.raw.toLocaleString();
                        } else {
                            label += context.raw;
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: '<?php echo $report_type === 'revenue' ? 'Amount (KES)' : 'Count'; ?>'
                }
            }
        }
    }
});

function exportReport() {
    window.location.href = 'export_report.php?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>';
}
</script>

<?php include_once '../templates/footer.php'; ?>
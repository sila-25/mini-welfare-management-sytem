<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_finances');
Middleware::requireGroupAccess();

$page_title = 'Investment Performance Report';
$groupId = $_SESSION['group_id'];

// Get all investments
$stmt = db()->prepare("
    SELECT i.*, 
           COUNT(DISTINCT it.id) as transaction_count,
           SUM(CASE WHEN it.transaction_type = 'dividend' THEN it.amount ELSE 0 END) as total_dividends,
           SUM(CASE WHEN it.transaction_type = 'sale' THEN it.amount ELSE 0 END) as total_sales
    FROM investments i
    LEFT JOIN investment_transactions it ON i.id = it.investment_id
    WHERE i.group_id = ?
    GROUP BY i.id
    ORDER BY (i.current_value - i.amount_invested) DESC
");
$stmt->execute([$groupId]);
$investments = $stmt->fetchAll();

// Calculate portfolio summary
$totalInvested = 0;
$totalCurrentValue = 0;
$totalDividends = 0;
$bestPerformer = null;
$worstPerformer = null;

foreach ($investments as $investment) {
    $totalInvested += $investment['amount_invested'];
    $totalCurrentValue += $investment['current_value'];
    $totalDividends += $investment['total_dividends'];
    
    $roi = $investment['amount_invested'] > 0 ? 
        (($investment['current_value'] - $investment['amount_invested']) / $investment['amount_invested'] * 100) : 0;
    
    if ($bestPerformer === null || $roi > $bestPerformer['roi']) {
        $bestPerformer = ['name' => $investment['investment_name'], 'roi' => $roi, 'gain' => $investment['current_value'] - $investment['amount_invested']];
    }
    if ($worstPerformer === null || $roi < $worstPerformer['roi']) {
        $worstPerformer = ['name' => $investment['investment_name'], 'roi' => $roi, 'loss' => $investment['current_value'] - $investment['amount_invested']];
    }
}

$totalGainLoss = $totalCurrentValue - $totalInvested;
$totalROI = $totalInvested > 0 ? ($totalGainLoss / $totalInvested * 100) : 0;

include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-chart-line me-2"></i>Investment Performance Report
                    </h1>
                    <p class="text-muted mt-2">Comprehensive analysis of all group investments</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Portfolio Value</h6>
                            <h2 class="mb-0"><?php echo formatCurrency($totalCurrentValue); ?></h2>
                            <small><?php echo number_format(count($investments)); ?> investments</small>
                        </div>
                        <i class="fas fa-chart-pie fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Invested</h6>
                            <h2 class="mb-0"><?php echo formatCurrency($totalInvested); ?></h2>
                            <small>Initial capital</small>
                        </div>
                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Gain/Loss</h6>
                            <h2 class="mb-0 <?php echo $totalGainLoss >= 0 ? 'text-white' : 'text-danger'; ?>">
                                <?php echo ($totalGainLoss >= 0 ? '+' : '') . formatCurrency($totalGainLoss); ?>
                            </h2>
                            <small>Overall profit/loss</small>
                        </div>
                        <i class="fas fa-arrow-trend-up fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total ROI</h6>
                            <h2 class="mb-0 <?php echo $totalROI >= 0 ? 'text-white' : 'text-danger'; ?>">
                                <?php echo ($totalROI >= 0 ? '+' : '') . number_format($totalROI, 1); ?>%
                            </h2>
                            <small>Return on Investment</small>
                        </div>
                        <i class="fas fa-percent fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Best and Worst Performers -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Best Performer</h5>
                </div>
                <div class="card-body text-center">
                    <h3 class="mb-2"><?php echo htmlspecialchars($bestPerformer['name']); ?></h3>
                    <p class="mb-1">ROI: <strong class="text-success">+<?php echo number_format($bestPerformer['roi'], 1); ?>%</strong></p>
                    <p class="mb-0">Gain: <strong class="text-success"><?php echo formatCurrency($bestPerformer['gain']); ?></strong></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Needs Improvement</h5>
                </div>
                <div class="card-body text-center">
                    <h3 class="mb-2"><?php echo htmlspecialchars($worstPerformer['name']); ?></h3>
                    <p class="mb-1">ROI: <strong class="text-danger"><?php echo number_format($worstPerformer['roi'], 1); ?>%</strong></p>
                    <p class="mb-0">Loss: <strong class="text-danger"><?php echo formatCurrency($worstPerformer['loss']); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Investment Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Detailed Investment Analysis</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Investment Name</th>
                            <th>Type</th>
                            <th>Invested</th>
                            <th>Current Value</th>
                            <th>Gain/Loss</th>
                            <th>ROI</th>
                            <th>Dividends</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($investments as $investment):
                            $gainLoss = $investment['current_value'] - $investment['amount_invested'];
                            $roi = $investment['amount_invested'] > 0 ? ($gainLoss / $investment['amount_invested'] * 100) : 0;
                        ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($investment['investment_name']); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst($investment['investment_type']); ?>
                                </span>
                            </td>
                            <td><?php echo formatCurrency($investment['amount_invested']); ?></td>
                            <td><?php echo formatCurrency($investment['current_value']); ?></td>
                            <td class="<?php echo $gainLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($gainLoss >= 0 ? '+' : '') . formatCurrency($gainLoss); ?>
                            </td>
                            <td class="<?php echo $roi >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($roi >= 0 ? '+' : '') . number_format($roi, 1); ?>%
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar bg-<?php echo $roi >= 0 ? 'success' : 'danger'; ?>" 
                                         role="progressbar" style="width: <?php echo min(abs($roi), 100); ?>%">
                                    </div>
                                </div>
                            </td>
                            <td><?php echo formatCurrency($investment['total_dividends'] ?? 0); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $investment['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($investment['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2">TOTAL</td>
                            <td><?php echo formatCurrency($totalInvested); ?></td>
                            <td><?php echo formatCurrency($totalCurrentValue); ?></td>
                            <td class="<?php echo $totalGainLoss >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($totalGainLoss >= 0 ? '+' : '') . formatCurrency($totalGainLoss); ?>
                            </td>
                            <td class="<?php echo $totalROI >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($totalROI >= 0 ? '+' : '') . number_format($totalROI, 1); ?>%
                            </td>
                            <td><?php echo formatCurrency($totalDividends); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Investment Performance Chart</h5>
        </div>
        <div class="card-body">
            <canvas id="performanceChart" height="100"></canvas>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Recommendations</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Top Performer:</strong> Consider increasing allocation to 
                        <?php echo htmlspecialchars($bestPerformer['name']); ?> if possible.
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Review Needed:</strong> Evaluate the performance of 
                        <?php echo htmlspecialchars($worstPerformer['name']); ?> and consider restructuring.
                    </div>
                </div>
            </div>
            <?php if ($totalGainLoss < 0): ?>
                <div class="alert alert-danger mt-2">
                    <i class="fas fa-chart-line me-2"></i>
                    <strong>Portfolio Alert:</strong> Overall portfolio is underperforming. Consider diversifying or 
                    consulting a financial advisor.
                </div>
            <?php elseif ($totalROI > 20): ?>
                <div class="alert alert-success mt-2">
                    <i class="fas fa-chart-line me-2"></i>
                    <strong>Excellent Performance:</strong> Portfolio is performing exceptionally well. Consider 
                    locking in some profits if applicable.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($investments as $inv) echo "'" . addslashes($inv['investment_name']) . "',"; ?>],
        datasets: [
            {
                label: 'Amount Invested',
                data: [<?php foreach ($investments as $inv) echo $inv['amount_invested'] . ','; ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Current Value',
                data: [<?php foreach ($investments as $inv) echo $inv['current_value'] . ','; ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Amount (KES)'
                },
                ticks: {
                    callback: function(value) {
                        return 'KES ' + value.toLocaleString();
                    }
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Investments'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.dataset.label}: KES ${context.raw.toLocaleString()}`;
                    }
                }
            }
        }
    }
});

function exportReport() {
    window.location.href = 'export_performance.php';
}
</script>

<style>
@media print {
    .btn, .topbar, .sidebar, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
.stat-card {
    border: none;
}
.progress {
    border-radius: 5px;
}
</style>

<?php include_once '../../templates/footer.php'; ?>
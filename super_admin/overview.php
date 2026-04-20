<?php
// Get monthly group creation data
$monthlyLabels = [];
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM groups WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthlyData[] = $stmt->fetch()['count'];
}

// Get plan distribution
$stmt = db()->prepare("
    SELECT subscription_plan, COUNT(*) as count 
    FROM groups 
    GROUP BY subscription_plan
");
$stmt->execute();
$planData = [];
while ($row = $stmt->fetch()) {
    $planData[$row['subscription_plan']] = $row['count'];
}
?>

<div class="overview-section">
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Groups Created (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="growthChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-success"></i>Subscription Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="planChart" height="250"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Monthly:</span>
                            <strong><?php echo number_format($planData['monthly'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Quarterly:</span>
                            <strong><?php echo number_format($planData['quarterly'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Biannual:</span>
                            <strong><?php echo number_format($planData['biannual'] ?? 0); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Annual:</span>
                            <strong><?php echo number_format($planData['annual'] ?? 0); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3 pb-0">
            <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="?section=groups" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-building me-2"></i>Manage Groups
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="?section=system-admins" class="btn btn-outline-success w-100 py-3">
                        <i class="fas fa-user-plus me-2"></i>Add System Admin
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="?section=plans" class="btn btn-outline-info w-100 py-3">
                        <i class="fas fa-tags me-2"></i>Manage Plans
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="?section=settings" class="btn btn-outline-secondary w-100 py-3">
                        <i class="fas fa-cog me-2"></i>Platform Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthlyLabels); ?>,
        datasets: [{
            label: 'New Groups',
            data: <?php echo json_encode($monthlyData); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102,126,234,0.1)',
            borderWidth: 2,
            fill: true
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('planChart'), {
    type: 'doughnut',
    data: {
        labels: ['Monthly', 'Quarterly', 'Biannual', 'Annual'],
        datasets: [{
            data: [<?php echo ($planData['monthly'] ?? 0) . ',' . ($planData['quarterly'] ?? 0) . ',' . ($planData['biannual'] ?? 0) . ',' . ($planData['annual'] ?? 0); ?>],
            backgroundColor: ['#667eea', '#48bb78', '#ed8936', '#f56565'],
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
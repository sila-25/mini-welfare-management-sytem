<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/login.php');
    exit();
}

$page_title = 'Dashboard';
$current_page = 'dashboard';

$groupId = $_SESSION['group_id'] ?? 1;

// Get dashboard statistics
$stats = getDashboardStats($groupId);

// Get upcoming meetings
$upcomingMeetings = [];
try {
    $stmt = db()->prepare("
        SELECT * FROM meetings 
        WHERE group_id = ? AND meeting_date >= CURDATE() AND status = 'scheduled'
        ORDER BY meeting_date ASC, start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$groupId]);
    $upcomingMeetings = $stmt->fetchAll();
} catch (Exception $e) {
    $upcomingMeetings = [];
}

// Get recent contributions
$recentContributions = [];
try {
    $stmt = db()->prepare("
        SELECT c.*, m.first_name, m.last_name, m.member_number
        FROM contributions c
        JOIN members m ON c.member_id = m.id
        WHERE c.group_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$groupId]);
    $recentContributions = $stmt->fetchAll();
} catch (Exception $e) {
    $recentContributions = [];
}

include_once '../templates/header.php';
include_once '../templates/sidebar.php';
include_once '../templates/topbar.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-gradient-primary text-white border-0">
                    <div class="card-body py-4">
                        <h2 class="mb-2">Welcome to <?php echo htmlspecialchars($_SESSION['group_name'] ?? 'Your Group'); ?>!</h2>
                        <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Member'); ?>!</p>
                        <p class="mb-0 mt-2 small opacity-75">
                            <i class="fas fa-building me-2"></i>Group Code: <?php echo htmlspecialchars($_SESSION['group_code'] ?? 'N/A'); ?>
                            <i class="fas fa-calendar-alt ms-3 me-2"></i><?php echo date('l, F j, Y'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards Row -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                        <h3 class="mb-1"><?php echo number_format($stats['total_members']); ?></h3>
                        <p class="text-muted mb-0">Total Members</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-coins fa-2x text-success"></i>
                        </div>
                        <h3 class="mb-1"><?php echo formatCurrency($stats['total_contributions']); ?></h3>
                        <p class="text-muted mb-0">Total Contributions</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-hand-holding-usd fa-2x text-warning"></i>
                        </div>
                        <h3 class="mb-1"><?php echo number_format($stats['active_loans']); ?></h3>
                        <p class="text-muted mb-0">Active Loans</p>
                        <small class="text-danger"><?php echo formatCurrency($stats['outstanding_loans']); ?> outstanding</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex p-3 mb-3">
                            <i class="fas fa-calendar-alt fa-2x text-info"></i>
                        </div>
                        <h3 class="mb-1"><?php echo count($upcomingMeetings); ?></h3>
                        <p class="text-muted mb-0">Upcoming Meetings</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Recent Contributions Table -->
            <div class="col-lg-7 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>Recent Contributions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentContributions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No contributions recorded yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Receipt #</th>
                                            <th>Member</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentContributions as $contribution): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($contribution['receipt_number'] ?? 'N/A'); ?></code></td>
                                                <td><?php echo htmlspecialchars($contribution['first_name'] . ' ' . $contribution['last_name']); ?></td>
                                                <td class="text-success fw-bold"><?php echo formatCurrency($contribution['amount']); ?></td>
                                                <td><?php echo formatDate($contribution['payment_date']); ?></td>
                                                <td><span class="badge bg-info"><?php echo ucfirst($contribution['contribution_type'] ?? 'monthly'); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-0 pt-0">
                        <a href="<?php echo APP_URL; ?>/modules/contributions/list.php" class="btn btn-sm btn-link ps-0">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar Content -->
            <div class="col-lg-5 mb-4">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?php echo APP_URL; ?>/modules/members/add.php" class="btn btn-outline-primary w-100 py-2">
                                    <i class="fas fa-user-plus me-2"></i>Add Member
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo APP_URL; ?>/modules/contributions/record.php" class="btn btn-outline-success w-100 py-2">
                                    <i class="fas fa-coins me-2"></i>Record Payment
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo APP_URL; ?>/modules/loans/apply.php" class="btn btn-outline-info w-100 py-2">
                                    <i class="fas fa-hand-holding-usd me-2"></i>Apply Loan
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo APP_URL; ?>/modules/meetings/create.php" class="btn btn-outline-warning w-100 py-2">
                                    <i class="fas fa-calendar-plus me-2"></i>Schedule Meeting
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Meetings -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h5 class="mb-0"><i class="fas fa-calendar-week me-2 text-info"></i>Upcoming Meetings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingMeetings)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No upcoming meetings</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingMeetings as $meeting): ?>
                                <div class="d-flex align-items-start mb-3 pb-2 border-bottom">
                                    <div class="flex-shrink-0">
                                        <div class="bg-info bg-opacity-10 rounded p-2 text-center me-3" style="min-width: 60px;">
                                            <div class="small text-info fw-bold"><?php echo date('M', strtotime($meeting['meeting_date'])); ?></div>
                                            <div class="h5 mb-0 fw-bold text-info"><?php echo date('d', strtotime($meeting['meeting_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($meeting['title']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($meeting['start_time'])); ?>
                                            <i class="fas fa-map-marker-alt ms-2 me-1"></i><?php echo htmlspecialchars(substr($meeting['venue'] ?? 'TBD', 0, 30)); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="text-center mt-2">
                            <a href="<?php echo APP_URL; ?>/modules/meetings/index.php" class="btn btn-sm btn-outline-primary">View All Meetings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
.btn-outline-primary:hover, .btn-outline-success:hover, .btn-outline-info:hover, .btn-outline-warning:hover {
    transform: translateY(-1px);
}
</style>

<?php include_once '../templates/footer.php'; ?>
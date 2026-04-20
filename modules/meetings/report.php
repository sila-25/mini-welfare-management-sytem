<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_meetings');
Middleware::requireGroupAccess();

$meetingId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get meeting details
$stmt = db()->prepare("
    SELECT m.*, 
           u1.first_name as creator_first, u1.last_name as creator_last,
           u2.first_name as recorded_first, u2.last_name as recorded_last,
           u3.first_name as approved_first, u3.last_name as approved_last
    FROM meetings m
    LEFT JOIN users u1 ON m.created_by = u1.id
    LEFT JOIN users u2 ON m.recorded_by = u2.id
    LEFT JOIN users u3 ON m.minutes_approved_by = u3.id
    WHERE m.id = ? AND m.group_id = ?
");
$stmt->execute([$meetingId, $groupId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    setFlash('danger', 'Meeting not found');
    redirect('/modules/meetings/index.php');
}

// Get attendance details
$stmt = db()->prepare("
    SELECT ma.*, m.first_name, m.last_name, m.member_number
    FROM meeting_attendance ma
    JOIN members m ON ma.member_id = m.id
    WHERE ma.meeting_id = ?
    ORDER BY ma.status DESC, m.first_name
");
$stmt->execute([$meetingId]);
$attendance = $stmt->fetchAll();

// Get agenda items
$stmt = db()->prepare("
    SELECT * FROM meeting_agenda_items
    WHERE meeting_id = ?
    ORDER BY item_order
");
$stmt->execute([$meetingId]);
$agendaItems = $stmt->fetchAll();

// Get minutes
$stmt = db()->prepare("
    SELECT * FROM meeting_minutes
    WHERE meeting_id = ?
    ORDER BY created_at
");
$stmt->execute([$meetingId]);
$minutes = $stmt->fetchAll();

// Get decisions
$stmt = db()->prepare("
    SELECT md.*, m.first_name, m.last_name
    FROM meeting_decisions md
    LEFT JOIN members m ON md.responsible_person = m.id
    WHERE md.meeting_id = ?
    ORDER BY md.created_at
");
$stmt->execute([$meetingId]);
$decisions = $stmt->fetchAll();

$presentCount = count(array_filter($attendance, function($a) { return $a['status'] === 'present'; }));
$excusedCount = count(array_filter($attendance, function($a) { return $a['status'] === 'excused'; }));
$absentCount = count(array_filter($attendance, function($a) { return $a['status'] === 'absent'; }));
$attendanceRate = count($attendance) > 0 ? round(($presentCount / count($attendance)) * 100, 1) : 0;

$page_title = 'Meeting Report - ' . $meeting['title'];
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Meetings</a></li>
                            <li class="breadcrumb-item"><a href="details.php?id=<?php echo $meetingId; ?>">Meeting Details</a></li>
                            <li class="breadcrumb-item active">Report</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Meeting Report</h1>
                    <p class="text-muted"><?php echo htmlspecialchars($meeting['title']); ?> - <?php echo formatDate($meeting['meeting_date']); ?></p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export PDF
                    </button>
                    <a href="details.php?id=<?php echo $meetingId; ?>" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
            
            <!-- Report Content -->
            <div id="reportContent">
                <!-- Header -->
                <div class="text-center mb-5">
                    <h2><?php echo htmlspecialchars($meeting['title']); ?></h2>
                    <h4>Meeting Report</h4>
                    <p class="mb-0">Date: <?php echo formatDate($meeting['meeting_date'], 'F d, Y'); ?></p>
                    <p>Venue: <?php echo htmlspecialchars($meeting['venue']); ?></p>
                </div>
                
                <!-- Meeting Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Meeting Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td width="40%"><strong>Meeting Type:</strong></td>
                                        <td><?php echo ucfirst($meeting['meeting_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Date:</strong></td>
                                        <td><?php echo formatDate($meeting['meeting_date'], 'l, F d, Y'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Time:</strong></td>
                                        <td><?php echo date('h:i A', strtotime($meeting['start_time'])); ?> - <?php echo date('h:i A', strtotime($meeting['end_time'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Venue:</strong></td>
                                        <td><?php echo htmlspecialchars($meeting['venue']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td width="40%"><strong>Status:</strong></td>
                                        <td><span class="badge bg-<?php echo $meeting['status'] === 'completed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($meeting['status']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Quorum:</strong></td>
                                        <td><?php echo $meeting['quorum']; ?> members</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Present:</strong></td>
                                        <td><?php echo $presentCount; ?> members</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Attendance Rate:</strong></td>
                                        <td><?php echo $attendanceRate; ?>%</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($meeting['description']): ?>
                            <div class="mt-3">
                                <strong>Description:</strong>
                                <p><?php echo nl2br(htmlspecialchars($meeting['description'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attendance Report -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Attendance Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded">
                                    <h3 class="text-success"><?php echo $presentCount; ?></h3>
                                    <small>Present</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded">
                                    <h3 class="text-warning"><?php echo $excusedCount; ?></h3>
                                    <small>Excused</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 border rounded">
                                    <h3 class="text-danger"><?php echo $absentCount; ?></h3>
                                    <small>Absent</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member Name</th>
                                        <th>Member Number</th>
                                        <th>Status</th>
                                        <th>Arrival Time</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $index => $attend): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($attend['first_name'] . ' ' . $attend['last_name']); ?></td>
                                            <td><?php echo $attend['member_number']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $attend['status'] === 'present' ? 'success' : ($attend['status'] === 'excused' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($attend['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $attend['arrival_time']; ?></td>
                                            <td><?php echo htmlspecialchars($attend['notes']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Agenda and Minutes -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Agenda and Minutes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($agendaItems)): ?>
                            <h6>Agenda Items</h6>
                            <ol>
                                <?php foreach ($agendaItems as $item): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                        <?php if ($item['presenter']): ?>
                                            <br><small class="text-muted">Presenter: <?php echo htmlspecialchars($item['presenter']); ?></small>
                                        <?php endif; ?>
                                        <?php if ($item['description']): ?>
                                            <br><small><?php echo nl2br(htmlspecialchars($item['description'])); ?></small>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                        
                        <?php if (!empty($minutes)): ?>
                            <hr>
                            <h6>Meeting Minutes</h6>
                            <?php foreach ($minutes as $minute): ?>
                                <div class="mb-3">
                                    <?php if ($minute['section']): ?>
                                        <strong><?php echo htmlspecialchars($minute['section']); ?></strong>
                                    <?php endif; ?>
                                    <p><?php echo nl2br(htmlspecialchars($minute['content'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Decisions and Action Items -->
                <?php if (!empty($decisions)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Decisions and Action Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Decision</th>
                                        <th>Action Items</th>
                                        <th>Responsible Person</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($decisions as $decision): ?>
                                        <tr>
                                            <td><?php echo nl2br(htmlspecialchars($decision['decision'])); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($decision['action_items'])); ?></td>
                                            <td><?php echo htmlspecialchars($decision['first_name'] . ' ' . $decision['last_name']); ?></td>
                                            <td><?php echo $decision['deadline'] ? formatDate($decision['deadline']) : 'Not set'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $decision['status'] === 'completed' ? 'success' : ($decision['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $decision['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Footer -->
                <div class="text-center mt-5 pt-3 border-top">
                    <small class="text-muted">
                        Report generated on <?php echo date('F d, Y \a\t h:i A'); ?><br>
                        <?php echo APP_NAME; ?> - Meeting Management System
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .topbar, .sidebar, .no-print, nav, .card-header .btn {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<script>
function exportReport() {
    window.location.href = 'export_report.php?id=<?php echo $meetingId; ?>';
}
</script>

<?php include_once '../../templates/footer.php'; ?>
<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_meetings');
Middleware::requireGroupAccess();

$meetingId = (int)($_GET['meeting_id'] ?? 0);
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get meeting details
$stmt = db()->prepare("
    SELECT m.*, u.first_name as creator_first, u.last_name as creator_last
    FROM meetings m
    LEFT JOIN users u ON m.created_by = u.id
    WHERE m.id = ? AND m.group_id = ?
");
$stmt->execute([$meetingId, $groupId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    setFlash('danger', 'Meeting not found');
    redirect('/modules/meetings/index.php');
}

// Get all active members
$stmt = db()->prepare("
    SELECT id, first_name, last_name, member_number, email, phone
    FROM members 
    WHERE group_id = ? AND status = 'active'
    ORDER BY first_name
");
$stmt->execute([$groupId]);
$allMembers = $stmt->fetchAll();

// Get current attendance
$stmt = db()->prepare("
    SELECT ma.*, m.first_name, m.last_name, m.member_number
    FROM meeting_attendance ma
    JOIN members m ON ma.member_id = m.id
    WHERE ma.meeting_id = ?
");
$stmt->execute([$meetingId]);
$existingAttendance = [];
while ($row = $stmt->fetch()) {
    $existingAttendance[$row['member_id']] = $row;
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect("/modules/meetings/attendance.php?meeting_id={$meetingId}");
    }
    
    try {
        db()->beginTransaction();
        
        $presentCount = 0;
        $attendanceStmt = db()->prepare("
            INSERT INTO meeting_attendance (meeting_id, member_id, status, arrival_time, notes, marked_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            status = VALUES(status), arrival_time = VALUES(arrival_time), notes = VALUES(notes), marked_by = VALUES(marked_by)
        ");
        
        foreach ($allMembers as $member) {
            $status = isset($_POST["status_{$member['id']}"]) ? $_POST["status_{$member['id']}"] : 'absent';
            $arrivalTime = isset($_POST["arrival_time_{$member['id']}"]) ? $_POST["arrival_time_{$member['id']}"] : null;
            $notes = isset($_POST["notes_{$member['id']}"]) ? trim($_POST["notes_{$member['id']}"]) : null;
            
            if ($status === 'present') {
                $presentCount++;
            }
            
            $attendanceStmt->execute([$meetingId, $member['id'], $status, $arrivalTime, $notes, $userId]);
        }
        
        // Update meeting attendance count and status if completed
        $newStatus = $meeting['status'];
        if ($meeting['status'] === 'scheduled' && $presentCount >= $meeting['quorum']) {
            $newStatus = 'ongoing';
        }
        
        $stmt = db()->prepare("
            UPDATE meetings 
            SET actual_attendance = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$presentCount, $newStatus, $meetingId]);
        
        auditLog('update_attendance', 'meetings', $meetingId, null, [
            'present_count' => $presentCount,
            'quorum' => $meeting['quorum']
        ]);
        
        db()->commit();
        
        $message = "Attendance recorded successfully! Present: {$presentCount}";
        if ($presentCount >= $meeting['quorum'] && $meeting['status'] === 'scheduled') {
            $message .= " Quorum achieved! Meeting can proceed.";
        }
        setFlash('success', $message);
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
    
    redirect("/modules/meetings/attendance.php?meeting_id={$meetingId}");
}

$page_title = 'Meeting Attendance - ' . $meeting['title'];
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
                            <li class="breadcrumb-item active">Attendance</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Meeting Attendance</h1>
                    <p class="text-muted"><?php echo htmlspecialchars($meeting['title']); ?> - <?php echo formatDate($meeting['meeting_date']); ?></p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Attendance Sheet
                    </button>
                    <a href="details.php?id=<?php echo $meetingId; ?>" class="btn btn-secondary ms-2">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
            
            <!-- Meeting Info Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Total Members</h6>
                            <h3><?php echo count($allMembers); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Required Quorum</h6>
                            <h3><?php echo $meeting['quorum']; ?></h3>
                            <small>(50% + 1)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card <?php echo $meeting['actual_attendance'] >= $meeting['quorum'] ? 'border-success' : 'border-warning'; ?>">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Present</h6>
                            <h3 class="<?php echo $meeting['actual_attendance'] >= $meeting['quorum'] ? 'text-success' : 'text-warning'; ?>">
                                <?php echo $meeting['actual_attendance']; ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h6 class="text-muted">Attendance Rate</h6>
                            <h3><?php echo count($allMembers) > 0 ? round(($meeting['actual_attendance'] / count($allMembers)) * 100, 1) : 0; ?>%</h3>
                            <div class="progress mt-2" style="height: 5px;">
                                <div class="progress-bar bg-success" style="width: <?php echo count($allMembers) > 0 ? ($meeting['actual_attendance'] / count($allMembers)) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quorum Alert -->
            <?php if ($meeting['actual_attendance'] < $meeting['quorum'] && $meeting['status'] !== 'completed'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Quorum Not Yet Achieved!</strong> Currently <?php echo $meeting['actual_attendance']; ?> members present. 
                    Need <?php echo $meeting['quorum'] - $meeting['actual_attendance']; ?> more member(s) to proceed.
                </div>
            <?php elseif ($meeting['actual_attendance'] >= $meeting['quorum'] && $meeting['status'] === 'ongoing'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Quorum Achieved!</strong> The meeting can proceed. <?php echo $meeting['actual_attendance']; ?> members present.
                </div>
            <?php endif; ?>
            
            <!-- Attendance Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-check me-2"></i>Mark Attendance</h5>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="attendanceTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Member Details</th>
                                        <th>Status</th>
                                        <th>Arrival Time</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allMembers as $index => $member): 
                                        $attendance = $existingAttendance[$member['id']] ?? null;
                                        $status = $attendance ? $attendance['status'] : 'absent';
                                    ?>
                                        <tr class="<?php echo $status === 'present' ? 'table-success' : ($status === 'excused' ? 'table-warning' : ''); ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                <small class="text-muted"><?php echo $member['member_number']; ?></small>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm status-select" name="status_<?php echo $member['id']; ?>" style="width: 130px;">
                                                    <option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
                                                    <option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                    <option value="excused" <?php echo $status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="time" class="form-control form-control-sm" name="arrival_time_<?php echo $member['id']; ?>" 
                                                       value="<?php echo $attendance ? $attendance['arrival_time'] : ''; ?>" style="width: 100px;">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" name="notes_<?php echo $member['id']; ?>" 
                                                       value="<?php echo htmlspecialchars($attendance ? $attendance['notes'] : ''); ?>" 
                                                       placeholder="Reason for absence/lateness">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Attendance
                            </button>
                            <button type="button" class="btn btn-success ms-2" onclick="markAllPresent()">
                                <i class="fas fa-check-double me-2"></i>Mark All Present
                            </button>
                            <button type="button" class="btn btn-warning ms-2" onclick="markAllAbsent()">
                                <i class="fas fa-times me-2"></i>Mark All Absent
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Meeting Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <a href="minutes.php?meeting_id=<?php echo $meetingId; ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-file-alt me-2"></i>Record Minutes
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="report.php?id=<?php echo $meetingId; ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-secondary w-100" onclick="sendReminders()">
                                <i class="fas fa-bell me-2"></i>Send Reminders
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function markAllPresent() {
    $('.status-select').val('present');
    $('tr').addClass('table-success');
}

function markAllAbsent() {
    $('.status-select').val('absent');
    $('tr').removeClass('table-success table-warning');
}

function sendReminders() {
    Swal.fire({
        title: 'Send Reminders?',
        text: 'Send attendance reminders to all members?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'send_reminders.php',
                method: 'POST',
                data: {
                    meeting_id: <?php echo $meetingId; ?>,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success', 'Reminders sent successfully', 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to send reminders', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to send reminders', 'error');
                }
            });
        }
    });
}

$('.status-select').change(function() {
    const row = $(this).closest('tr');
    const value = $(this).val();
    row.removeClass('table-success table-warning');
    if (value === 'present') {
        row.addClass('table-success');
    } else if (value === 'excused') {
        row.addClass('table-warning');
    }
});
</script>

<style>
@media print {
    .btn, .topbar, .sidebar, form button, .card-header .btn {
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
    select, input {
        border: none !important;
        background: transparent !important;
    }
}
</style>

<?php include_once '../../templates/footer.php'; ?>
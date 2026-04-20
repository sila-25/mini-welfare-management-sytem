<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

Permissions::check('manage_meetings');
Middleware::requireGroupAccess();

$page_title = 'Schedule Meeting';
$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

// Get members for attendance list
$stmt = db()->prepare("SELECT id, first_name, last_name, member_number, email FROM members WHERE group_id = ? AND status = 'active' ORDER BY first_name");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token');
        redirect('/modules/meetings/create.php');
    }
    
    try {
        db()->beginTransaction();
        
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $meetingDate = $_POST['meeting_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        $venue = trim($_POST['venue']);
        $meetingType = $_POST['meeting_type'];
        $locationLink = !empty($_POST['location_link']) ? trim($_POST['location_link']) : null;
        $onlineLink = !empty($_POST['online_link']) ? trim($_POST['online_link']) : null;
        $agenda = trim($_POST['agenda']);
        
        // Calculate quorum (50% of active members + 1)
        $totalMembers = count($members);
        $quorum = floor($totalMembers / 2) + 1;
        
        // Insert meeting
        $stmt = db()->prepare("
            INSERT INTO meetings (
                group_id, title, description, meeting_date, start_time, end_time,
                venue, meeting_type, location_link, online_link, agenda,
                expected_attendance, quorum, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");
        
        $stmt->execute([
            $groupId, $title, $description, $meetingDate, $startTime, $endTime,
            $venue, $meetingType, $locationLink, $onlineLink, $agenda,
            $totalMembers, $quorum, $userId
        ]);
        
        $meetingId = db()->lastInsertId();
        
        // Add agenda items if provided
        if (isset($_POST['agenda_items']) && is_array($_POST['agenda_items'])) {
            $agendaStmt = db()->prepare("
                INSERT INTO meeting_agenda_items (group_id, meeting_id, item_order, title, description, presenter, duration_minutes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['agenda_items'] as $index => $item) {
                if (!empty($item['title'])) {
                    $agendaStmt->execute([
                        $groupId, $meetingId, $index + 1,
                        $item['title'], $item['description'] ?? null,
                        $item['presenter'] ?? null, $item['duration'] ?? 0
                    ]);
                }
            }
        }
        
        // Send notifications to members
        $subject = "New Meeting Scheduled: {$title}";
        $body = "
            <h3>Meeting Notification</h3>
            <p>A new meeting has been scheduled:</p>
            <p><strong>Title:</strong> {$title}</p>
            <p><strong>Date:</strong> " . formatDate($meetingDate) . "</p>
            <p><strong>Time:</strong> " . date('h:i A', strtotime($startTime)) . " - " . date('h:i A', strtotime($endTime)) . "</p>
            <p><strong>Venue:</strong> {$venue}</p>
            <p><strong>Description:</strong> {$description}</p>
            <hr>
            <p>Please confirm your attendance through the system.</p>
            <p><a href='" . APP_URL . "/modules/meetings/attendance.php?meeting_id={$meetingId}'>Confirm Attendance</a></p>
        ";
        
        foreach ($members as $member) {
            if ($member['email']) {
                sendEmail($member['email'], $subject, $body);
            }
        }
        
        auditLog('create_meeting', 'meetings', $meetingId, null, ['title' => $title]);
        
        db()->commit();
        
        setFlash('success', "Meeting '{$title}' scheduled successfully! Notifications sent to all members.");
        redirect("/modules/meetings/attendance.php?meeting_id={$meetingId}");
        
    } catch (Exception $e) {
        db()->rollback();
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Meetings</a></li>
                            <li class="breadcrumb-item active">Schedule Meeting</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Schedule New Meeting</h1>
                    <p class="text-muted">Create and schedule a new group meeting</p>
                </div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <form action="" method="POST" id="meetingForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Meeting Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" required placeholder="e.g., Monthly General Meeting, Special Committee Meeting">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meeting Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="meeting_type" required>
                                    <option value="regular">Regular Meeting</option>
                                    <option value="special">Special Meeting</option>
                                    <option value="annual">Annual General Meeting</option>
                                    <option value="emergency">Emergency Meeting</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Meeting Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="meeting_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" value="14:00" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" value="17:00" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Venue <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="venue" required placeholder="Physical location or virtual meeting link">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location Link (Google Maps)</label>
                                <input type="url" class="form-control" name="location_link" placeholder="https://maps.google.com/...">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Online Meeting Link</label>
                                <input type="url" class="form-control" name="online_link" placeholder="Zoom, Google Meet, Teams link">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Meeting purpose, objectives, and important notes"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Agenda</label>
                                <textarea class="form-control" name="agenda" rows="5" placeholder="1. Opening prayer&#10;2. Reading of previous minutes&#10;3. Matters arising&#10;4. Financial report&#10;5. New business&#10;6. Adjournment"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Agenda Items (Detailed)</label>
                                <div id="agendaItemsContainer">
                                    <div class="agenda-item mb-3">
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="agenda_items[0][title]" placeholder="Item title">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" class="form-control" name="agenda_items[0][presenter]" placeholder="Presenter">
                                            </div>
                                            <div class="col-md-2">
                                                <input type="number" class="form-control" name="agenda_items[0][duration]" placeholder="Minutes">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-danger remove-agenda-item" style="display: none;">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                            <div class="col-12 mt-2">
                                                <textarea class="form-control" name="agenda_items[0][description]" rows="2" placeholder="Description/details"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" id="addAgendaItem">
                                    <i class="fas fa-plus me-2"></i>Add Agenda Item
                                </button>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> All active members will receive email notifications about this meeting.
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-calendar-plus me-2"></i>Schedule Meeting
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Tips Card -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Meeting Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Schedule meetings at least 7 days in advance</li>
                        <li>Provide clear agenda items for better preparation</li>
                        <li>Include location maps or virtual links</li>
                        <li>Set realistic time allocations for each agenda item</li>
                        <li>Ensure quorum requirements are met (<?php echo floor(count($members) / 2) + 1; ?> members)</li>
                        <li>Send reminders 24 hours before the meeting</li>
                    </ul>
                </div>
            </div>
            
            <!-- Quorum Info -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Quorum Information</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><strong>Total Active Members:</strong> <?php echo count($members); ?></p>
                    <p class="mb-0"><strong>Required Quorum:</strong> <?php echo floor(count($members) / 2) + 1; ?> members (50% + 1)</p>
                    <hr>
                    <small class="text-muted">Meeting cannot proceed without quorum</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let agendaCount = 1;

$('#addAgendaItem').click(function() {
    const newItem = `
        <div class="agenda-item mb-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" class="form-control" name="agenda_items[${agendaCount}][title]" placeholder="Item title">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="agenda_items[${agendaCount}][presenter]" placeholder="Presenter">
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="agenda_items[${agendaCount}][duration]" placeholder="Minutes">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-danger remove-agenda-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="col-12 mt-2">
                    <textarea class="form-control" name="agenda_items[${agendaCount}][description]" rows="2" placeholder="Description/details"></textarea>
                </div>
            </div>
        </div>
    `;
    $('#agendaItemsContainer').append(newItem);
    $('.remove-agenda-item').show();
    agendaCount++;
});

$(document).on('click', '.remove-agenda-item', function() {
    $(this).closest('.agenda-item').remove();
    if ($('.agenda-item').length === 1) {
        $('.remove-agenda-item').hide();
    }
});

// Validate end time > start time
$('#meetingForm').on('submit', function(e) {
    const startTime = $('input[name="start_time"]').val();
    const endTime = $('input[name="end_time"]').val();
    
    if (startTime >= endTime) {
        e.preventDefault();
        Swal.fire('Error', 'End time must be after start time', 'error');
        return false;
    }
    
    $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Scheduling...');
});
</script>

<?php include_once '../../templates/footer.php'; ?>
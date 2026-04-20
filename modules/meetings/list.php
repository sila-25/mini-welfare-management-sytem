<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_meetings');
Middleware::requireGroupAccess();

$page_title = 'Meetings Management';
$current_page = 'meetings';

$groupId = $_SESSION['group_id'];
$userRole = $_SESSION['user_role'];

// Handle filters
$status = $_GET['status'] ?? '';
$meeting_type = $_GET['meeting_type'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'meeting_date';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$view_mode = $_GET['view_mode'] ?? 'grid'; // grid or list
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Get statistics
$stmt = db()->prepare("
    SELECT 
        COUNT(*) as total_meetings,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN meeting_date >= CURDATE() AND status = 'scheduled' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN meeting_date < CURDATE() AND status = 'scheduled' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN meeting_type = 'regular' THEN 1 ELSE 0 END) as regular,
        SUM(CASE WHEN meeting_type = 'special' THEN 1 ELSE 0 END) as special,
        SUM(CASE WHEN meeting_type = 'annual' THEN 1 ELSE 0 END) as annual,
        SUM(CASE WHEN meeting_type = 'emergency' THEN 1 ELSE 0 END) as emergency,
        AVG(CASE WHEN status = 'completed' AND expected_attendance > 0 THEN (actual_attendance / expected_attendance * 100) ELSE 0 END) as avg_attendance,
        SUM(CASE WHEN meeting_date = CURDATE() AND status = 'scheduled' THEN 1 ELSE 0 END) as today_meetings
    FROM meetings 
    WHERE group_id = ?
");
$stmt->execute([$groupId]);
$stats = $stmt->fetch();

// Get upcoming meetings for sidebar
$stmt = db()->prepare("
    SELECT id, title, meeting_date, start_time, venue, expected_attendance
    FROM meetings 
    WHERE group_id = ? AND meeting_date >= CURDATE() AND status = 'scheduled'
    ORDER BY meeting_date ASC, start_time ASC
    LIMIT 5
");
$stmt->execute([$groupId]);
$upcomingMeetings = $stmt->fetchAll();

// Build query for meetings list
$query = "SELECT m.*, 
          COUNT(DISTINCT ma.id) as attendance_count,
          SUM(CASE WHEN ma.status = 'present' THEN 1 ELSE 0 END) as present_count,
          SUM(CASE WHEN ma.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
          COUNT(DISTINCT ai.id) as agenda_count,
          COUNT(DISTINCT md.id) as decisions_count,
          (SELECT COUNT(*) FROM members WHERE group_id = m.group_id AND status = 'active') as total_members,
          CONCAT(u.first_name, ' ', u.last_name) as creator_name
          FROM meetings m
          LEFT JOIN meeting_attendance ma ON m.id = ma.meeting_id
          LEFT JOIN meeting_agenda_items ai ON m.id = ai.meeting_id
          LEFT JOIN meeting_decisions md ON m.id = md.meeting_id
          LEFT JOIN users u ON m.created_by = u.id
          WHERE m.group_id = ?";

$params = [$groupId];

if ($status) {
    $query .= " AND m.status = ?";
    $params[] = $status;
}

if ($meeting_type) {
    $query .= " AND m.meeting_type = ?";
    $params[] = $meeting_type;
}

if ($date_from) {
    $query .= " AND m.meeting_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND m.meeting_date <= ?";
    $params[] = $date_to;
}

if ($search) {
    $query .= " AND (m.title LIKE ? OR m.description LIKE ? OR m.venue LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY m.id ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = db()->prepare($query);
$stmt->execute($params);
$meetings = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM meetings WHERE group_id = ?";
$countParams = [$groupId];

if ($status) {
    $countQuery .= " AND status = ?";
    $countParams[] = $status;
}
if ($meeting_type) {
    $countQuery .= " AND meeting_type = ?";
    $countParams[] = $meeting_type;
}
if ($date_from) {
    $countQuery .= " AND meeting_date >= ?";
    $countParams[] = $date_from;
}
if ($date_to) {
    $countQuery .= " AND meeting_date <= ?";
    $countParams[] = $date_to;
}

$stmt = db()->prepare($countQuery);
$stmt->execute($countParams);
$totalMeetings = $stmt->fetch()['total'];
$totalPages = ceil($totalMeetings / $limit);

include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-calendar-alt me-2"></i>Meetings Management
                    </h1>
                    <p class="text-muted mt-2">Schedule, track attendance, and manage all group meetings</p>
                </div>
                <div>
                    <a href="create.php" class="btn btn-gradient">
                        <i class="fas fa-plus me-2"></i>Schedule Meeting
                    </a>
                    <button type="button" class="btn btn-outline-primary ms-2" onclick="exportMeetings()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card bg-gradient-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Meetings</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_meetings']); ?></h2>
                                </div>
                                <i class="fas fa-calendar fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card bg-gradient-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Upcoming</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['upcoming']); ?></h2>
                                </div>
                                <i class="fas fa-clock fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card bg-gradient-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Completed</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['completed']); ?></h2>
                                </div>
                                <i class="fas fa-check-circle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card stat-card bg-gradient-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Today's Meetings</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['today_meetings']); ?></h2>
                                </div>
                                <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3" id="filterForm">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" placeholder="Search by title, venue..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="ongoing" <?php echo $status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Meeting Type</label>
                            <select class="form-select" name="meeting_type">
                                <option value="">All Types</option>
                                <option value="regular" <?php echo $meeting_type === 'regular' ? 'selected' : ''; ?>>Regular</option>
                                <option value="special" <?php echo $meeting_type === 'special' ? 'selected' : ''; ?>>Special</option>
                                <option value="annual" <?php echo $meeting_type === 'annual' ? 'selected' : ''; ?>>Annual</option>
                                <option value="emergency" <?php echo $meeting_type === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="text-muted">Showing <?php echo count($meetings); ?> of <?php echo $totalMeetings; ?> meetings</span>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary <?php echo $view_mode === 'grid' ? 'active' : ''; ?>" onclick="setViewMode('grid')">
                        <i class="fas fa-th-large"></i> Grid
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary <?php echo $view_mode === 'list' ? 'active' : ''; ?>" onclick="setViewMode('list')">
                        <i class="fas fa-list"></i> List
                    </button>
                </div>
            </div>

            <!-- Meetings Display -->
            <?php if (empty($meetings)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No meetings found</h5>
                        <p class="text-muted">Schedule your first meeting to get started</p>
                        <a href="create.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i>Schedule Meeting
                        </a>
                    </div>
                </div>
            <?php elseif ($view_mode === 'grid'): ?>
                <!-- Grid View -->
                <div class="row">
                    <?php foreach ($meetings as $meeting): 
                        $attendanceRate = $meeting['expected_attendance'] > 0 ? 
                            round(($meeting['present_count'] / $meeting['expected_attendance']) * 100, 1) : 0;
                        $isToday = date('Y-m-d') === $meeting['meeting_date'];
                        $isUpcoming = $meeting['meeting_date'] > date('Y-m-d');
                        $statusColor = [
                            'scheduled' => 'secondary',
                            'ongoing' => 'warning',
                            'completed' => 'success',
                            'cancelled' => 'danger'
                        ];
                        $typeColor = [
                            'regular' => 'primary',
                            'special' => 'warning',
                            'annual' => 'success',
                            'emergency' => 'danger'
                        ];
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card meeting-card h-100">
                                <div class="card-header bg-transparent border-0 pt-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="badge bg-<?php echo $typeColor[$meeting['meeting_type']]; ?>">
                                            <?php echo ucfirst($meeting['meeting_type']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $statusColor[$meeting['status']]; ?>">
                                            <?php echo ucfirst($meeting['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($meeting['title']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars(substr($meeting['description'] ?? '', 0, 80)); ?>
                                    </p>
                                    
                                    <div class="meeting-details mt-3">
                                        <div class="mb-2">
                                            <i class="fas fa-calendar-day text-primary me-2"></i>
                                            <strong><?php echo formatDate($meeting['meeting_date'], 'M d, Y'); ?></strong>
                                            <?php if ($meeting['start_time']): ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($meeting['start_time'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-2">
                                            <i class="fas fa-map-marker-alt text-danger me-2"></i>
                                            <?php echo htmlspecialchars(substr($meeting['venue'] ?? 'TBD', 0, 40)); ?>
                                        </div>
                                        <div class="mb-2">
                                            <i class="fas fa-users text-info me-2"></i>
                                            Attendance: <?php echo $meeting['present_count']; ?>/<?php echo $meeting['expected_attendance']; ?>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo $attendanceRate; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-list me-1"></i> <?php echo $meeting['agenda_count']; ?> agenda items
                                            <i class="fas fa-gavel ms-2 me-1"></i> <?php echo $meeting['decisions_count']; ?> decisions
                                        </div>
                                    </div>
                                    
                                    <?php if ($isToday && $meeting['status'] === 'scheduled'): ?>
                                        <div class="alert alert-info mt-3 mb-0 py-2">
                                            <i class="fas fa-bell me-1"></i> Today at <?php echo date('h:i A', strtotime($meeting['start_time'])); ?>
                                        </div>
                                    <?php elseif ($isUpcoming && $meeting['status'] === 'scheduled'): ?>
                                        <div class="alert alert-light mt-3 mb-0 py-2">
                                            <i class="fas fa-hourglass-half me-1"></i> <?php echo daysUntil($meeting['meeting_date']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group w-100" role="group">
                                        <a href="details.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="attendance.php?meeting_id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-user-check me-1"></i> Attendance
                                        </a>
                                        <?php if ($meeting['status'] === 'completed'): ?>
                                            <a href="report.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-chart-bar me-1"></i> Report
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteMeeting(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars($meeting['title']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- List View -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <a href="?sort_by=title&sort_order=<?php echo $sort_by === 'title' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter(['status' => $status, 'meeting_type' => $meeting_type, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to])); ?>">
                                                Title <?php echo $sort_by === 'title' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=meeting_date&sort_order=<?php echo $sort_by === 'meeting_date' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_filter(['status' => $status, 'meeting_type' => $meeting_type, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to])); ?>">
                                                Date <?php echo $sort_by === 'meeting_date' ? ($sort_order === 'ASC' ? '↑' : '↓') : ''; ?>
                                            </a>
                                        </th>
                                        <th>Type</th>
                                        <th>Venue</th>
                                        <th>Attendance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meetings as $meeting): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($meeting['title']); ?></div>
                                                <small class="text-muted">Created by: <?php echo htmlspecialchars($meeting['creator_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo formatDate($meeting['meeting_date']); ?>
                                                <br><small><?php echo date('h:i A', strtotime($meeting['start_time'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $typeColor[$meeting['meeting_type']]; ?>">
                                                    <?php echo ucfirst($meeting['meeting_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($meeting['venue'] ?? 'TBD', 0, 30)); ?></td>
                                            <td>
                                                <?php echo $meeting['present_count']; ?>/<?php echo $meeting['expected_attendance']; ?>
                                                <div class="progress mt-1" style="width: 80px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $meeting['expected_attendance'] > 0 ? ($meeting['present_count'] / $meeting['expected_attendance'] * 100) : 0; ?>%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $statusColor[$meeting['status']]; ?>">
                                                    <?php echo ucfirst($meeting['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="details.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="attendance.php?meeting_id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-primary" title="Attendance">
                                                        <i class="fas fa-user-check"></i>
                                                    </a>
                                                    <?php if ($meeting['status'] === 'scheduled'): ?>
                                                        <button class="btn btn-sm btn-warning" onclick="editMeeting(<?php echo $meeting['id']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteMeeting(<?php echo $meeting['id']; ?>, '<?php echo htmlspecialchars($meeting['title']); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&meeting_type=<?php echo $meeting_type; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&view_mode=<?php echo $view_mode; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&' . http_build_query(array_filter(['status' => $status, 'meeting_type' => $meeting_type, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to, 'sort_by' => $sort_by, 'sort_order' => $sort_order, 'view_mode' => $view_mode])) . '">1</a></li>';
                                if ($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&meeting_type=<?php echo $meeting_type; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&view_mode=<?php echo $view_mode; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&' . http_build_query(array_filter(['status' => $status, 'meeting_type' => $meeting_type, 'search' => $search, 'date_from' => $date_from, 'date_to' => $date_to, 'sort_by' => $sort_by, 'sort_order' => $sort_order, 'view_mode' => $view_mode])) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&meeting_type=<?php echo $meeting_type; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&view_mode=<?php echo $view_mode; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span>Regular Meetings</span>
                            <strong><?php echo $stats['regular']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Special Meetings</span>
                            <strong><?php echo $stats['special']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Annual Meetings</span>
                            <strong><?php echo $stats['annual']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span>Emergency Meetings</span>
                            <strong><?php echo $stats['emergency']; ?></strong>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6>Average Attendance Rate</h6>
                        <h2 class="text-success"><?php echo round($stats['avg_attendance'] ?? 0); ?>%</h2>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" style="width: <?php echo $stats['avg_attendance'] ?? 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Meetings -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Upcoming Meetings</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingMeetings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-2x text-muted mb-2 d-block"></i>
                            <p class="text-muted mb-0">No upcoming meetings</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($upcomingMeetings as $upcoming): ?>
                                <a href="details.php?id=<?php echo $upcoming['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars(substr($upcoming['title'], 0, 30)); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-day me-1"></i> <?php echo formatDate($upcoming['meeting_date'], 'M d'); ?>
                                                <i class="fas fa-clock ms-2 me-1"></i> <?php echo date('h:i A', strtotime($upcoming['start_time'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary"><?php echo daysUntil($upcoming['meeting_date']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="?status=scheduled" class="btn btn-sm btn-outline-primary w-100">View All Upcoming</a>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Schedule Meeting
                        </a>
                        <a href="?status=ongoing" class="btn btn-warning">
                            <i class="fas fa-play-circle me-2"></i>View Ongoing Meetings
                        </a>
                        <a href="?status=completed" class="btn btn-info">
                            <i class="fas fa-check-circle me-2"></i>View Completed Meetings
                        </a>
                        <button class="btn btn-secondary" onclick="exportCalendar()">
                            <i class="fas fa-calendar-alt me-2"></i>Export Calendar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-gradient-success { background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%); }
.bg-gradient-warning { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); }
.stat-card {
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
    border: none;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}
.meeting-card {
    transition: transform 0.3s, box-shadow 0.3s;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.meeting-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.progress {
    border-radius: 10px;
    background-color: #e9ecef;
}
.table td {
    vertical-align: middle;
}
</style>

<script>
function setViewMode(mode) {
    const url = new URL(window.location.href);
    url.searchParams.set('view_mode', mode);
    window.location.href = url.toString();
}

function editMeeting(id) {
    window.location.href = 'edit.php?id=' + id;
}

function deleteMeeting(id, title) {
    Swal.fire({
        title: 'Delete Meeting?',
        html: `Are you sure you want to delete <strong>${title}</strong>?<br><br>This will also delete all attendance records and minutes.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete meeting'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete.php?id=' + id + '&confirm=yes';
        }
    });
}

function exportMeetings() {
    const params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('view_mode');
    window.location.href = 'export.php?' + params.toString();
}

function exportCalendar() {
    window.location.href = 'export_calendar.php';
}

function daysUntil(date) {
    const today = new Date();
    const meetingDate = new Date(date);
    const diffTime = meetingDate - today;
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Tomorrow';
    if (diffDays < 7) return `In ${diffDays} days`;
    if (diffDays < 30) return `In ${Math.floor(diffDays / 7)} weeks`;
    return `In ${Math.floor(diffDays / 30)} months`;
}
</script>

<?php include_once '../../templates/footer.php'; ?>
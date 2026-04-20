<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

Middleware::requireLogin();

$page_title = 'Notifications';
$current_page = 'notifications';

$userId = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    redirect('/dashboard/notifications.php');
}

// Mark single as read
if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    redirect('/dashboard/notifications.php');
}

// Delete notification
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = db()->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    setFlash('success', 'Notification deleted');
    redirect('/dashboard/notifications.php');
}

// Get notifications with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = db()->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $limit, $offset]);
$notifications = $stmt->fetchAll();

// Get total count
$stmt = db()->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->execute([$userId]);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $limit);

// Get unread count
$stmt = db()->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = $stmt->fetch()['unread'];

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </h1>
                    <p class="text-muted mt-2">
                        <?php if ($unreadCount > 0): ?>
                            You have <strong><?php echo $unreadCount; ?></strong> unread notification(s)
                        <?php else: ?>
                            All caught up! No unread notifications
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($unreadCount > 0): ?>
                        <a href="?mark_all=1" class="btn btn-outline-primary" onclick="return confirm('Mark all notifications as read?')">
                            <i class="fas fa-check-double me-2"></i>Mark All as Read
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary ms-2" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3 d-block"></i>
                            <h5 class="text-muted">No notifications</h5>
                            <p class="text-muted">You're all caught up!</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <div class="notification-icon bg-<?php echo getNotificationColor($notification['type']); ?>">
                                                <i class="fas <?php echo getNotificationIcon($notification['type']); ?>"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1 notification-title">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-1 notification-message">
                                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo timeAgo($notification['created_at']); ?>
                                                    </small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <?php if (!$notification['is_read']): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="?read=1&id=<?php echo $notification['id']; ?>">
                                                                    <i class="fas fa-check me-2"></i>Mark as Read
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="?delete=1&id=<?php echo $notification['id']; ?>" 
                                                               onclick="return confirm('Delete this notification?')">
                                                                <i class="fas fa-trash-alt me-2"></i>Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <?php if ($notification['link']): ?>
                                                <div class="mt-2">
                                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-arrow-right me-1"></i>View Details
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Helper functions
function getNotificationIcon($type) {
    $icons = [
        'contribution' => 'fa-coins',
        'loan' => 'fa-hand-holding-usd',
        'meeting' => 'fa-calendar-alt',
        'member' => 'fa-user-plus',
        'system' => 'fa-cog',
        'reminder' => 'fa-bell'
    ];
    return $icons[$type] ?? 'fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'contribution' => 'success',
        'loan' => 'warning',
        'meeting' => 'info',
        'member' => 'primary',
        'system' => 'secondary',
        'reminder' => 'danger'
    ];
    return $colors[$type] ?? 'secondary';
}
?>

<style>
.notification-item {
    transition: background 0.3s;
    border-left: 4px solid transparent;
}

.notification-item.unread {
    background: rgba(102, 126, 234, 0.05);
    border-left-color: #667eea;
}

.notification-item:hover {
    background: rgba(0,0,0,0.02);
}

.notification-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.notification-icon.bg-success { background: #48bb78; }
.notification-icon.bg-warning { background: #ed8936; }
.notification-icon.bg-info { background: #4299e1; }
.notification-icon.bg-primary { background: #667eea; }
.notification-icon.bg-secondary { background: #718096; }
.notification-icon.bg-danger { background: #f56565; }

.notification-title {
    font-size: 1rem;
    margin-bottom: 5px;
}

.notification-message {
    font-size: 0.85rem;
    color: #4a5568;
}

body.dark-mode .notification-message {
    color: #cbd5e0;
}
</style>

<?php include_once '../templates/footer.php'; ?>
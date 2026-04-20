<?php
// Only show topbar if user is logged in
if (!isLoggedIn()) return;

// Get unread notifications count
$notificationCount = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND is_read = 0 
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationCount = $stmt->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $notificationCount = 0;
    }
}
?>

<header class="topbar" id="topbar">
    <div class="topbar-left">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo-text">
            <span class="fw-bold">Careway</span>
            <span class="small text-muted ms-1">Welfare System</span>
        </div>
    </div>
    
    <div class="topbar-center">
        <div class="group-badge">
            <i class="fas fa-building me-1"></i>
            <span><?php echo htmlspecialchars($_SESSION['group_code'] ?? 'GROUP'); ?></span>
        </div>
        <div class="page-title">
            <?php echo $page_title ?? 'Dashboard'; ?>
        </div>
    </div>
    
    <div class="topbar-right">
        <!-- Dark Mode Toggle -->
        <button class="topbar-btn" id="darkModeToggle" title="Dark Mode">
            <i class="fas fa-moon"></i>
        </button>
        
        <!-- Notifications -->
        <div class="dropdown">
            <button class="topbar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                <div class="dropdown-header">
                    <strong>Notifications</strong>
                    <a href="<?php echo APP_URL; ?>/dashboard/notifications.php" class="float-end">View All</a>
                </div>
                <div class="dropdown-divider"></div>
                <div class="dropdown-item text-center text-muted">
                    <i class="fas fa-bell-slash me-1"></i> No new notifications
                </div>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="dropdown">
            <button class="dropdown-toggle user-menu" type="button" data-bs-toggle="dropdown">
                <div class="user-avatar-small">
                    <div class="avatar-initial">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                    </div>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/dashboard/profile.php">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/dashboard/profile.php?tab=security">
                        <i class="fas fa-lock me-2"></i> Security
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/dashboard/notifications.php">
                        <i class="fas fa-bell me-2"></i> Notifications
                        <?php if ($notificationCount > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/pages/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<style>
.topbar {
    position: sticky;
    top: 0;
    right: 0;
    left: var(--sidebar-width);
    height: var(--header-height);
    background: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    z-index: 999;
    transition: left var(--transition-speed);
}

body.dark-mode .topbar {
    background: #16213e;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.toggle-sidebar {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: #4a5568;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s;
}

.toggle-sidebar:hover {
    background: rgba(102,126,234,0.1);
    color: var(--primary-color);
}

.logo-text {
    font-size: 1rem;
}

.logo-text .fw-bold {
    color: var(--primary-color);
}

.topbar-center {
    flex: 1;
    text-align: center;
}

.group-badge {
    display: inline-block;
    background: rgba(102,126,234,0.1);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--primary-color);
    margin-right: 15px;
}

.page-title {
    display: inline-block;
    font-size: 1rem;
    font-weight: 500;
    color: #4a5568;
}

body.dark-mode .page-title {
    color: #cbd5e0;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.topbar-btn {
    background: none;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    color: #4a5568;
    padding: 8px;
    border-radius: 50%;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    position: relative;
}

.topbar-btn:hover {
    background: rgba(102,126,234,0.1);
    color: var(--primary-color);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #f56565;
    color: white;
    font-size: 0.65rem;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 18px;
}

.notification-dropdown {
    width: 320px;
    max-height: 400px;
    overflow-y: auto;
}

.user-menu {
    display: flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px 12px;
    border-radius: 30px;
    transition: all 0.3s;
}

.user-menu:hover {
    background: rgba(102,126,234,0.1);
}

.user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.user-avatar-small .avatar-initial {
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
}

.user-menu .user-name {
    font-weight: 500;
    font-size: 0.85rem;
}

.user-menu .fa-chevron-down {
    font-size: 0.75rem;
    color: #a0aec0;
}

@media (max-width: 768px) {
    .logo-text {
        display: none;
    }
    
    .group-badge {
        display: none;
    }
    
    .user-menu .user-name {
        display: none;
    }
    
    .user-menu .fa-chevron-down {
        display: none;
    }
    
    .notification-dropdown {
        width: 280px;
    }
}
</style>
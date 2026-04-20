<?php
// Only show sidebar if user is logged in
if (!isLoggedIn()) return;

$current_page = $current_page ?? '';
$user_role = $_SESSION['user_role'] ?? 'member';
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-hand-holding-heart fa-2x"></i>
            <h3 class="logo-text">Careway</h3>
        </div>
        <p class="logo-subtitle">Welfare Management System</p>
    </div>
    
    <div class="sidebar-user">
        <div class="user-avatar">
            <div class="avatar-initial">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
            </div>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></div>
            <div class="user-group small"><?php echo htmlspecialchars($_SESSION['group_name'] ?? 'System'); ?></div>
            <div class="user-role">
                <span class="badge bg-<?php 
                    echo $user_role === 'super_admin' ? 'danger' : 
                        ($user_role === 'chairperson' ? 'warning' : 
                        ($user_role === 'treasurer' ? 'success' : 
                        ($user_role === 'secretary' ? 'info' : 'secondary'))); 
                ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                </span>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php if ($user_role === 'super_admin'): ?>
                <!-- SUPER ADMIN MENU - Platform Management Only -->
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=overview" class="nav-link <?php echo $current_page === 'super_admin_overview' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=groups" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Manage Groups</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=group-admins" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        <span>Group Admins</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=system-admins" class="nav-link">
                        <i class="fas fa-crown"></i>
                        <span>System Admins</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=plans" class="nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Subscription Plans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=transactions" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        <span>Transactions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/super_admin/dashboard.php?section=settings" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Platform Settings</span>
                    </a>
                </li>
                
            <?php elseif ($user_role === 'chairperson'): ?>
                <!-- GROUP ADMIN MENU - Full Group Management -->
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link <?php echo $current_page === 'admin' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/members/list.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Members</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/contributions/list.php" class="nav-link">
                        <i class="fas fa-coins"></i>
                        <span>Contributions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/loans/index.php" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Loans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/meetings/index.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Meetings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/elections/index.php" class="nav-link">
                        <i class="fas fa-vote-yea"></i>
                        <span>Elections</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/reports/index.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/accounts/index.php" class="nav-link">
                        <i class="fas fa-university"></i>
                        <span>Accounts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/investments/index.php" class="nav-link">
                        <i class="fas fa-chart-pie"></i>
                        <span>Investments</span>
                    </a>
                </li>
                <li class="nav-divider"></li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/settings/index.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Group Settings</span>
                    </a>
                </li>
                
            <?php else: ?>
                <!-- REGULAR MEMBER MENU -->
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/loans/apply.php" class="nav-link">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Apply for Loan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/loans/index.php" class="nav-link">
                        <i class="fas fa-list"></i>
                        <span>My Loans</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/meetings/index.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Meetings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo APP_URL; ?>/modules/elections/index.php" class="nav-link">
                        <i class="fas fa-vote-yea"></i>
                        <span>Elections</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-divider"></li>
            <li class="nav-item">
                <a href="<?php echo APP_URL; ?>/pages/logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-version">
            <small>Version <?php echo APP_VERSION; ?></small>
        </div>
    </div>
</aside>

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transition: all var(--transition-speed);
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
}

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 10px;
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar.collapsed .logo-text,
.sidebar.collapsed .logo-subtitle,
.sidebar.collapsed .user-info,
.sidebar.collapsed .nav-link span,
.sidebar.collapsed .sidebar-footer {
    display: none;
}

.sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 12px;
}

.sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: 1.3rem;
}

.sidebar-header {
    padding: 25px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.logo-text {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
}

.logo-subtitle {
    font-size: 0.65rem;
    opacity: 0.8;
    margin-top: 5px;
}

.sidebar-user {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-initial {
    font-size: 1.2rem;
    font-weight: 600;
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-group {
    font-size: 0.7rem;
    opacity: 0.8;
}

.user-role .badge {
    font-size: 0.65rem;
    padding: 2px 8px;
}

.nav-menu {
    list-style: none;
    padding: 10px 0;
    margin: 0;
}

.nav-item {
    margin: 3px 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 10px 20px;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    transition: all 0.3s;
    border-radius: 10px;
    margin: 0 10px;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    transform: translateX(5px);
}

.nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    border-left: 3px solid white;
}

.nav-link i {
    width: 24px;
    margin-right: 12px;
    font-size: 1.1rem;
    text-align: center;
}

.nav-link.text-danger {
    color: #f56565 !important;
}

.nav-link.text-danger:hover {
    background: rgba(245,101,101,0.2);
    color: #f56565 !important;
}

.nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 10px 20px;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.1);
    font-size: 0.7rem;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 1050;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>
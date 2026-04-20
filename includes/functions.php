<?php
require_once __DIR__ . '/db.php';

// ============================================
// SANITIZATION FUNCTIONS
// ============================================

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function escape($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// ============================================
// CSRF PROTECTION
// ============================================

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// ============================================
// DATE FORMATTING FUNCTIONS
// ============================================

function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'M d, Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    if (!$datetime) return 'N/A';
    $timestamp = strtotime($datetime);
    $seconds = time() - $timestamp;
    
    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($units as $unit => $name) {
        if ($seconds >= $unit) {
            $number = floor($seconds / $unit);
            return $number . ' ' . $name . ($number > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function daysUntil($date) {
    $today = new DateTime();
    $eventDate = new DateTime($date);
    $diff = $today->diff($eventDate);
    
    if ($diff->days == 0) return 'Today';
    if ($diff->days == 1) return 'Tomorrow';
    if ($diff->days < 7) return $diff->days . ' days';
    if ($diff->days < 30) return floor($diff->days / 7) . ' weeks';
    return floor($diff->days / 30) . ' months';
}

// ============================================
// CURRENCY FORMATTING
// ============================================

function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

// ============================================
// GENERATION FUNCTIONS
// ============================================

function generateMemberNumber($groupId) {
    $prefix = 'MBR';
    $year = date('Y');
    $random = strtoupper(substr(uniqid(), -6));
    return $prefix . $year . $groupId . $random;
}

function generateLoanNumber($groupId) {
    $prefix = 'LN';
    $year = date('Y');
    $random = rand(10000, 99999);
    return $prefix . $year . $groupId . $random;
}

function generateReceiptNumber() {
    return 'RCP-' . date('Ymd') . '-' . strtoupper(uniqid());
}

function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// ============================================
// USER AUTHENTICATION FUNCTIONS
// ============================================

function getUserById($userId) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        return getUserById($_SESSION['user_id']);
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    
    if (is_array($role)) {
        return in_array($_SESSION['user_role'], $role);
    }
    
    return $_SESSION['user_role'] === $role;
}

// ============================================
// PAGE ACCESS CONTROL FUNCTIONS
// ============================================

function checkPageAccess($allowed_roles, $redirect = null) {
    if (!isLoggedIn()) {
        if ($redirect) {
            header('Location: ' . $redirect);
        } else {
            header('Location: ' . APP_URL . '/pages/login.php');
        }
        exit();
    }
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        if ($redirect) {
            header('Location: ' . $redirect);
            exit();
        }
        return false;
    }
    return true;
}

function showAccessDenied($message = null) {
    global $page_title;
    $page_title = $page_title ?? 'Access Denied';
    include_once APP_ROOT . '/templates/header.php';
    include_once APP_ROOT . '/templates/sidebar.php';
    include_once APP_ROOT . '/templates/topbar.php';
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card border-0 shadow-lg text-center">
                        <div class="card-body py-5">
                            <div class="mb-4">
                                <i class="fas fa-lock fa-4x text-danger"></i>
                            </div>
                            <h2 class="h4 mb-3">Access Denied</h2>
                            <p class="text-muted mb-4"><?php echo $message ?: 'You do not have permission to access this page.'; ?></p>
                            <p class="text-muted mb-4">This area is restricted to authorized personnel only.</p>
                            <a href="<?php echo APP_URL; ?>/dashboard/home.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include_once APP_ROOT . '/templates/footer.php';
    exit();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        showAccessDenied('You do not have the required role to access this page.');
    }
}

// ============================================
// REDIRECT AND FLASH MESSAGES
// ============================================

function redirect($url) {
    header("Location: " . APP_URL . $url);
    exit();
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================
// AUDIT LOGGING
// ============================================

function auditLog($action, $tableName = null, $recordId = null, $oldData = null, $newData = null) {
    if (!isLoggedIn()) return;
    
    $userId = $_SESSION['user_id'];
    $groupId = $_SESSION['group_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    try {
        $stmt = db()->prepare("
            INSERT INTO audit_logs (user_id, group_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $groupId,
            $action,
            $tableName,
            $recordId,
            $oldData ? json_encode($oldData) : null,
            $newData ? json_encode($newData) : null,
            $ipAddress,
            $userAgent
        ]);
    } catch (Exception $e) {
        // Silently fail if table doesn't exist
    }
}

// ============================================
// SETTINGS FUNCTIONS
// ============================================

function getGroupSetting($groupId, $key, $default = null) {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM group_settings WHERE group_id = ? AND setting_key = ?");
        $stmt->execute([$groupId, $key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function getSystemSetting($key, $default = null) {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// ============================================
// SUBSCRIPTION FUNCTIONS
// ============================================

function checkSubscription($groupId) {
    try {
        $stmt = db()->prepare("
            SELECT subscription_status, subscription_end 
            FROM groups 
            WHERE id = ? AND subscription_status = 'active'
            AND subscription_end >= CURDATE()
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return true;
    }
}

// ============================================
// MEMBER FUNCTIONS
// ============================================

function getMemberTotalContributions($memberId, $groupId) {
    try {
        $stmt = db()->prepare("
            SELECT SUM(amount) as total 
            FROM contributions 
            WHERE member_id = ? AND group_id = ?
        ");
        $stmt->execute([$memberId, $groupId]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getMemberLoanBalance($memberId, $groupId) {
    try {
        $stmt = db()->prepare("
            SELECT 
                l.total_repayable,
                COALESCE(SUM(lr.amount), 0) as paid
            FROM loans l
            LEFT JOIN loan_repayments lr ON l.id = lr.loan_id
            WHERE l.member_id = ? AND l.group_id = ? AND l.status IN ('disbursed', 'approved')
            GROUP BY l.id
        ");
        $stmt->execute([$memberId, $groupId]);
        $loans = $stmt->fetchAll();
        
        $balance = 0;
        foreach ($loans as $loan) {
            $balance += ($loan['total_repayable'] - $loan['paid']);
        }
        return $balance;
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================
// LOAN CALCULATION FUNCTIONS
// ============================================

function calculateLoanInterest($principal, $rate, $months) {
    $monthlyRate = $rate / 100 / 12;
    $totalInterest = $principal * $monthlyRate * $months;
    return round($totalInterest, 2);
}

function calculateLoanInstallment($principal, $rate, $months) {
    $monthlyRate = $rate / 100 / 12;
    if ($monthlyRate == 0) {
        return $principal / $months;
    }
    $installment = $principal * $monthlyRate * pow(1 + $monthlyRate, $months) / (pow(1 + $monthlyRate, $months) - 1);
    return round($installment, 2);
}

// ============================================
// FILE HANDLING FUNCTIONS
// ============================================

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

function uploadFile($file, $targetDir, $allowedTypes = null, $maxSize = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed'];
    }
    
    $allowedTypes = $allowedTypes ?: ALLOWED_EXTENSIONS;
    $maxSize = $maxSize ?: UPLOAD_MAX_SIZE;
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $targetPath = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

// ============================================
// EMAIL FUNCTIONS
// ============================================

function sendEmail($to, $subject, $body, $isHtml = true) {
    // For development, just log
    error_log("Email to {$to}: {$subject}");
    
    // TODO: Implement actual email sending with PHPMailer
    return true;
}

// ============================================
// EXPORT FUNCTIONS
// ============================================

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function exportToJSON($data, $filename) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// ============================================
// DASHBOARD STATISTICS
// ============================================

function getDashboardStats($groupId) {
    $stats = [
        'total_members' => 0,
        'total_contributions' => 0,
        'active_loans' => 0,
        'outstanding_loans' => 0,
        'recent_contributions' => [],
        'upcoming_meetings' => []
    ];
    
    try {
        $stmt = db()->prepare("SELECT COUNT(*) as total FROM members WHERE group_id = ? AND status = 'active'");
        $stmt->execute([$groupId]);
        $stats['total_members'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {}
    
    try {
        $stmt = db()->prepare("SELECT SUM(amount) as total FROM contributions WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $stats['total_contributions'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {}
    
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as total, SUM(total_repayable - COALESCE(paid, 0)) as outstanding
            FROM loans l
            LEFT JOIN (
                SELECT loan_id, SUM(amount) as paid
                FROM loan_repayments
                GROUP BY loan_id
            ) lr ON l.id = lr.loan_id
            WHERE l.group_id = ? AND l.status IN ('disbursed', 'approved')
        ");
        $stmt->execute([$groupId]);
        $loanData = $stmt->fetch();
        $stats['active_loans'] = $loanData['total'] ?? 0;
        $stats['outstanding_loans'] = $loanData['outstanding'] ?? 0;
    } catch (Exception $e) {}
    
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
        $stats['recent_contributions'] = $stmt->fetchAll();
    } catch (Exception $e) {}
    
    try {
        $stmt = db()->prepare("
            SELECT * FROM meetings
            WHERE group_id = ? AND meeting_date >= CURDATE() AND status = 'scheduled'
            ORDER BY meeting_date ASC
            LIMIT 5
        ");
        $stmt->execute([$groupId]);
        $stats['upcoming_meetings'] = $stmt->fetchAll();
    } catch (Exception $e) {}
    
    return $stats;
}

// ============================================
// ROLE PERMISSION FUNCTIONS
// ============================================

function userCan($permission) {
    $rolePermissions = [
        'super_admin' => [
            'view_members', 'add_members', 'edit_members', 'delete_members',
            'view_contributions', 'add_contributions', 'edit_contributions', 'delete_contributions',
            'view_loans', 'approve_loans', 'disburse_loans', 'repay_loans',
            'view_reports', 'export_reports',
            'manage_settings', 'manage_users', 'manage_groups'
        ],
        'chairperson' => [
            'view_members', 'add_members', 'edit_members',
            'view_contributions', 'add_contributions',
            'view_loans', 'approve_loans', 'disburse_loans',
            'view_reports',
            'manage_settings'
        ],
        'treasurer' => [
            'view_members',
            'view_contributions', 'add_contributions',
            'view_loans', 'disburse_loans', 'repay_loans',
            'view_reports'
        ],
        'secretary' => [
            'view_members', 'add_members', 'edit_members',
            'view_reports'
        ],
        'member' => [
            'view_own_profile', 'view_own_contributions', 'view_own_loans', 'apply_loans'
        ]
    ];
    
    $role = $_SESSION['user_role'] ?? 'member';
    return in_array($permission, $rolePermissions[$role] ?? []);
}

// ============================================
// VALIDATION FUNCTIONS
// ============================================

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,15}$/', $phone);
}

function validatePassword($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

// ============================================
// LOGGING FUNCTIONS
// ============================================

function logError($message, $context = []) {
    $log = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $log .= ' - ' . json_encode($context);
    }
    error_log($log);
}

function logInfo($message, $context = []) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $log = date('Y-m-d H:i:s') . ' [INFO] - ' . $message;
        if (!empty($context)) {
            $log .= ' - ' . json_encode($context);
        }
        error_log($log);
    }
}
?>
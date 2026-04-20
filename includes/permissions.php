<?php
class Permissions {
    
    private static $permissions = [
        'super_admin' => [
            'manage_groups' => true,
            'manage_subscriptions' => true,
            'view_all_reports' => true,
            'manage_system_settings' => true,
            'view_audit_logs' => true,
            'manage_all_users' => true
        ],
        'chairperson' => [
            'manage_members' => true,
            'approve_loans' => true,
            'view_audit_logs' => true,
            'manage_elections' => true,
            'override_actions' => true,
            'manage_finances' => true,
            'manage_meetings' => true,
            'generate_reports' => true
        ],
        'treasurer' => [
            'manage_finances' => true,
            'record_contributions' => true,
            'disburse_loans' => true,
            'view_financial_reports' => true,
            'manage_accounts' => true
        ],
        'secretary' => [
            'manage_members' => true,
            'record_meetings' => true,
            'track_attendance' => true,
            'manage_documents' => true
        ],
        'vice_secretary' => [
            'assist_secretary' => true,
            'view_members' => true,
            'record_minutes' => true
        ],
        'member' => [
            'view_own_profile' => true,
            'view_own_contributions' => true,
            'view_own_loans' => true,
            'vote_in_elections' => true,
            'submit_loan_application' => true
        ]
    ];
    
    public static function can($permission) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        $role = $_SESSION['user_role'];
        
        // Super admin can do everything
        if ($role === 'super_admin') {
            return true;
        }
        
        // Chairperson can do everything except super admin tasks
        if ($role === 'chairperson') {
            return true;
        }
        
        return isset(self::$permissions[$role][$permission]) && self::$permissions[$role][$permission];
    }
    
    public static function check($permission) {
        if (!self::can($permission)) {
            http_response_code(403);
            // Use a simple error message instead of including a file
            die('<h1>Access Denied</h1><p>You do not have permission to access this page.</p><a href="/careway/dashboard/home.php">Back to Dashboard</a>');
        }
        return true;
    }
}
?>
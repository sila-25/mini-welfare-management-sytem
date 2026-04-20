<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "<h1>Fix Subscription Issues</h1>";

try {
    // Check if groups exist
    $stmt = db()->query("SELECT COUNT(*) as count FROM groups");
    $groupCount = $stmt->fetch()['count'];
    
    if ($groupCount == 0) {
        db()->exec("
            INSERT INTO groups (group_name, group_code, email, subscription_plan, subscription_status, subscription_start, subscription_end) 
            VALUES ('Default Group', 'DEF001', 'default@careway.com', 'monthly', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))
        ");
        echo "<p>✓ Created default group</p>";
    }
    
    // Get first group ID
    $groupId = db()->query("SELECT id FROM groups LIMIT 1")->fetch()['id'];
    
    // Update all groups to active
    db()->exec("
        UPDATE groups 
        SET subscription_status = 'active', 
            subscription_start = CURDATE(), 
            subscription_end = DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
    ");
    echo "<p>✓ Updated all groups to active subscription</p>";
    
    // Update superadmin to have a group
    db()->exec("
        UPDATE users 
        SET group_id = $groupId 
        WHERE role = 'super_admin' AND (group_id IS NULL OR group_id = 0)
    ");
    echo "<p>✓ Updated superadmin group assignment</p>";
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✓ Subscription issues fixed!</p>";
    echo "<a href='dashboard/home.php' style='display: inline-block; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Dashboard →</a>";
    echo "&nbsp;&nbsp;";
    echo "<a href='pages/logout.php' style='display: inline-block; background: #f44336; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Logout & Login Again</a>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
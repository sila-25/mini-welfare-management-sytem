<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/middleware.php';

Middleware::requireLogin();

$page_title = 'Subscription Expired';
$groupId = $_SESSION['group_id'];

// Get group details
$stmt = db()->prepare("SELECT * FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

// Fix subscription on the fly
if (isset($_GET['fix'])) {
    $stmt = db()->prepare("
        UPDATE groups 
        SET subscription_status = 'active', 
            subscription_start = CURDATE(), 
            subscription_end = DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
        WHERE id = ?
    ");
    $stmt->execute([$groupId]);
    
    setFlash('success', 'Subscription renewed successfully!');
    redirect('/dashboard/home.php');
}

include_once '../templates/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card text-center mt-5">
                <div class="card-body py-5">
                    <i class="fas fa-exclamation-triangle fa-5x text-warning mb-4"></i>
                    <h2 class="mb-3">Subscription Expired</h2>
                    <p class="lead">Your group's subscription has expired or is inactive.</p>
                    
                    <div class="alert alert-info mt-4">
                        <strong>Click the button below to renew your subscription instantly.</strong>
                    </div>
                    
                    <a href="?fix=1" class="btn btn-success btn-lg">
                        <i class="fas fa-sync-alt me-2"></i>Renew Subscription Now
                    </a>
                    
                    <hr class="my-4">
                    
                    <div class="text-start">
                        <h5>Group Details:</h5>
                        <p><strong>Group Name:</strong> <?php echo htmlspecialchars($group['group_name'] ?? 'N/A'); ?></p>
                        <p><strong>Subscription Status:</strong> 
                            <span class="badge bg-danger"><?php echo ucfirst($group['subscription_status'] ?? 'expired'); ?></span>
                        </p>
                        <p><strong>Subscription End Date:</strong> <?php echo formatDate($group['subscription_end'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <a href="../pages/logout.php" class="btn btn-secondary mt-4">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?>
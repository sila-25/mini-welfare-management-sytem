<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_members');
Middleware::requireGroupAccess();

$memberId = (int)($_GET['id'] ?? 0);
$groupId = $_SESSION['group_id'];

// Get member details
$stmt = db()->prepare("
    SELECT m.*, u.username, u.email as user_email
    FROM members m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.id = ? AND m.group_id = ?
");
$stmt->execute([$memberId, $groupId]);
$member = $stmt->fetch();

if (!$member) {
    setFlash('danger', 'Member not found');
    redirect('/modules/members/list.php');
}

// Get family members
$stmt = db()->prepare("SELECT * FROM member_family WHERE member_id = ? AND group_id = ?");
$stmt->execute([$memberId, $groupId]);
$family = $stmt->fetchAll();

// Get documents
$stmt = db()->prepare("SELECT * FROM member_documents WHERE member_id = ? AND group_id = ?");
$stmt->execute([$memberId, $groupId]);
$documents = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlash('danger', 'Invalid security token. Please try again.');
        redirect("/modules/members/edit.php?id={$memberId}");
    }
    
    try {
        db()->beginTransaction();
        
        // Handle profile photo upload
        $profilePhoto = $member['profile_photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/members/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileInfo = pathinfo($_FILES['profile_photo']['name']);
            $extension = strtolower($fileInfo['extension']);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($extension, $allowedExtensions)) {
                $filename = $member['member_number'] . '_' . time() . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    // Delete old photo if exists
                    if ($profilePhoto && file_exists('../../' . $profilePhoto)) {
                        unlink('../../' . $profilePhoto);
                    }
                    $profilePhoto = 'uploads/members/' . $filename;
                }
            }
        }
        
        // Update member
        $stmt = db()->prepare("
            UPDATE members SET
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                id_number = ?,
                date_of_birth = ?,
                gender = ?,
                occupation = ?,
                physical_address = ?,
                emergency_contact = ?,
                emergency_phone = ?,
                bio = ?,
                profile_photo = ?,
                status = ?
            WHERE id = ? AND group_id = ?
        ");
        
        $stmt->execute([
            trim($_POST['first_name']),
            trim($_POST['last_name']),
            !empty($_POST['email']) ? trim($_POST['email']) : null,
            !empty($_POST['phone']) ? trim($_POST['phone']) : null,
            !empty($_POST['id_number']) ? trim($_POST['id_number']) : null,
            !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
            !empty($_POST['gender']) ? $_POST['gender'] : null,
            !empty($_POST['occupation']) ? trim($_POST['occupation']) : null,
            !empty($_POST['physical_address']) ? trim($_POST['physical_address']) : null,
            !empty($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : null,
            !empty($_POST['emergency_phone']) ? trim($_POST['emergency_phone']) : null,
            !empty($_POST['bio']) ? trim($_POST['bio']) : null,
            $profilePhoto,
            $_POST['status'],
            $memberId,
            $groupId
        ]);
        
        // Update family members (delete existing and re-add)
        $deleteStmt = db()->prepare("DELETE FROM member_family WHERE member_id = ? AND group_id = ?");
        $deleteStmt->execute([$memberId, $groupId]);
        
        if (isset($_POST['family_name']) && is_array($_POST['family_name'])) {
            $familyStmt = db()->prepare("
                INSERT INTO member_family (group_id, member_id, relationship, full_name, date_of_birth)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < count($_POST['family_name']); $i++) {
                if (!empty($_POST['family_name'][$i])) {
                    $familyStmt->execute([
                        $groupId,
                        $memberId,
                        $_POST['family_relationship'][$i] ?? 'other',
                        trim($_POST['family_name'][$i]),
                        !empty($_POST['family_dob'][$i]) ? $_POST['family_dob'][$i] : null
                    ]);
                }
            }
        }
        
        // Update user account if exists
        if ($member['user_id']) {
            $userStmt = db()->prepare("
                UPDATE users SET
                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?
                WHERE id = ?
            ");
            $userStmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'] ?? null,
                $_POST['phone'] ?? null,
                $member['user_id']
            ]);
        }
        
        db()->commit();
        auditLog('edit_member', 'members', $memberId, $member, $_POST);
        setFlash('success', 'Member updated successfully!');
        redirect("/modules/members/view.php?id={$memberId}");
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("Error updating member: " . $e->getMessage());
        setFlash('danger', 'An error occurred while updating the member: ' . $e->getMessage());
    }
}

$page_title = 'Edit Member - ' . $member['first_name'] . ' ' . $member['last_name'];
include_once '../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="list.php">Members</a></li>
                            <li class="breadcrumb-item"><a href="view.php?id=<?php echo $memberId; ?>"><?php echo htmlspecialchars($member['first_name']); ?></a></li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0">Edit Member</h1>
                    <p class="text-muted">Update member information</p>
                </div>
                <div>
                    <a href="view.php?id=<?php echo $memberId; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Form -->
    <div class="card">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <ul class="nav nav-tabs mb-3" id="editTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#personalInfo" type="button">
                            <i class="fas fa-user"></i> Personal Info
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#contactInfo" type="button">
                            <i class="fas fa-address-card"></i> Contact Info
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#familyInfo" type="button">
                            <i class="fas fa-users"></i> Family Info
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="
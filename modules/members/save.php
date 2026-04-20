<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';

Middleware::requireLogin();
Middleware::requireGroupAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/members/index.php');
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token');
    redirect('/modules/members/index.php');
}

$groupId = $_SESSION['group_id'];
$action = $_POST['action'] ?? 'add';
$userId = $_SESSION['user_id'];

try {
    if ($action === 'add') {
        // Generate unique member number
        $memberNumber = generateMemberNumber($groupId);
        
        // Handle profile photo upload
        $profilePhoto = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/members/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $filename = $memberNumber . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                $profilePhoto = 'uploads/members/' . $filename;
            }
        }
        
        // Insert member
        $stmt = db()->prepare("
            INSERT INTO members (
                group_id, member_number, first_name, last_name, email, phone, 
                id_number, date_of_birth, gender, occupation, physical_address,
                emergency_contact, emergency_phone, bio, profile_photo, join_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')
        ");
        
        $stmt->execute([
            $groupId,
            $memberNumber,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['id_number'] ?? null,
            $_POST['date_of_birth'] ?? null,
            $_POST['gender'] ?? null,
            $_POST['occupation'] ?? null,
            $_POST['physical_address'] ?? null,
            $_POST['emergency_contact'] ?? null,
            $_POST['emergency_phone'] ?? null,
            $_POST['bio'] ?? null,
            $profilePhoto
        ]);
        
        $memberId = db()->lastInsertId();
        
        // Add family members
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
                        $_POST['family_name'][$i],
                        $_POST['family_dob'][$i] ?? null
                    ]);
                }
            }
        }
        
        // Handle ID document upload
        if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/documents/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = 'id_' . $memberId . '_' . time() . '_' . $_FILES['id_document']['name'];
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['id_document']['tmp_name'], $targetPath)) {
                $docStmt = db()->prepare("
                    INSERT INTO member_documents (group_id, member_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by)
                    VALUES (?, ?, 'id_card', ?, ?, ?, ?, ?)
                ");
                $docStmt->execute([
                    $groupId,
                    $memberId,
                    $_FILES['id_document']['name'],
                    'uploads/documents/' . $filename,
                    $_FILES['id_document']['size'],
                    $_FILES['id_document']['type'],
                    $userId
                ]);
            }
        }
        
        // Create user account for member
        $username = strtolower($_POST['first_name'] . '.' . $_POST['last_name']);
        $password = generateRandomPassword(10);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userStmt = db()->prepare("
            INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, phone, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'member', 'active')
        ");
        
        $userStmt->execute([
            $groupId,
            $username,
            $_POST['email'] ?? null,
            $passwordHash,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['phone'] ?? null
        ]);
        
        $userId = db()->lastInsertId();
        
        // Link member to user account
        $updateStmt = db()->prepare("UPDATE members SET user_id = ? WHERE id = ?");
        $updateStmt->execute([$userId, $memberId]);
        
        // Send welcome email with credentials
        if (!empty($_POST['email'])) {
            $subject = "Welcome to " . APP_NAME;
            $body = "
                <h3>Welcome to {$groupName}</h3>
                <p>Your member account has been created successfully.</p>
                <p><strong>Member Number:</strong> {$memberNumber}</p>
                <p><strong>Username:</strong> {$username}</p>
                <p><strong>Password:</strong> {$password}</p>
                <p>Please login and change your password immediately.</p>
                <p>Login URL: " . APP_URL . "/pages/login.php</p>
            ";
            sendEmail($_POST['email'], $subject, $body);
        }
        
        auditLog('add_member', 'members', $memberId, null, ['member_number' => $memberNumber]);
        setFlash('success', 'Member added successfully! Login credentials have been sent to their email.');
        
    } elseif ($action === 'edit') {
        $memberId = $_POST['member_id'];
        
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
                status = ?
            WHERE id = ? AND group_id = ?
        ");
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'] ?? null,
            $_POST['phone'] ?? null,
            $_POST['id_number'] ?? null,
            $_POST['date_of_birth'] ?? null,
            $_POST['gender'] ?? null,
            $_POST['occupation'] ?? null,
            $_POST['physical_address'] ?? null,
            $_POST['emergency_contact'] ?? null,
            $_POST['emergency_phone'] ?? null,
            $_POST['bio'] ?? null,
            $_POST['status'],
            $memberId,
            $groupId
        ]);
        
        auditLog('edit_member', 'members', $memberId);
        setFlash('success', 'Member updated successfully');
    }
    
} catch (Exception $e) {
    error_log($e->getMessage());
    setFlash('danger', 'An error occurred: ' . $e->getMessage());
}

redirect('/modules/members/index.php');
?>
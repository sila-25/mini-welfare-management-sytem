<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/middleware.php';
require_once '../../includes/permissions.php';

// Check permission
Permissions::check('manage_members');
Middleware::requireGroupAccess();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/members/list.php');
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    setFlash('danger', 'Invalid security token. Please try again.');
    redirect('/modules/members/list.php');
}

$groupId = $_SESSION['group_id'];
$userId = $_SESSION['user_id'];

try {
    // Start transaction
    db()->beginTransaction();
    
    // Generate unique member number
    $memberNumber = generateMemberNumber($groupId);
    
    // Handle profile photo upload
    $profilePhoto = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/members/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['profile_photo']['name']);
        $extension = strtolower($fileInfo['extension']);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extension, $allowedExtensions)) {
            $filename = $memberNumber . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                $profilePhoto = 'uploads/members/' . $filename;
            }
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
                    trim($_POST['family_name'][$i]),
                    !empty($_POST['family_dob'][$i]) ? $_POST['family_dob'][$i] : null
                ]);
            }
        }
    }
    
    // Handle document uploads
    $documentStmt = db()->prepare("
        INSERT INTO member_documents (group_id, member_id, document_type, document_name, file_path, file_size, mime_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // ID Document
    if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/documents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = 'id_' . $memberId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['id_document']['name']);
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['id_document']['tmp_name'], $targetPath)) {
            $documentStmt->execute([
                $groupId,
                $memberId,
                'id_card',
                $_FILES['id_document']['name'],
                'uploads/documents/' . $filename,
                $_FILES['id_document']['size'],
                $_FILES['id_document']['type'],
                $userId
            ]);
        }
    }
    
    // Additional document
    if (isset($_FILES['additional_document']) && $_FILES['additional_document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/documents/';
        $filename = 'doc_' . $memberId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['additional_document']['name']);
        $targetPath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['additional_document']['tmp_name'], $targetPath)) {
            $documentStmt->execute([
                $groupId,
                $memberId,
                'other',
                $_FILES['additional_document']['name'],
                'uploads/documents/' . $filename,
                $_FILES['additional_document']['size'],
                $_FILES['additional_document']['type'],
                $userId
            ]);
        }
    }
    
    // Create user account for member (if email provided)
    $userCreated = false;
    $generatedPassword = null;
    
    if (!empty($_POST['email'])) {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['first_name'] . '.' . $_POST['last_name']));
        $baseUsername = $username;
        $counter = 1;
        
        // Check if username exists
        $checkStmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        while ($checkStmt->fetch()) {
            $username = $baseUsername . $counter;
            $checkStmt->execute([$username]);
            $counter++;
        }
        
        $generatedPassword = generateRandomPassword(10);
        $passwordHash = password_hash($generatedPassword, PASSWORD_DEFAULT);
        
        $userStmt = db()->prepare("
            INSERT INTO users (group_id, username, email, password_hash, first_name, last_name, phone, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'member', 'active')
        ");
        
        $userStmt->execute([
            $groupId,
            $username,
            $_POST['email'],
            $passwordHash,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['phone'] ?? null
        ]);
        
        $newUserId = db()->lastInsertId();
        
        // Link member to user account
        $updateStmt = db()->prepare("UPDATE members SET user_id = ? WHERE id = ?");
        $updateStmt->execute([$newUserId, $memberId]);
        
        $userCreated = true;
    }
    
    // Get group name for email
    $groupStmt = db()->prepare("SELECT group_name FROM groups WHERE id = ?");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch();
    
    // Send welcome email
    if ($userCreated && !empty($_POST['email'])) {
        $subject = "Welcome to " . APP_NAME . " - " . $group['group_name'];
        $loginUrl = APP_URL . "/pages/login.php";
        $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8f9fa; }
                    .credentials { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Welcome to {$group['group_name']}</h2>
                    </div>
                    <div class='content'>
                        <h3>Dear {$_POST['first_name']} {$_POST['last_name']},</h3>
                        <p>Your membership has been successfully created in the {$group['group_name']} welfare group.</p>
                        
                        <div class='credentials'>
                            <h4>Your Login Credentials:</h4>
                            <p><strong>Member Number:</strong> {$memberNumber}</p>
                            <p><strong>Username:</strong> {$username}</p>
                            <p><strong>Password:</strong> {$generatedPassword}</p>
                            <p><strong>Login URL:</strong> <a href='{$loginUrl}'>{$loginUrl}</a></p>
                        </div>
                        
                        <p><strong>Important:</strong> Please login and change your password immediately for security reasons.</p>
                        
                        <h4>Next Steps:</h4>
                        <ul>
                            <li>Login to your account using the credentials above</li>
                            <li>Update your profile information</li>
                            <li>Review group policies and contribution schedules</li>
                            <li>Start making contributions on time</li>
                        </ul>
                        
                        <p>If you have any questions, please contact the group administrators.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        sendEmail($_POST['email'], $subject, $body);
    }
    
    // Add welcome note
    $noteStmt = db()->prepare("
        INSERT INTO member_notes (group_id, member_id, note_type, note, created_by)
        VALUES (?, ?, 'general', ?, ?)
    ");
    $noteStmt->execute([
        $groupId,
        $memberId,
        "Member joined the group on " . date('F d, Y') . ". " . ($userCreated ? "Account created successfully." : "No user account created (email not provided)."),
        $userId
    ]);
    
    // Audit log
    auditLog('add_member', 'members', $memberId, null, [
        'member_number' => $memberNumber,
        'name' => $_POST['first_name'] . ' ' . $_POST['last_name'],
        'email' => $_POST['email'] ?? null
    ]);
    
    // Commit transaction
    db()->commit();
    
    // Set success message
    $message = "Member added successfully!";
    if ($userCreated) {
        $message .= " Login credentials have been sent to {$_POST['email']}.";
    } else {
        $message .= " Note: No email was provided, so no login account was created.";
    }
    setFlash('success', $message);
    
} catch (Exception $e) {
    // Rollback transaction on error
    db()->rollback();
    error_log("Error adding member: " . $e->getMessage());
    setFlash('danger', 'An error occurred while adding the member: ' . $e->getMessage());
}

// Redirect back to members list
redirect('/modules/members/list.php');
?>
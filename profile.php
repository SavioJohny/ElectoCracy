<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

requireAuth();

$page_title = 'Profile';
$current_user = getCurrentUser();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle password change if provided
        $password_updated = false;
        if (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password)) {
                throw new Exception('Current password is required to change password.');
            }
            
            if (empty($new_password)) {
                throw new Exception('New password is required.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('New password must be at least 6 characters long.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New password and confirmation do not match.');
            }
            
            // Verify current password
            if (!password_verify($current_password, $current_user['password_hash'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            $password_updated = true;
        }
        
        // Only update password if provided
        if ($password_updated) {
            $pdo->beginTransaction();
            
            $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$new_password_hash, $current_user['user_id']]);
            
            $pdo->commit();
            
            $message = 'Password updated successfully!';
            
            // Refresh current user data
            $current_user = getCurrentUser();
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get additional user information based on role
$additional_info = [];
if ($current_user['role_name'] === 'Student') {
    $stmt = $pdo->prepare("
        SELECT c.class_name, d.department_name, u.roll_number
        FROM users u
        LEFT JOIN classes c ON u.class_id = c.class_id
        LEFT JOIN departments d ON c.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$current_user['user_id']]);
    $additional_info = $stmt->fetch() ?: [];
} elseif ($current_user['role_name'] === 'Invigilator') {
    $stmt = $pdo->prepare("
        SELECT d.department_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$current_user['user_id']]);
    $additional_info = $stmt->fetch() ?: [];
    
    // Get assigned classes for current year
    $current_year = date('Y');
    $stmt = $pdo->prepare("
        SELECT c.class_name, d.department_name
        FROM invigilator_class_assignments ica
        JOIN classes c ON ica.class_id = c.class_id
        JOIN departments d ON c.department_id = d.department_id
        WHERE ica.invigilator_id = ? AND ica.election_year = ?
        ORDER BY c.class_name
    ");
    $stmt->execute([$current_user['user_id'], $current_year]);
    $assigned_classes = $stmt->fetchAll();
    $additional_info['assigned_classes'] = $assigned_classes;
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user me-2"></i>My Profile</h2>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-avatar mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h5><?php echo htmlspecialchars($current_user['fname'] . ' ' . $current_user['lname']); ?></h5>
                <p class="text-muted mb-2">
                    <span class="badge bg-<?php 
                        echo match($current_user['role_name']) {
                            'Student' => 'success',
                            'Invigilator' => 'warning',
                            'Election Commissioner' => 'info',
                            'Super Admin' => 'danger',
                            default => 'secondary'
                        };
                    ?>">
                        <?php echo htmlspecialchars($current_user['role_name']); ?>
                    </span>
                </p>
                <p class="text-muted">
                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($current_user['email']); ?>
                </p>
                
                <?php if ($current_user['role_name'] === 'Student' && !empty($additional_info)): ?>
                    <hr>
                    <div class="text-start">
                        <?php if (!empty($additional_info['roll_number'])): ?>
                            <p class="mb-1"><strong>Roll Number:</strong> <?php echo htmlspecialchars($additional_info['roll_number']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($additional_info['class_name'])): ?>
                            <p class="mb-1"><strong>Class:</strong> <?php echo htmlspecialchars($additional_info['class_name']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($additional_info['department_name'])): ?>
                            <p class="mb-0"><strong>Department:</strong> <?php echo htmlspecialchars($additional_info['department_name']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($current_user['role_name'] === 'Invigilator'): ?>
                    <hr>
                    <div class="text-start">
                        <?php if (!empty($additional_info['department_name'])): ?>
                            <p class="mb-2"><strong>Department:</strong> <?php echo htmlspecialchars($additional_info['department_name']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($additional_info['assigned_classes'])): ?>
                            <p class="mb-1"><strong>Assigned Classes (<?php echo date('Y'); ?>):</strong></p>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($additional_info['assigned_classes'] as $class): ?>
                                    <li><small><?php echo htmlspecialchars($class['class_name']); ?></small></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fname" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="fname" name="fname" 
                                       value="<?php echo htmlspecialchars($current_user['fname'] ?? ''); ?>" readonly disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="lname" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="lname" name="lname" 
                                       value="<?php echo htmlspecialchars($current_user['lname'] ?? ''); ?>" readonly disabled>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mname" class="form-label">Middle Name</label>
                        <input type="text" class="form-control" id="mname" name="mname" 
                               value="<?php echo htmlspecialchars($current_user['mname'] ?? ''); ?>" readonly disabled>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" readonly disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender" readonly disabled>
                                    <option value="">Select Gender</option>
                                    <option value="M" <?php echo ($current_user['gender'] ?? '') === 'M' ? 'selected' : ''; ?>>Male</option>
                                    <option value="F" <?php echo ($current_user['gender'] ?? '') === 'F' ? 'selected' : ''; ?>>Female</option>
                                    <option value="O" <?php echo ($current_user['gender'] ?? '') === 'O' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" 
                               value="<?php echo $current_user['dob'] ?? ''; ?>" readonly disabled>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Address Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="address_line1" name="address_line1" 
                                       value="<?php echo htmlspecialchars($current_user['address_line1'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" 
                                       value="<?php echo htmlspecialchars($current_user['address_line2'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($current_user['city'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo htmlspecialchars($current_user['state'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?php echo htmlspecialchars($current_user['postal_code'] ?? ''); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Change Password (Optional)</h6>
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                        <div class="form-text">Required only if you want to change your password</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Clear password fields if current password is empty
document.getElementById('current_password').addEventListener('input', function() {
    if (!this.value) {
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
    }
});
</script>

<?php include 'includes/footer.php'; ?>

<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Staff Details';

// Get staff ID
$staff_id = (int)($_GET['id'] ?? 0);

if (!$staff_id) {
    header('Location: staff.php');
    exit();
}

// Get staff data with department information
$stmt = $pdo->prepare("
    SELECT u.user_id, u.email, u.role_id, u.session_token, u.fname, u.mname, u.lname, 
           u.dob, u.gender, u.phone, u.address_line1, u.address_line2, u.city, u.state, 
           u.postal_code, u.department_id, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE u.user_id = ? AND u.role_id != 1
");
$stmt->execute([$staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    header('Location: staff.php');
    exit();
}


include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Staff Details</h1>
            <div>
                <a href="edit_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit me-1"></i>Edit Staff
                </a>
                <a href="staff.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Staff
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Personal Information -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-12 text-center">
                        <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <?php echo strtoupper(substr($staff['fname'], 0, 1) . substr($staff['lname'], 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($staff['fname'] . ' ' . ($staff['mname'] ? $staff['mname'] . ' ' : '') . $staff['lname']); ?></h4>
                        <span class="badge bg-info fs-6">Staff Member</span>
                    </div>
                </div>
                
                <table class="table table-borderless">
                    <tr>
                        <td><strong>User ID:</strong></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($staff['user_id']); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>First Name:</strong></td>
                        <td><?php echo htmlspecialchars($staff['fname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Middle Name:</strong></td>
                        <td><?php echo htmlspecialchars($staff['mname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Last Name:</strong></td>
                        <td><?php echo htmlspecialchars($staff['lname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date of Birth:</strong></td>
                        <td><?php echo $staff['dob'] ? date('F j, Y', strtotime($staff['dob'])) : 'Not provided'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Gender:</strong></td>
                        <td>
                            <?php 
                            $gender_map = ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'];
                            echo $staff['gender'] ? $gender_map[$staff['gender']] : 'Not specified';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo $staff['phone'] ? htmlspecialchars($staff['phone']) : 'Not provided'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Professional Information -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Professional Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Department:</strong></td>
                        <td><?php echo htmlspecialchars($staff['department_name'] ?? 'Not assigned'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Role ID:</strong></td>
                        <td><?php echo htmlspecialchars($staff['role_id']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Session Status:</strong></td>
                        <td>
                            <?php if ($staff['session_token']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Address Information -->
<?php if ($staff['address_line1'] || $staff['city'] || $staff['state']): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Address Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Address Line 1:</strong></td>
                                <td><?php echo htmlspecialchars($staff['address_line1'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Address Line 2:</strong></td>
                                <td><?php echo htmlspecialchars($staff['address_line2'] ?? 'Not provided'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>City:</strong></td>
                                <td><?php echo htmlspecialchars($staff['city'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td><?php echo htmlspecialchars($staff['state'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Postal Code:</strong></td>
                                <td><?php echo htmlspecialchars($staff['postal_code'] ?? 'Not provided'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>



<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    font-weight: bold;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

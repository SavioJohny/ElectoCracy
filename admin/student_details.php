<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Super Admin');

$page_title = 'Student Details';

// Get student ID
$student_id = (int)($_GET['id'] ?? 0);

if (!$student_id) {
    header('Location: students.php');
    exit();
}

// Get student data with department and class information
$stmt = $pdo->prepare("
    SELECT u.user_id, u.email, u.role_id, u.session_token, u.fname, u.mname, u.lname, 
           u.dob, u.gender, u.phone, u.address_line1, u.address_line2, u.city, u.state, 
           u.postal_code, u.roll_number, u.department_id, u.class_id,
           d.department_name, c.class_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.department_id
    LEFT JOIN classes c ON u.class_id = c.class_id
    WHERE u.user_id = ? AND u.role_id = 1
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: students.php');
    exit();
}


include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Student Details</h1>
            <div>
                <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit me-1"></i>Edit Student
                </a>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Students
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
                            <?php echo strtoupper(substr($student['fname'], 0, 1) . substr($student['lname'], 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($student['fname'] . ' ' . ($student['mname'] ? $student['mname'] . ' ' : '') . $student['lname']); ?></h4>
                        <span class="badge bg-info fs-6"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                    </div>
                </div>
                
                <table class="table table-borderless">
                    <tr>
                        <td><strong>User ID:</strong></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($student['user_id']); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>First Name:</strong></td>
                        <td><?php echo htmlspecialchars($student['fname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Middle Name:</strong></td>
                        <td><?php echo htmlspecialchars($student['mname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Last Name:</strong></td>
                        <td><?php echo htmlspecialchars($student['lname'] ?? 'Not provided'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date of Birth:</strong></td>
                        <td><?php echo $student['dob'] ? date('F j, Y', strtotime($student['dob'])) : 'Not provided'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Gender:</strong></td>
                        <td>
                            <?php 
                            $gender_map = ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'];
                            echo $student['gender'] ? $gender_map[$student['gender']] : 'Not specified';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo $student['phone'] ? htmlspecialchars($student['phone']) : 'Not provided'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Academic Information -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Roll Number:</strong></td>
                        <td><span class="badge bg-primary"><?php echo htmlspecialchars($student['roll_number'] ?? 'Not assigned'); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Department:</strong></td>
                        <td><?php echo htmlspecialchars($student['department_name'] ?? 'Not assigned'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Class:</strong></td>
                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'Not assigned'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Role ID:</strong></td>
                        <td><?php echo htmlspecialchars($student['role_id']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Session Status:</strong></td>
                        <td>
                            <?php if ($student['session_token']): ?>
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
<?php if ($student['address_line1'] || $student['city'] || $student['state']): ?>
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
                                <td><?php echo htmlspecialchars($student['address_line1'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Address Line 2:</strong></td>
                                <td><?php echo htmlspecialchars($student['address_line2'] ?? 'Not provided'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>City:</strong></td>
                                <td><?php echo htmlspecialchars($student['city'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td><?php echo htmlspecialchars($student['state'] ?? 'Not provided'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Postal Code:</strong></td>
                                <td><?php echo htmlspecialchars($student['postal_code'] ?? 'Not provided'); ?></td>
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

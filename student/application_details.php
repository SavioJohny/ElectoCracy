<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

$page_title = 'Application Details';

$user_id = $_SESSION['user_id'];
$candidate_id = (int)($_GET['id'] ?? 0);

if (!$candidate_id) {
    header('Location: dashboard.php');
    exit();
}

// Get candidate application details
$stmt = $pdo->prepare("
    SELECT c.*, u.fname, u.lname, u.roll_number, u.email, u.gender,
           e.election_id, e.election_year, e.class_id,
           cl.class_name, d.department_name,
           p.position_name, p.position_type,
           cd.marksheet_file, cd.attendance_file
    FROM candidates c
    JOIN users u ON c.user_id = u.user_id
    JOIN elections e ON c.election_id = e.election_id
    JOIN classes cl ON e.class_id = cl.class_id
    JOIN departments d ON cl.department_id = d.department_id
    JOIN positions p ON c.position_id = p.position_id
    LEFT JOIN candidate_documents cd ON c.candidate_id = cd.candidate_id
    WHERE c.candidate_id = ? AND c.user_id = ?
");
$stmt->execute([$candidate_id, $user_id]);
$application = $stmt->fetch();

if (!$application) {
    header('Location: dashboard.php');
    exit();
}

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Application Details</h1>
            <div>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Application Status Alert -->
<div class="row mb-4">
    <div class="col-12">
        <?php if ($application['is_approved'] === 'pending'): ?>
            <div class="alert alert-warning">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Application Under Review</h5>
                        <p class="mb-0">Your application is currently being reviewed by the assigned invigilator. You will be notified once a decision is made.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($application['is_approved'] === 'approved'): ?>
            <div class="alert alert-success">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Application Approved</h5>
                        <p class="mb-0">Congratulations! Your application has been approved. You are now eligible to participate in the election.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($application['is_approved'] === 'rejected'): ?>
            <div class="alert alert-danger">
                <div class="d-flex align-items-center">
                    <i class="fas fa-times-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Application Rejected</h5>
                        <p class="mb-0">Unfortunately, your application has been rejected. Please contact the invigilator for more information.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">
                <div class="d-flex align-items-center">
                    <i class="fas fa-question-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Unknown Status</h5>
                        <p class="mb-0">Your application status is unclear. Please contact the administrator.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- Documents Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Submitted Documents</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Marksheet</h6>
                            </div>
                            <div class="card-body text-center">
                                <?php if ($application['marksheet_file']): ?>
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <p class="mb-2"><strong>File:</strong> <?php echo htmlspecialchars($application['marksheet_file']); ?></p>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="../uploads/candidate_documents/<?php echo htmlspecialchars($application['marksheet_file']); ?>" 
                                           target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Document
                                        </a>
                                        <?php if ($application['is_approved'] === 'pending'): ?>
                                            <button class="btn btn-outline-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#replaceMarksheetModal">
                                                <i class="fas fa-edit me-1"></i>Replace
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-file-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No marksheet uploaded</p>
                                    <?php if ($application['is_approved'] === 'pending'): ?>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#replaceMarksheetModal">
                                            <i class="fas fa-upload me-1"></i>Upload Document
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-file-pdf me-2"></i>Attendance Record</h6>
                            </div>
                            <div class="card-body text-center">
                                <?php if ($application['attendance_file']): ?>
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <p class="mb-2"><strong>File:</strong> <?php echo htmlspecialchars($application['attendance_file']); ?></p>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="../uploads/candidate_documents/<?php echo htmlspecialchars($application['attendance_file']); ?>" 
                                           target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Document
                                        </a>
                                        <?php if ($application['is_approved'] === 'pending'): ?>
                                            <button class="btn btn-outline-warning" data-bs-toggle="modal" 
                                                    data-bs-target="#replaceAttendanceModal">
                                                <i class="fas fa-edit me-1"></i>Replace
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-file-times fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No attendance record uploaded</p>
                                    <?php if ($application['is_approved'] === 'pending'): ?>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#replaceAttendanceModal">
                                            <i class="fas fa-upload me-1"></i>Upload Document
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                
            </div>
        </div>
    </div>
</div>


<!-- Replace Marksheet Modal -->
<div class="modal fade" id="replaceMarksheetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-pdf me-2"></i>Replace Marksheet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceMarksheetForm" enctype="multipart/form-data">
                <input type="hidden" name="candidate_id" value="<?php echo $application['candidate_id']; ?>">
                <input type="hidden" name="document_type" value="marksheet">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> This will replace your current marksheet document. Make sure the new file is correct before uploading.
                    </div>
                    <div class="mb-3">
                        <label for="new_marksheet" class="form-label">New Marksheet Document <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="new_marksheet" name="new_document" 
                               accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Upload PDF or image file (max 5MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-upload me-1"></i>Replace Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Replace Attendance Modal -->
<div class="modal fade" id="replaceAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-pdf me-2"></i>Replace Attendance Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replaceAttendanceForm" enctype="multipart/form-data">
                <input type="hidden" name="candidate_id" value="<?php echo $application['candidate_id']; ?>">
                <input type="hidden" name="document_type" value="attendance">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Note:</strong> This will replace your current attendance record. Make sure the new file is correct before uploading.
                    </div>
                    <div class="mb-3">
                        <label for="new_attendance" class="form-label">New Attendance Record <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="new_attendance" name="new_document" 
                               accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="form-text">Upload PDF or image file (max 5MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-upload me-1"></i>Replace Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const marksheetForm = document.getElementById('replaceMarksheetForm');
    const attendanceForm = document.getElementById('replaceAttendanceForm');

    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);
        }

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    function handleFormSubmit(form, modalId) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const fileInput = form.querySelector('input[type="file"]');
            const file = fileInput.files[0];

            if (!file) {
                showAlert('Please select a file to upload.', 'danger');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showAlert('File size must be less than 5MB.', 'danger');
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'replace_document');

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';

            fetch('document_update_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Network error. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }

    if (marksheetForm) {
        handleFormSubmit(marksheetForm, 'replaceMarksheetModal');
    }

    if (attendanceForm) {
        handleFormSubmit(attendanceForm, 'replaceAttendanceModal');
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

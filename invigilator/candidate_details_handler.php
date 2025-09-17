<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Invigilator');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'get_candidate_details':
            $candidate_id = (int)($_POST['candidate_id'] ?? 0);
            
            if (!$candidate_id) {
                throw new Exception('Invalid candidate ID.');
            }
            
            // Get candidate details
            $stmt = $pdo->prepare("
                SELECT c.*, u.fname, u.lname, u.roll_number, u.email, u.phone, u.gender,
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
                WHERE c.candidate_id = ?
            ");
            $stmt->execute([$candidate_id]);
            $candidate = $stmt->fetch();
            
            if (!$candidate) {
                throw new Exception('Candidate not found.');
            }
            
            // Verify invigilator is assigned to this class
            $stmt = $pdo->prepare("
                SELECT assignment_id 
                FROM invigilator_class_assignments 
                WHERE invigilator_id = ? AND class_id = ? AND election_year = ?
            ");
            $stmt->execute([$user_id, $candidate['class_id'], $candidate['election_year']]);
            
            if (!$stmt->fetch()) {
                throw new Exception('You are not authorized to view this candidate.');
            }
            
            // Generate HTML for candidate details
            $gender_map = ['M' => 'Male', 'F' => 'Female', 'O' => 'Other'];
            $gender_display = $candidate['gender'] ? $gender_map[$candidate['gender']] : 'Not specified';
            
            $status_badge = '';
            $status_info = '';
            
            if ($candidate['is_approved'] === 'pending') {
                $status_badge = '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending Review</span>';
                $status_info = '<div class="alert alert-warning"><i class="fas fa-clock me-2"></i>This application is pending your review. Please review the candidate\'s documents and qualifications.</div>';
            } elseif ($candidate['is_approved'] === 'approved') {
                $status_badge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>';
                $status_info = "<div class=\"alert alert-success\"><i class=\"fas fa-check-circle me-2\"></i>This candidate has been approved and can participate in the election.</div>";
            } elseif ($candidate['is_approved'] === 'rejected') {
                $status_badge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>';
                $status_info = "<div class=\"alert alert-danger\"><i class=\"fas fa-times-circle me-2\"></i>This candidate has been rejected and cannot participate in the election.</div>";
            } else {
                $status_badge = '<span class="badge bg-secondary"><i class="fas fa-question me-1"></i>Unknown Status</span>';
                $status_info = "<div class=\"alert alert-secondary\"><i class=\"fas fa-exclamation-triangle me-2\"></i>Unknown status: {$candidate['is_approved']}. Please contact the administrator.</div>";
            }
            
            $html = "
                <div class=\"row\">
                    <div class=\"col-md-6\">
                        <h6 class=\"text-primary mb-3\">Student Information</h6>
                        <table class=\"table table-borderless\">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>" . htmlspecialchars($candidate['fname'] . ' ' . $candidate['lname']) . "</td>
                            </tr>
                            <tr>
                                <td><strong>Roll Number:</strong></td>
                                <td>" . htmlspecialchars($candidate['roll_number']) . "</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>" . htmlspecialchars($candidate['email']) . "</td>
                            </tr>
                            <tr>
                                <td><strong>Gender:</strong></td>
                                <td>{$gender_display}</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class=\"col-md-6\">
                        <h6 class=\"text-primary mb-3\">Documents</h6>
                        <div class=\"d-grid gap-2\">";

            if ($candidate['marksheet_file']) {
                $html .= "
                            <a href=\"../uploads/candidate_documents/" . htmlspecialchars($candidate['marksheet_file']) . "\"
                               target=\"_blank\" class=\"btn btn-outline-primary btn-sm\">
                                <i class=\"fas fa-file-pdf me-1\"></i>View Marksheet
                            </a>";
            } else {
                $html .= "
                            <button class=\"btn btn-outline-secondary btn-sm\" disabled>
                                <i class=\"fas fa-file-pdf me-1\"></i>No Marksheet
                            </button>";
            }

            if ($candidate['attendance_file']) {
                $html .= "
                            <a href=\"../uploads/candidate_documents/" . htmlspecialchars($candidate['attendance_file']) . "\"
                               target=\"_blank\" class=\"btn btn-outline-primary btn-sm\">
                                <i class=\"fas fa-file-pdf me-1\"></i>View Attendance
                            </a>";
            } else {
                $html .= "
                            <button class=\"btn btn-outline-secondary btn-sm\" disabled>
                                <i class=\"fas fa-file-pdf me-1\"></i>No Attendance
                            </button>";
            }

            $html .= "
                        </div>
                    </div>
                </div>
                
                {$status_info}
            ";
            
            $response['success'] = true;
            $response['html'] = $html;
            $response['candidate'] = $candidate;
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

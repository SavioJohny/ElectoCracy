<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Election Commissioner');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_election':
            $election_type_id = 1; // Force class election type
            $election_year = (int)($_POST['election_year'] ?? 0);
            $class_id = $_POST['class_id'] ? (int)$_POST['class_id'] : null;

            // Validate required fields
            if (!$election_year || !$class_id) {
                throw new Exception('Election year and class selection are required.');
            }

            // Validate election year
            $current_year = (int)date('Y');
            if ($election_year < $current_year || $election_year > $current_year + 1) {
                throw new Exception('Invalid election year.');
            }

            // Validate class exists
            $stmt = $pdo->prepare("
                SELECT c.class_name, d.department_name
                FROM classes c
                    JOIN departments d ON c.department_id = d.department_id 
                    WHERE c.class_id = ?
                ");
                $stmt->execute([$class_id]);
                $class_info = $stmt->fetch();
                
                if (!$class_info) {
                    throw new Exception('Invalid class selected.');
                }
                
                // Check if election already exists for this class and year
                $stmt = $pdo->prepare("
                    SELECT election_id 
                    FROM elections 
                    WHERE election_type_id = ? AND election_year = ? AND class_id = ?
                ");
                $stmt->execute([$election_type_id, $election_year, $class_id]);
                
                if ($stmt->fetch()) {
                    throw new Exception("Class election already exists for {$class_info['class_name']} in {$election_year}.");
                }
                
                $success_message = "Class election created successfully for {$class_info['class_name']} ({$class_info['department_name']}) - {$election_year}.";
            
            // Create the election
            $stmt = $pdo->prepare("
                INSERT INTO elections (election_type_id, election_year, class_id, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$election_type_id, $election_year, $class_id]);
            
            $response['success'] = true;
            $response['message'] = $success_message;
            break;
            
        case 'toggle_status':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $new_status = (int)($_POST['status'] ?? 0);
            
            if (!$election_id) {
                throw new Exception('Invalid election ID.');
            }
            
            // Validate election exists
            $stmt = $pdo->prepare("
                SELECT e.*, et.election_type_name, c.class_name 
                FROM elections e 
                JOIN election_types et ON e.election_type_id = et.election_type_id
                LEFT JOIN classes c ON e.class_id = c.class_id
                WHERE e.election_id = ?
            ");
            $stmt->execute([$election_id]);
            $election = $stmt->fetch();
            
            if (!$election) {
                throw new Exception('Election not found.');
            }
            
            // Update election status
            $stmt = $pdo->prepare("UPDATE elections SET is_active = ? WHERE election_id = ?");
            $stmt->execute([$new_status, $election_id]);
            
            $status_text = $new_status ? 'activated' : 'deactivated';
            $election_name = $election['class_name'] ? 
                "Class election for {$election['class_name']}" : 
                "Union election";
            
            $response['success'] = true;
            $response['message'] = "{$election_name} has been {$status_text}.";
            break;

        case 'preview_bulk_classes':
            $department_id = $_POST['department_id'] ?? '';
            $election_year = (int)($_POST['election_year'] ?? 0);

            if (!$election_year) {
                throw new Exception('Election year is required.');
            }

            // Get class election type ID first
            $stmt = $pdo->prepare("SELECT election_type_id FROM election_types WHERE election_type_name = 'class'");
            $stmt->execute();
            $class_type = $stmt->fetch();

            if (!$class_type) {
                throw new Exception('Class election type not found.');
            }

            $election_type_id = $class_type['election_type_id'];

            // Build query for classes
            $where_conditions = [];
            $params = [$election_year, $election_type_id];

            if (!empty($department_id)) {
                $where_conditions[] = "c.department_id = ?";
                $params[] = $department_id;
            }

            $where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';

            // Get all classes with election status
            $stmt = $pdo->prepare("
                SELECT c.class_id, c.class_name, d.department_name,
                       CASE WHEN e.election_id IS NOT NULL THEN 1 ELSE 0 END as has_election
                FROM classes c
                JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN elections e ON c.class_id = e.class_id
                    AND e.election_year = ?
                    AND e.election_type_id = ?
                $where_clause
                ORDER BY d.department_name, c.class_name
            ");

            $stmt->execute($params);
            $classes = $stmt->fetchAll();

            $response['success'] = true;
            $response['classes'] = $classes;
            break;

        case 'create_bulk_elections':
            $election_year = (int)($_POST['election_year'] ?? 0);
            $department_filter = $_POST['department_filter'] ?? '';

            if (!$election_year) {
                throw new Exception('Election year is required.');
            }

            // Validate election year
            $current_year = (int)date('Y');
            if ($election_year < $current_year || $election_year > $current_year + 1) {
                throw new Exception('Invalid election year.');
            }

            // Get class election type ID
            $stmt = $pdo->prepare("SELECT election_type_id FROM election_types WHERE election_type_name = 'class'");
            $stmt->execute();
            $class_type = $stmt->fetch();

            if (!$class_type) {
                throw new Exception('Class election type not found.');
            }

            $election_type_id = $class_type['election_type_id'];

            // Build query for classes to create elections for
            $where_conditions = [];
            $params = [$election_year, $election_type_id];

            if (!empty($department_filter)) {
                $where_conditions[] = "c.department_id = ?";
                $params[] = $department_filter;
            }

            $where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';

            // Get classes that don't already have elections for this year
            $stmt = $pdo->prepare("
                SELECT c.class_id, c.class_name, d.department_name
                FROM classes c
                JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN elections e ON c.class_id = e.class_id
                    AND e.election_year = ?
                    AND e.election_type_id = ?
                WHERE e.election_id IS NULL
                $where_clause
                ORDER BY d.department_name, c.class_name
            ");

            $stmt->execute($params);
            $eligible_classes = $stmt->fetchAll();

            if (empty($eligible_classes)) {
                throw new Exception('No eligible classes found for election creation.');
            }

            $pdo->beginTransaction();

            try {
                $created_count = 0;
                $failed_classes = [];

                // Create elections for each eligible class
                $insert_stmt = $pdo->prepare("
                    INSERT INTO elections (election_type_id, election_year, class_id, is_active)
                    VALUES (?, ?, ?, 1)
                ");

                foreach ($eligible_classes as $class) {
                    try {
                        $insert_stmt->execute([$election_type_id, $election_year, $class['class_id']]);
                        $created_count++;
                    } catch (Exception $e) {
                        $failed_classes[] = $class['class_name'];
                    }
                }

                if ($created_count === 0) {
                    throw new Exception('Failed to create any elections.');
                }

                $pdo->commit();

                $message = "Successfully created {$created_count} class elections for {$election_year}.";

                if (!empty($failed_classes)) {
                    $message .= " Failed to create elections for: " . implode(', ', $failed_classes);
                }

                $response['success'] = true;
                $response['message'] = $message;
                $response['created_count'] = $created_count;
                $response['failed_count'] = count($failed_classes);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'get_invigilators_and_assignments':
            $class_id = (int)($_POST['class_id'] ?? 0);
            $election_year = (int)($_POST['election_year'] ?? 0);

            if (!$class_id || !$election_year) {
                throw new Exception('Class ID and election year are required.');
            }

            // Get all invigilators excluding those already assigned for this year
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.fname, u.lname, u.email
                FROM users u
                WHERE u.role_id = (SELECT role_id FROM roles WHERE role_name = 'Invigilator')
                AND u.user_id NOT IN (
                    SELECT ica.invigilator_id 
                    FROM invigilator_class_assignments ica 
                    WHERE ica.election_year = ? AND ica.class_id != ?
                )
                ORDER BY u.fname, u.lname
            ");
            $stmt->execute([$election_year, $class_id]);
            $invigilators = $stmt->fetchAll();

            // Get current assignment for this class and year
            $stmt = $pdo->prepare("
                SELECT ica.assignment_id, ica.invigilator_id, u.fname, u.lname, u.email
                FROM invigilator_class_assignments ica
                JOIN users u ON ica.invigilator_id = u.user_id
                WHERE ica.class_id = ? AND ica.election_year = ?
            ");
            $stmt->execute([$class_id, $election_year]);
            $current_assignment = $stmt->fetch();

            $response['success'] = true;
            $response['invigilators'] = $invigilators;
            $response['current_assignment'] = $current_assignment ?: null;
            break;

        case 'assign_invigilator':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $class_id = (int)($_POST['class_id'] ?? 0);
            $election_year = (int)($_POST['election_year'] ?? 0);
            $invigilator_id = (int)($_POST['invigilator_id'] ?? 0);

            if (!$election_id || !$class_id || !$election_year || !$invigilator_id) {
                throw new Exception('All fields are required.');
            }

            // Validate election exists
            $stmt = $pdo->prepare("SELECT election_id FROM elections WHERE election_id = ?");
            $stmt->execute([$election_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Election not found.');
            }

            // Validate invigilator exists
            $stmt = $pdo->prepare("
                SELECT fname, lname
                FROM users
                WHERE user_id = ? AND role_id = (SELECT role_id FROM roles WHERE role_name = 'Invigilator')
            ");
            $stmt->execute([$invigilator_id]);
            $invigilator = $stmt->fetch();

            if (!$invigilator) {
                throw new Exception('Invalid invigilator selected.');
            }

            // Check if assignment already exists
            $stmt = $pdo->prepare("
                SELECT assignment_id
                FROM invigilator_class_assignments
                WHERE class_id = ? AND election_year = ?
            ");
            $stmt->execute([$class_id, $election_year]);
            $existing_assignment = $stmt->fetch();

            if ($existing_assignment) {
                // Update existing assignment
                $stmt = $pdo->prepare("
                    UPDATE invigilator_class_assignments
                    SET invigilator_id = ?
                    WHERE assignment_id = ?
                ");
                $stmt->execute([$invigilator_id, $existing_assignment['assignment_id']]);
                $action_text = 'updated';
            } else {
                // Create new assignment
                $stmt = $pdo->prepare("
                    INSERT INTO invigilator_class_assignments (invigilator_id, class_id, election_year)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$invigilator_id, $class_id, $election_year]);
                $action_text = 'assigned';
            }

            $invigilator_name = $invigilator['fname'] . ' ' . $invigilator['lname'];

            $response['success'] = true;
            $response['message'] = "Invigilator {$invigilator_name} has been {$action_text} successfully.";
            break;

        case 'remove_invigilator_assignment':
            $assignment_id = (int)($_POST['assignment_id'] ?? 0);

            if (!$assignment_id) {
                throw new Exception('Assignment ID is required.');
            }

            // Get assignment details before deletion
            $stmt = $pdo->prepare("
                SELECT ica.*, u.fname, u.lname, c.class_name
                FROM invigilator_class_assignments ica
                JOIN users u ON ica.invigilator_id = u.user_id
                JOIN classes c ON ica.class_id = c.class_id
                WHERE ica.assignment_id = ?
            ");
            $stmt->execute([$assignment_id]);
            $assignment = $stmt->fetch();

            if (!$assignment) {
                throw new Exception('Assignment not found.');
            }

            // Delete the assignment
            $stmt = $pdo->prepare("DELETE FROM invigilator_class_assignments WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);

            $invigilator_name = $assignment['fname'] . ' ' . $assignment['lname'];
            $class_name = $assignment['class_name'];

            $response['success'] = true;
            $response['message'] = "Removed {$invigilator_name} from {$class_name} assignment.";
            break;

        case 'delete_election':
            $election_id = (int)($_POST['election_id'] ?? 0);

            if (!$election_id) {
                throw new Exception('Election ID is required.');
            }

            // Get election details for verification and response message
            $stmt = $pdo->prepare("
                SELECT e.*, et.election_type_name, c.class_name, d.department_name,
                       COUNT(DISTINCT cand.candidate_id) as candidate_count,
                       COUNT(DISTINCT v.vote_id) as vote_count
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                LEFT JOIN classes c ON e.class_id = c.class_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN candidates cand ON e.election_id = cand.election_id
                LEFT JOIN votes v ON e.election_id = v.election_id
                WHERE e.election_id = ?
                GROUP BY e.election_id
            ");
            $stmt->execute([$election_id]);
            $election = $stmt->fetch();

            if (!$election) {
                throw new Exception('Election not found.');
            }

            // Start transaction for safe deletion
            $pdo->beginTransaction();

            try {
                // Delete in proper order to respect foreign key constraints

                // 1. Delete votes first
                $stmt = $pdo->prepare("DELETE FROM votes WHERE election_id = ?");
                $stmt->execute([$election_id]);
                $deleted_votes = $stmt->rowCount();

                // 2. Delete candidates
                $stmt = $pdo->prepare("DELETE FROM candidates WHERE election_id = ?");
                $stmt->execute([$election_id]);
                $deleted_candidates = $stmt->rowCount();

                // 3. Delete invigilator assignments (if any)
                $stmt = $pdo->prepare("DELETE FROM invigilator_class_assignments WHERE class_id = ? AND election_year = ?");
                $stmt->execute([$election['class_id'], $election['election_year']]);
                $deleted_assignments = $stmt->rowCount();

                // 4. Delete election results (if any) - Skip as table doesn't exist
                $deleted_results = 0;

                // 5. Finally delete the election itself
                $stmt = $pdo->prepare("DELETE FROM elections WHERE election_id = ?");
                $stmt->execute([$election_id]);

                // Commit the transaction
                $pdo->commit();

                // Prepare response message
                $election_type = ucfirst($election['election_type_name']);
                $election_scope = $election['class_name'] ?: 'Union Wide';

                $response['success'] = true;
                $response['message'] = "Successfully deleted {$election_type} Election ({$election_scope}).";

                // Add details about what was deleted
                $details = [];
                if ($deleted_votes > 0) $details[] = "{$deleted_votes} votes";
                if ($deleted_candidates > 0) $details[] = "{$deleted_candidates} candidates";
                if ($deleted_assignments > 0) $details[] = "{$deleted_assignments} invigilator assignments";
                if ($deleted_results > 0) $details[] = "{$deleted_results} results";

                if (!empty($details)) {
                    $response['message'] .= " Also removed: " . implode(', ', $details) . ".";
                }

            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw new Exception('Failed to delete election: ' . $e->getMessage());
            }
            break;

        case 'mass_delete_elections':
            $election_ids_json = $_POST['election_ids'] ?? '';

            if (empty($election_ids_json)) {
                throw new Exception('No elections selected for deletion.');
            }

            $election_ids = json_decode($election_ids_json, true);

            if (!is_array($election_ids) || empty($election_ids)) {
                throw new Exception('Invalid election IDs provided.');
            }

            // Validate all election IDs are integers
            $election_ids = array_map('intval', $election_ids);
            $election_ids = array_filter($election_ids, function($id) { return $id > 0; });

            if (empty($election_ids)) {
                throw new Exception('No valid election IDs provided.');
            }

            // Get election details before deletion
            $placeholders = str_repeat('?,', count($election_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT e.election_id, e.election_year, e.class_id, et.election_type_name, c.class_name,
                       COUNT(DISTINCT cand.candidate_id) as candidate_count,
                       COUNT(DISTINCT v.vote_id) as vote_count
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                LEFT JOIN classes c ON e.class_id = c.class_id
                LEFT JOIN candidates cand ON e.election_id = cand.election_id
                LEFT JOIN votes v ON e.election_id = v.election_id
                WHERE e.election_id IN ($placeholders)
                GROUP BY e.election_id
            ");
            $stmt->execute($election_ids);
            $elections_to_delete = $stmt->fetchAll();

            if (count($elections_to_delete) !== count($election_ids)) {
                throw new Exception('Some elections were not found or you do not have permission to delete them.');
            }

            // Start transaction for safe mass deletion
            $pdo->beginTransaction();

            try {
                $total_deleted_votes = 0;
                $total_deleted_candidates = 0;
                $total_deleted_assignments = 0;
                $total_deleted_results = 0;
                $deleted_elections = [];

                foreach ($elections_to_delete as $election) {
                    $election_id = $election['election_id'];

                    // Delete in proper order to respect foreign key constraints

                    // 1. Delete votes
                    $stmt = $pdo->prepare("DELETE FROM votes WHERE election_id = ?");
                    $stmt->execute([$election_id]);
                    $total_deleted_votes += $stmt->rowCount();

                    // 2. Delete candidates
                    $stmt = $pdo->prepare("DELETE FROM candidates WHERE election_id = ?");
                    $stmt->execute([$election_id]);
                    $total_deleted_candidates += $stmt->rowCount();

                    // 3. Delete invigilator assignments (if any)
                    if ($election['class_id']) {
                        $stmt = $pdo->prepare("DELETE FROM invigilator_class_assignments WHERE class_id = ? AND election_year = ?");
                        $stmt->execute([$election['class_id'], $election['election_year']]);
                        $total_deleted_assignments += $stmt->rowCount();
                    }

                    // 4. Delete election results (if any) - Skip as table doesn't exist
                    $total_deleted_results += 0;

                    // 5. Delete the election itself
                    $stmt = $pdo->prepare("DELETE FROM elections WHERE election_id = ?");
                    $stmt->execute([$election_id]);

                    // Track deleted election for response
                    $election_type = ucfirst($election['election_type_name']);
                    $election_scope = $election['class_name'] ?: 'Union Wide';
                    $deleted_elections[] = "{$election_type} Election ({$election_scope})";
                }

                // Commit the transaction
                $pdo->commit();

                // Prepare response message
                $count = count($deleted_elections);
                $response['success'] = true;
                $response['message'] = "Successfully deleted {$count} elections.";

                // Add details about what was deleted
                $details = [];
                if ($total_deleted_votes > 0) $details[] = "{$total_deleted_votes} votes";
                if ($total_deleted_candidates > 0) $details[] = "{$total_deleted_candidates} candidates";
                if ($total_deleted_assignments > 0) $details[] = "{$total_deleted_assignments} invigilator assignments";
                if ($total_deleted_results > 0) $details[] = "{$total_deleted_results} results";

                if (!empty($details)) {
                    $response['message'] .= " Also removed: " . implode(', ', $details) . ".";
                }

                $response['deleted_count'] = $count;
                $response['deleted_elections'] = $deleted_elections;

            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw new Exception('Failed to delete elections: ' . $e->getMessage());
            }
            break;

        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

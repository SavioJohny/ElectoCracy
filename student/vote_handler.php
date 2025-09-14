<?php
session_start();
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

requireRole('Student');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'cast_vote':
            $election_id = (int)($_POST['election_id'] ?? 0);
            $gender_category = $_POST['gender_category'] ?? '';
            $vote_choice = $_POST['vote_choice'] ?? '';

            if (!$election_id || !$gender_category || !$vote_choice) {
                throw new Exception('Election, gender category, and vote choice are required.');
            }

            if (!in_array($gender_category, ['girls', 'boys'])) {
                throw new Exception('Invalid gender category.');
            }
            
            // Get student information
            $stmt = $pdo->prepare("
                SELECT u.*, c.class_id
                FROM users u
                JOIN classes c ON u.class_id = c.class_id
                WHERE u.user_id = ? AND u.role_id = 1
            ");
            $stmt->execute([$user_id]);
            $student = $stmt->fetch();

            if (!$student) {
                throw new Exception('Student not found.');
            }

            // Check if student is disqualified from this election
            $stmt = $pdo->prepare("
                SELECT disqualification_id
                FROM election_disqualifications
                WHERE student_id = ? AND election_id = ?
            ");
            $stmt->execute([$user_id, $election_id]);
            $disqualification = $stmt->fetch();

            if ($disqualification) {
                throw new Exception('You are disqualified from participating in this election.');
            }
            
            // Check if voting_status column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM elections LIKE 'voting_status'");
            $voting_column_exists = $stmt->fetch();

            // Validate election
            $select_fields = "e.*, et.election_type_name";
            if ($voting_column_exists) {
                $select_fields .= ", e.voting_status";
            }

            $stmt = $pdo->prepare("
                SELECT {$select_fields}
                FROM elections e
                JOIN election_types et ON e.election_type_id = et.election_type_id
                WHERE e.election_id = ? AND e.is_active = 1
            ");
            $stmt->execute([$election_id]);
            $election = $stmt->fetch();

            if (!$election) {
                throw new Exception('Election not found or not active.');
            }

            // For class elections, check voting status
            if ($election['election_type_name'] === 'class' && $voting_column_exists) {
                $voting_status = $election['voting_status'] ?? 'active';
                if ($voting_status !== 'active') {
                    $status_messages = [
                        'not_started' => 'Voting has not started yet.',
                        'paused' => 'Voting is currently paused.',
                        'ended' => 'Voting has ended.'
                    ];
                    throw new Exception($status_messages[$voting_status] ?? 'Voting is not currently available.');
                }
            }
            
            // For class elections, validate student belongs to the class
            if ($election['election_type_name'] === 'class' && $election['class_id'] != $student['class_id']) {
                throw new Exception('You can only vote in elections for your own class.');
            }
            
            // Process vote choice
            $candidate_id = null;
            $candidate = null;

            if ($vote_choice === 'nil') {
                // NIL vote - no candidate validation needed
                $candidate_id = null;
            } else if (strpos($vote_choice, 'candidate_') === 0) {
                // Extract candidate ID from choice
                $candidate_id = (int)str_replace('candidate_', '', $vote_choice);

                // Validate candidate
                $stmt = $pdo->prepare("
                    SELECT c.*, u.fname, u.lname, u.gender, p.position_name, p.position_type
                    FROM candidates c
                    JOIN users u ON c.user_id = u.user_id
                    JOIN positions p ON c.position_id = p.position_id
                    WHERE c.candidate_id = ? AND c.election_id = ? AND c.is_approved = 'approved'
                ");
                $stmt->execute([$candidate_id, $election_id]);
                $candidate = $stmt->fetch();

                if (!$candidate) {
                    throw new Exception('Candidate not found or not approved for this election.');
                }

                // Validate candidate gender matches gender category
                $expected_gender = $gender_category === 'girls' ? 'F' : 'M';
                if ($candidate['gender'] !== $expected_gender) {
                    throw new Exception('Candidate gender does not match the gender category.');
                }
            } else {
                throw new Exception('Invalid vote choice.');
            }
            
            // Check if student has already voted for this gender category in this election
            $stmt = $pdo->prepare("
                SELECT v.vote_id
                FROM votes v
                WHERE v.voter_id = ? AND v.election_id = ? AND v.gender_category = ?
            ");
            $stmt->execute([$user_id, $election_id, $gender_category]);

            if ($stmt->fetch()) {
                $gender_text = $gender_category === 'girls' ? 'Girls' : 'Boys';
                throw new Exception("You have already voted for {$gender_text} Representative in this election.");
            }

            // Determine vote_type: NULL for NIL votes, 'valid' for candidate votes
            $vote_type = ($vote_choice === 'nil') ? null : 'valid';

            // Cast the vote
            $stmt = $pdo->prepare("
                INSERT INTO votes (voter_id, election_id, candidate_id, vote_type, gender_category)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $election_id, $candidate_id, $vote_type, $gender_category]);

            // Check if all students in the class have voted and auto-end voting if needed
            if ($election['election_type_name'] === 'class' && isset($election['voting_status']) && $election['voting_status'] === 'active') {
                checkAndAutoEndVoting($pdo, $election_id, $election['class_id']);
            }

            // Generate success message
            $gender_text = $gender_category === 'girls' ? 'Girls' : 'Boys';

            if ($vote_choice === 'nil') {
                $response['message'] = "Your NIL vote for {$gender_text} Representative has been recorded successfully.";
            } else {
                $candidate_name = $candidate['fname'] . ' ' . $candidate['lname'];
                $response['message'] = "Your vote for {$candidate_name} as {$gender_text} Representative has been recorded successfully.";
            }

            $response['success'] = true;
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

/**
 * Check if all students in a class have voted and automatically end voting if so
 */
function checkAndAutoEndVoting($pdo, $election_id, $class_id) {
    try {
        // Get total number of qualified students in the class (excluding disqualified)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN election_disqualifications ed ON u.user_id = ed.student_id
                AND ed.election_id = ?
            WHERE u.class_id = ? AND r.role_name = 'Student' AND ed.disqualification_id IS NULL
        ");
        $stmt->execute([$election_id, $class_id]);
        $total_students = $stmt->fetchColumn();

        // Get number of students who have voted for both gender categories
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT voter_id) as voted_students
            FROM votes
            WHERE election_id = ?
            GROUP BY voter_id
            HAVING COUNT(DISTINCT gender_category) = 2
        ");
        $stmt->execute([$election_id]);
        $fully_voted_students = $stmt->rowCount();

        // If all students have voted for both categories, auto-end the voting
        if ($total_students > 0 && $fully_voted_students >= $total_students) {
            $stmt = $pdo->prepare("UPDATE elections SET voting_status = 'ended' WHERE election_id = ?");
            $stmt->execute([$election_id]);

            // Log the auto-end action
            error_log("Auto-ended voting for election {$election_id} - all {$total_students} students have voted");
        }

    } catch (Exception $e) {
        // Log error but don't throw - voting should still succeed even if auto-end fails
        error_log("Error in checkAndAutoEndVoting: " . $e->getMessage());
    }
}

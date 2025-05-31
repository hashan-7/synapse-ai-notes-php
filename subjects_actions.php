<?php
session_start();
header('Content-Type: application/json'); // Ensure JSON response

require_once 'config.php';   // Go up one directory to find includes
require_once 'db_connect.php'; // Provides $pdo

$response = ['success' => false, 'message' => 'Invalid request.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to handle both GET and POST for action

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    if ($action === 'add_subject' || $action === 'update_subject') {
        $subject_name = isset($_POST['name']) ? trim($_POST['name']) : null;
        $subject_id = isset($_POST['id']) ? (int)$_POST['id'] : null;

        if (empty($subject_name)) {
            $response['message'] = 'Subject name cannot be empty.';
        } else {
            try {
                // Check for duplicates before adding or updating to a new name
                $checkStmt = $pdo->prepare("SELECT id FROM subjects WHERE user_id = :user_id AND name = :name AND (:id IS NULL OR id != :id)");
                $checkParams = ['user_id' => $current_user_id, 'name' => $subject_name, 'id' => ($action === 'update_subject' ? $subject_id : null)];
                $checkStmt->execute($checkParams);

                if ($checkStmt->fetch()) {
                    $response['message'] = 'A subject with this name already exists.';
                } else {
                    if ($action === 'add_subject') {
                        $stmt = $pdo->prepare("INSERT INTO subjects (user_id, name, created_at, updated_at) VALUES (:user_id, :name, NOW(), NOW())");
                        $stmt->execute(['user_id' => $current_user_id, 'name' => $subject_name]);
                        $new_subject_id = $pdo->lastInsertId();
                        $response = ['success' => true, 'message' => 'Subject added successfully!', 'subject' => ['id' => $new_subject_id, 'name' => $subject_name]];
                    } elseif ($action === 'update_subject' && $subject_id) {
                        $stmt = $pdo->prepare("UPDATE subjects SET name = :name, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
                        $stmt->execute(['name' => $subject_name, 'id' => $subject_id, 'user_id' => $current_user_id]);
                        if ($stmt->rowCount() > 0) {
                           $response = ['success' => true, 'message' => 'Subject updated successfully!', 'subject' => ['id' => $subject_id, 'name' => $subject_name]];
                        } else {
                            $response['message'] = 'Subject not found or no changes made.';
                        }
                    } else {
                         $response['message'] = 'Invalid action or missing subject ID for update.';
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error in subjects_actions.php: " . $e->getMessage());
                $response['message'] = 'Database error: Could not process subject. ' . $e->getMessage();
                 http_response_code(500);
            }
        }
    } elseif ($action === 'delete_subject') {
        $subject_id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        if ($subject_id) {
            try {
                // Optional: Check if notes are associated and handle accordingly or let DB constraints handle it.
                // For ON DELETE SET NULL, notes will have subject_id set to NULL.
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id AND user_id = :user_id");
                $stmt->execute(['id' => $subject_id, 'user_id' => $current_user_id]);
                if ($stmt->rowCount() > 0) {
                    $response = ['success' => true, 'message' => 'Subject deleted successfully.'];
                } else {
                    $response['message'] = 'Subject not found or you do not have permission to delete it.';
                    http_response_code(404); // Not Found or Forbidden
                }
            } catch (PDOException $e) {
                error_log("Database error deleting subject: " . $e->getMessage());
                $response['message'] = 'Database error: Could not delete subject. ' . $e->getMessage();
                http_response_code(500);
            }
        } else {
            $response['message'] = 'Subject ID not provided for deletion.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_all') {
    try {
        $stmtSubjects = $pdo->prepare("SELECT id, name FROM subjects WHERE user_id = :user_id ORDER BY name ASC");
        $stmtSubjects->execute(['user_id' => $current_user_id]);
        $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
        $response = ['success' => true, 'subjects' => $subjects];
    } catch (PDOException $e) {
        error_log("Error fetching subjects: " . $e->getMessage());
        $response['message'] = 'Database error: Could not fetch subjects. ' . $e->getMessage();
        http_response_code(500);
    }
}

echo json_encode($response);
exit;
?>

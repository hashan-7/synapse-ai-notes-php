<?php
session_start();
require_once 'config.php';      // Assuming config.php is in the same root folder
require_once 'db_connect.php';  // Assuming db_connect.php is in the same root folder

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // For a script like this, usually redirecting is fine.
    // If it were an AJAX-only endpoint, a JSON error would be better.
    $_SESSION['message'] = 'Please login to perform this action.';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_id_to_delete'])) {
    $note_id_to_delete = (int)$_POST['note_id_to_delete'];
    error_log("PHP (delete_note_action.php): Attempting to delete note ID: " . $note_id_to_delete . " for user ID: " . $current_user_id);

    // Optional: Add CSRF token validation here if you implement CSRF protection in your forms

    if ($note_id_to_delete > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $note_id_to_delete, 'user_id' => $current_user_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = 'Note deleted successfully.';
                $_SESSION['message_type'] = 'success';
                error_log("PHP (delete_note_action.php): Note ID " . $note_id_to_delete . " deleted successfully for user ID: " . $current_user_id);
            } else {
                $_SESSION['message'] = 'Note not found or you do not have permission to delete it.';
                $_SESSION['message_type'] = 'danger';
                error_log("PHP (delete_note_action.php): Failed to delete note ID " . $note_id_to_delete . ". Row count 0 or permission issue for user ID: " . $current_user_id);
            }
        } catch (PDOException $e) {
            error_log("PHP (delete_note_action.php): Database error deleting note: " . $e->getMessage());
            $_SESSION['message'] = 'Database error: Could not delete note.';
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        $_SESSION['message'] = 'Invalid Note ID for deletion.';
        $_SESSION['message_type'] = 'danger';
        error_log("PHP (delete_note_action.php): Invalid note_id_to_delete received: " . $note_id_to_delete);
    }
} else {
    // If not a POST request or note_id_to_delete is not set
    $_SESSION['message'] = 'Invalid request to delete note.';
    $_SESSION['message_type'] = 'danger';
    error_log("PHP (delete_note_action.php): Invalid request method or missing note_id_to_delete. Method: ".$_SERVER['REQUEST_METHOD']);
}

header('Location: dashboard.php'); // Always redirect to dashboard after delete attempt
exit;
?>

<?php
session_start();
require_once 'config.php';
require_once 'db_connect.php'; // Provides $pdo

$message = 'An unexpected error occurred.';
$message_type = 'danger';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please login to perform this action.';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Optional: Add CSRF token validation here for security

    error_log("PHP (delete_account_action.php): Account deletion initiated for user ID: " . $current_user_id);

    try {
        // Start a transaction
        $pdo->beginTransaction();

        // 1. Delete notes associated with the user
        // (ON DELETE CASCADE on notes.user_id and notes.subject_id should handle this if subjects are also deleted,
        // but explicit deletion can be safer or handle specific logic)
        // If subjects.user_id has ON DELETE CASCADE, deleting the user will delete their subjects,
        // and if notes.subject_id has ON DELETE SET NULL or CASCADE, it will act accordingly.
        // Our DB schema for notes has ON DELETE CASCADE for user_id and ON DELETE SET NULL for subject_id.
        // Our DB schema for subjects has ON DELETE CASCADE for user_id.
        // So, deleting the user from app_users should cascade and delete their subjects,
        // which in turn will set subject_id to NULL in notes. Then we delete notes by user_id.

        $stmt_delete_notes = $pdo->prepare("DELETE FROM notes WHERE user_id = :user_id");
        $stmt_delete_notes->execute(['user_id' => $current_user_id]);
        error_log("PHP (delete_account_action.php): Deleted " . $stmt_delete_notes->rowCount() . " notes for user ID: " . $current_user_id);
        
        // 2. Delete subjects associated with the user
        $stmt_delete_subjects = $pdo->prepare("DELETE FROM subjects WHERE user_id = :user_id");
        $stmt_delete_subjects->execute(['user_id' => $current_user_id]);
        error_log("PHP (delete_account_action.php): Deleted " . $stmt_delete_subjects->rowCount() . " subjects for user ID: " . $current_user_id);

        // 3. Delete the user from app_users table
        $stmt_delete_user = $pdo->prepare("DELETE FROM app_users WHERE id = :user_id");
        $stmt_delete_user->execute(['user_id' => $current_user_id]);

        if ($stmt_delete_user->rowCount() > 0) {
            // Commit transaction
            $pdo->commit();
            
            // Clear all session data and destroy the session
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            // Start a new session just to pass the success message to index.php
            session_start();
            $_SESSION['message'] = 'Your account and all associated data have been successfully deleted.';
            $_SESSION['message_type'] = 'success';
            error_log("PHP (delete_account_action.php): Account deleted successfully for user ID: " . $current_user_id);
            header('Location: index.php');
            exit;
        } else {
            // Rollback transaction if user deletion failed (e.g., user not found, though unlikely if session was valid)
            $pdo->rollBack();
            $_SESSION['message'] = 'Failed to delete account. User not found.';
            $_SESSION['message_type'] = 'danger';
            error_log("PHP (delete_account_action.php): Failed to delete user account (user not found in DB) for user ID: " . $current_user_id);
        }

    } catch (PDOException $e) {
        // Rollback transaction on any DB error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("PHP (delete_account_action.php): Database error during account deletion for user ID " . $current_user_id . ": " . $e->getMessage());
        $_SESSION['message'] = 'Database error: Could not delete your account. Please try again.';
        $_SESSION['message_type'] = 'danger';
    }
} else {
    // If not a POST request
    $_SESSION['message'] = 'Invalid request method for account deletion.';
    $_SESSION['message_type'] = 'danger';
    error_log("PHP (delete_account_action.php): Invalid request method. Method: ".$_SERVER['REQUEST_METHOD']);
}

// Redirect back to profile page if deletion was not fully processed or if it wasn't a POST request
header('Location: user_profile.php');
exit;
?>

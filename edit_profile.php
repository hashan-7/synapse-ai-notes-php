<?php
session_start();
require_once 'config.php';
require_once 'db_connect.php'; // Provides $pdo

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=' . urlencode('Please login to access this page.'));
    exit;
}

$current_user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Fetch current user details to pre-fill the form
$user_data = null;
try {
    $stmt = $pdo->prepare("SELECT name, email FROM app_users WHERE id = :user_id");
    $stmt->execute(['user_id' => $current_user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        // Should not happen if session is valid
        $_SESSION['message'] = 'User data not found.';
        $_SESSION['message_type'] = 'danger';
        header('Location: user_profile.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user data for edit: " . $e->getMessage());
    $message = 'Error loading your profile data.';
    $message_type = 'danger';
    // Allow page to load but show error
}


// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';
    // Email editing can be added here if desired, but requires more validation (e.g., uniqueness)
    // $new_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';

    if (empty($new_name)) {
        $message = 'Name cannot be empty.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE app_users SET name = :name, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute(['name' => $new_name, 'user_id' => $current_user_id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['user_name'] = $new_name; // Update session with new name
                $_SESSION['message'] = 'Profile updated successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: user_profile.php'); // Redirect back to profile page
                exit;
            } else {
                $message = 'No changes were made to your profile or an error occurred.';
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            error_log("Database error updating profile: " . $e->getMessage());
            $message = 'Database error: Could not update profile.';
            $message_type = 'danger';
        }
    }
}

// Retrieve and clear session messages (if redirected here with a message)
if (isset($_SESSION['message']) && empty($message) ) { // Only if no form submission message is set
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_type']) && empty($message_type)) {
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message_type']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Synapse AI Notes (PHP)</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;500;600&display=swap');
        body { font-family: 'Lexend', sans-serif; color: #cdd5e0; background-color: #0a101f; }
        h1, h2, h3, .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .bg-main-gradient { background: linear-gradient(180deg, #111827 0%, #0a101f 40%, #080c17 100%); min-height: 100vh; }
        .form-container-custom { background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(10px) saturate(130%); border: 1px solid rgba(55, 65, 81, 0.4); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); }
        .form-control-custom { background-color: rgba(31, 41, 55, 0.8); border: 1px solid rgba(55, 65, 81, 0.5); color: #cdd5e0; }
        .form-control-custom:focus { border-color: #6366f1; box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); background-color: rgba(31, 41, 55, 0.9); color: #cdd5e0; }
        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .btn-secondary-custom { background-color: rgba(55, 65, 81, 0.6); border-color: rgba(71, 85, 105, 0.6); color: #cbd5e1;}
        .btn-secondary-custom:hover { background-color: rgba(71, 85, 105, 0.8); border-color: rgba(100, 116, 139, 0.8); }
        .alert-dismissible .close { padding: 0.75rem 1.0rem; }
    </style>
</head>
<body class="bg-main-gradient py-4 py-md-5 d-flex align-items-center justify-content-center">
    <div class="form-container-custom container" style="max-width: 600px;">
        <div class="p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-indigo-300 font-orbitron">Edit Your Profile</h1>
                <a href="user_profile.php" class="btn btn-sm btn-outline-light d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-1" viewBox="0 0 16 16">
                      <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z"/>
                    </svg>
                    Back to Profile
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert" id="editProfileAlert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($user_data): ?>
            <form id="editProfileForm" method="POST" action="edit_profile.php">
                <div class="form-group">
                    <label for="userNameInput" class="text-slate-400 small">Name</label>
                    <input type="text" class="form-control form-control-custom" id="userNameInput" name="user_name" 
                           value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="userEmailInput" class="text-slate-400 small">Email (Cannot be changed)</label>
                    <input type="email" class="form-control form-control-custom" id="userEmailInput" name="user_email" 
                           value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly disabled>
                </div>
                
                <div class="pt-3 d-flex justify-content-end">
                    <a href="user_profile.php" class="btn btn-secondary-custom mr-2">Cancel</a>
                    <button type="submit" class="btn btn-primary-custom">Update Profile</button>
                </div>
            </form>
            <?php elseif(empty($message)): // Only show if no specific error message is already set ?>
                 <div class="alert alert-warning text-center">Could not load profile data for editing.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto-dismiss alerts from PHP after 3 seconds
        window.setTimeout(function() {
            if (window.jQuery && $.fn.alert) {
                 $("#editProfileAlert .alert").fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }
        }, 3000);
    </script>
</body>
</html>

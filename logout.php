<?php
session_start(); // Start the session to access session variables

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Set a logout message to be displayed on the index page
session_start(); // Start a new session just to pass the message
$_SESSION['message'] = 'You have been logged out successfully.';
$_SESSION['message_type'] = 'success';


// Redirect to the login page (index.php)
header("Location: index.php");
exit;
?>

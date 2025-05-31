<?php
session_start(); 

require_once 'vendor/autoload.php'; // Composer autoload
require_once 'config.php';  // Your config file with credentials
require_once 'db_connect.php'; // Your database connection file ($pdo object)

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI); // Must match the one in Google Console & config.php

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        if (isset($token['error'])) {
            // Log the error from Google
            error_log("Google Token Error: " . $token['error'] . " - " . ($token['error_description'] ?? 'No description'));
            // Redirect to login page with a generic error message
            header('Location: index.php?error=' . urlencode('Google authentication failed. Please try again.'));
            exit;
        }
        $client->setAccessToken($token);

        // Get profile info from Google
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $google_user_id = $google_account_info->getId();
        $email = $google_account_info->getEmail();
        $name = $google_account_info->getName();
        $profile_picture_url = $google_account_info->getPicture();

        // Check if user exists in our database by google_user_id
        $stmt = $pdo->prepare("SELECT * FROM app_users WHERE google_user_id = :google_user_id");
        $stmt->execute(['google_user_id' => $google_user_id]);
        $user = $stmt->fetch();

        $userIdInDb = null;

        if ($user) {
            // User exists with this Google ID, update their details if changed
            $userIdInDb = $user['id'];
            $updateStmt = $pdo->prepare("UPDATE app_users SET name = :name, email = :email, profile_picture_url = :purl, updated_at = NOW() WHERE id = :id");
            $updateStmt->execute([
                'name' => $name,
                'email' => $email, 
                'purl' => $profile_picture_url,
                'id' => $userIdInDb
            ]);
        } else {
            // No user with this Google ID. Check if a user exists with this email (e.g., signed up differently before)
            $stmtEmail = $pdo->prepare("SELECT * FROM app_users WHERE email = :email");
            $stmtEmail->execute(['email' => $email]);
            $userByEmail = $stmtEmail->fetch();

            if ($userByEmail) {
                // Email exists, update this record to link the Google ID
                $userIdInDb = $userByEmail['id'];
                $updateStmt = $pdo->prepare("UPDATE app_users SET google_user_id = :google_user_id, name = :name, profile_picture_url = :purl, updated_at = NOW() WHERE id = :id");
                $updateStmt->execute([
                    'google_user_id' => $google_user_id,
                    'name' => $name,
                    'purl' => $profile_picture_url,
                    'id' => $userIdInDb
                ]);
            } else {
                // Truly new user, insert into database
                $insertStmt = $pdo->prepare("INSERT INTO app_users (google_user_id, email, name, profile_picture_url, created_at, updated_at) VALUES (:google_user_id, :email, :name, :purl, NOW(), NOW())");
                $insertStmt->execute([
                    'google_user_id' => $google_user_id,
                    'email' => $email,
                    'name' => $name,
                    'purl' => $profile_picture_url
                ]);
                $userIdInDb = $pdo->lastInsertId();
            }
        }

        // Set session variables
        $_SESSION['user_id'] = $userIdInDb;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['profile_picture_url'] = $profile_picture_url;
        $_SESSION['google_user_id'] = $google_user_id; // Store google_user_id in session as well

        // Redirect to the main dashboard page
        header('Location: dashboard.php'); // Or your main application page
        exit;

    } catch (Exception $e) {
        // Handle any other exceptions during the process
        error_log("Google OAuth Callback Exception: " . $e->getMessage());
        header('Location: index.php?error=' . urlencode('Authentication process encountered an issue. Details: ' . $e->getMessage()));
        exit;
    }
} else {
    // No authorization code parameter found in URL, redirect to login
    header('Location: index.php?error=' . urlencode('Google authentication was cancelled or failed.'));
    exit;
}
?>

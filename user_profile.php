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
$user_profile_data = null;
$message = '';
$message_type = '';

// Fetch user details from the database
try {
    $stmt = $pdo->prepare("SELECT id, name, email, google_user_id, profile_picture_url, created_at FROM app_users WHERE id = :user_id");
    $stmt->execute(['user_id' => $current_user_id]);
    $user_profile_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_profile_data) {
        $_SESSION['message'] = 'User profile not found.';
        $_SESSION['message_type'] = 'danger';
        unset($_SESSION['user_id']);
        unset($_SESSION['user_name']);
        unset($_SESSION['user_email']);
        unset($_SESSION['profile_picture_url']);
        header('Location: index.php?error=' . urlencode('User profile error. Please login again.'));
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $message = 'Error fetching your profile data.';
    $message_type = 'danger';
}

function formatDateForProfile($dateString) {
    if (!$dateString) return 'N/A';
    try {
        $date = new DateTime($dateString);
        return $date->format('F j, Y'); // e.g., May 29, 2025
    } catch (Exception $e) {
        return $dateString; 
    }
}

// Retrieve and clear session messages (if any)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_type'])) {
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message_type']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Synapse AI Notes (PHP)</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;500;600&display=swap');
        body { font-family: 'Lexend', sans-serif; color: #cdd5e0; background-color: #0a101f; }
        h1, h2, h3, .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .bg-main-gradient { background: linear-gradient(180deg, #111827 0%, #0a101f 40%, #080c17 100%); min-height: 100vh; }
        .sidebar { background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(10px) saturate(120%); border-right: 1px solid rgba(55, 65, 81, 0.4); padding: 1rem; height: 100vh; position: fixed; width: 100px; }
        .sidebar .nav-link svg { width: 28px; height: 28px; color: #9ca3af; transition: color 0.2s ease-in-out, transform 0.2s ease-out; }
        .sidebar .nav-link:hover svg { color: #a5b4fc; transform: scale(1.1); }
        .sidebar .profile-icon.active svg { color: #818cf8; } 
        .sidebar .logout-icon:hover svg { color: #f87171; }
        .main-content { margin-left: 100px; padding: 2rem; width: calc(100% - 100px); }
        
        .profile-card-custom { background-color: rgba(31, 41, 55, 0.7); border: 1px solid rgba(55, 65, 81, 0.35); color: #cdd5e0; border-radius: 0.75rem; }
        .avatar-img { width: 120px; height: 120px; border-radius: 50%; border: 3px solid rgba(139, 92, 246, 0.5); object-fit: cover;}
        .avatar-placeholder-profile { width: 120px; height: 120px; border-radius: 50%; background-color: #4a5568; color: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 600; border: 3px solid rgba(139, 92, 246, 0.4); }
        .info-group { margin-bottom: 1.5rem; }
        .info-label { font-size: 0.8rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; }
        .info-value { font-size: 1rem; color: #e5e7eb; }

        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .btn-secondary-custom { background-color: rgba(55, 65, 81, 0.6); border-color: rgba(71, 85, 105, 0.6); color: #cbd5e1;}
        .btn-secondary-custom:hover { background-color: rgba(71, 85, 105, 0.8); border-color: rgba(100, 116, 139, 0.8); }
        .btn-danger-custom { background-color: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #f87171;}
        .btn-danger-custom:hover { background-color: rgba(220, 38, 38, 0.4); border-color: rgba(220, 38, 38, 0.5); color: #fca5a5;}
        .alert-dismissible .close { padding: 0.75rem 1.0rem; }
    </style>
</head>
<body class="bg-main-gradient">

    <div class="d-flex">
        <nav class="sidebar d-flex flex-column align-items-center py-3">
            <a href="dashboard.php" class="mb-4" title="Dashboard Home"> 
                <svg style="width:48px; height:48px;" class="text-indigo-400 hover:text-indigo-300" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                     <path d="M32 5C16.475 5 5 16.475 5 32C5 47.525 16.475 59 32 59C47.525 59 59 47.525 59 32C59 16.475 47.525 5 32 5Z" stroke="currentColor" stroke-width="3" stroke-miterlimit="10" stroke-opacity="0.5"/>
                    <path d="M24 24V40M40 24V40M20 32H44" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.7"/>
                </svg>
            </a>
            <ul class="nav flex-column align-items-center flex-grow-1">
                <li class="nav-item mb-3">
                    <a href="dashboard.php" class="nav-link sidebar-icon p-2" title="Dashboard">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h12A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6zM3.75 12h16.5" />
                        </svg>
                    </a>
                </li>
                <li class="nav-item mb-3">
                    <a href="subjects_management.php" class="nav-link sidebar-icon p-2" title="Manage Subjects">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0A2.25 2.25 0 013.75 7.5h16.5a2.25 2.25 0 012.25 2.25m0 0A2.25 2.25 0 0121.75 12h-1.875a.375.375 0 01-.375-.375V9.776c0-.227.186-.412.413-.412h1.414a.375.375 0 00.375-.375V7.5A2.25 2.25 0 0019.5 5.25h-15A2.25 2.25 0 002.25 7.5v1.414c0 .207.168.375.375.375h1.414c.227 0 .413.185.413.412v1.875a.375.375 0 01-.375-.375H2.25A2.25 2.25 0 010 9.75M7.5 12h9M7.5 15h9" />
                        </svg>
                    </a>
                </li>
            </ul>
            <div class="d-flex flex-column align-items-center mt-auto pb-2">
                 <a href="user_profile.php" class="nav-link profile-icon active p-2 mb-2" title="Profile"> 
                    <svg style="width:32px; height:32px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </a>
                 <a href="logout.php" class="nav-link logout-icon p-2" title="Logout"> 
                    <svg style="width:32px; height:32px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                </a>
            </div>
        </nav>

        <main class="main-content">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2 text-indigo-300 font-orbitron">My Profile</h1>
                     <a href="dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-1" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z"/>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert" id="profilePageAlert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($user_profile_data): ?>
                    <div class="card profile-card-custom p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center mb-4 mb-md-0">
                                <?php if (!empty($user_profile_data['profile_picture_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($user_profile_data['profile_picture_url']); ?>" alt="<?php echo htmlspecialchars($user_profile_data['name']); ?>'s Profile Picture" class="avatar-img mx-auto">
                                <?php else: 
                                    $name_parts = explode(' ', $user_profile_data['name']);
                                    $initials = '';
                                    if(count($name_parts) >= 1) $initials .= strtoupper(substr($name_parts[0], 0, 1));
                                    if(count($name_parts) >= 2) $initials .= strtoupper(substr(end($name_parts), 0, 1));
                                    if(empty($initials)) $initials = "U";
                                ?>
                                    <div class="avatar-placeholder-profile mx-auto"><?php echo htmlspecialchars($initials); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h2 class="h3 text-slate-100 mb-1"><?php echo htmlspecialchars($user_profile_data['name']); ?></h2>
                                <p class="text-indigo-400 mb-3"><?php echo htmlspecialchars($user_profile_data['email']); ?></p>
                                <p class="info-label mb-0">JOINED</p>
                                <p class="info-value mb-3"><?php echo formatDateForProfile($user_profile_data['created_at']); ?></p>
                                <p class="info-label mb-0">GOOGLE USER ID</p>
                                <p class="info-value"><?php echo htmlspecialchars($user_profile_data['google_user_id']); ?></p>
                            </div>
                        </div>
                        <hr class="my-4" style="border-color: rgba(55, 65, 81, 0.5);">
                        <div class="text-center text-md-right">
                             <a href="edit_profile.php" class="btn btn-secondary-custom btn-sm mr-2 d-inline-flex align-items-center" id="editProfileLink">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil-square mr-1" viewBox="0 0 16 16">
                                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                                </svg>
                                Edit Profile
                            </a>
                             <button type="button" class="btn btn-danger-custom btn-sm" id="deleteAccountBtn">Delete Account</button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if(empty($message)): ?>
                        <div class="alert alert-warning text-center">Could not load user profile data.</div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <div class="modal fade" id="confirmationModalProfile" tabindex="-1" aria-labelledby="confirmationModalLabelProfile" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-orbitron" id="confirmationModalLabelProfile">Confirm Action</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="confirmationModalBodyProfile">
            Are you sure you want to proceed?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmActionBtnProfile">Confirm</button> 
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Navigation
        document.getElementById('dashboardHomeLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'dashboard.php'; });
        document.getElementById('dashboardLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'dashboard.php'; });
        document.getElementById('manageSubjectsLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'subjects_management.php'; });
        document.getElementById('profileLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'user_profile.php'; });
        
        const logoutBtnProfile = document.getElementById('logoutBtn'); 
        if(logoutBtnProfile) {
            logoutBtnProfile.addEventListener('click', (e) => { 
                e.preventDefault(); 
                showConfirmationModalProfilePage('Are you sure you want to log out?', () => {
                    window.location.href = 'logout.php';
                }, 'Confirm Logout', 'Logout');
            });
        }

        // Edit Profile button is now a link, no JS needed for navigation.
        // const editProfileStaticBtn = document.getElementById('editProfileStaticBtn');
        // if(editProfileStaticBtn) {
        //     editProfileStaticBtn.addEventListener('click', () => {
        //         alert('Static: Edit Profile functionality is not implemented in this version.');
        //     });
        // }
        
        const deleteAccountBtn = document.getElementById('deleteAccountBtn'); // Changed from deleteAccountStaticBtn
        if(deleteAccountBtn) {
            deleteAccountBtn.addEventListener('click', () => {
                 showConfirmationModalProfilePage(
                    '<strong>DANGER!</strong> Are you absolutely sure you want to delete your account?<br>This action cannot be undone and will permanently remove all your data (notes, subjects, etc.).', 
                    () => {
                         showConfirmationModalProfilePage(
                            '<strong>FINAL CONFIRMATION:</strong> This will permanently delete your account and all associated data. <br>To proceed, please type "DELETE" in the box below and click "Yes, Delete My Account".<br><input type="text" id="deleteConfirmInputProfile" class="form-control form-control-sm mt-2" placeholder="Type DELETE here">',
                            () => { 
                                const confirmInput = document.getElementById('deleteConfirmInputProfile');
                                if (confirmInput && confirmInput.value === "DELETE") {
                                    // Create a form and submit to delete_account_action.php
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.action = 'delete_account_action.php'; 
                                    document.body.appendChild(form);
                                    form.submit();
                                } else {
                                    // Using Bootstrap alert for this message instead of native alert
                                    const modalBody = document.getElementById('confirmationModalBodyProfile');
                                    if(modalBody){
                                        const originalMessage = modalBody.innerHTML;
                                        modalBody.innerHTML += '<p class="text-danger mt-2">Deletion cancelled or confirmation text was incorrect.</p>';
                                        setTimeout(() => { 
                                            modalBody.innerHTML = originalMessage; // Restore original modal message
                                            // Or simply hide the modal: $('#confirmationModalProfile').modal('hide');
                                        }, 3000);
                                    } else {
                                        alert('Deletion cancelled. Confirmation text was incorrect.');
                                    }
                                }
                            },
                            'Final Account Deletion Confirmation',
                            'Yes, Delete My Account',
                            'btn-danger'
                         );
                    },
                    'Confirm Account Deletion',
                    'Proceed to Final Confirmation',
                    'btn-danger'
                );
            });
        }


        // Confirmation Modal Logic for this page
        const confirmationModalProfile = $('#confirmationModalProfile'); 
        const confirmActionBtnProfile = document.getElementById('confirmActionBtnProfile');
        const confirmationModalLabelProfile = document.getElementById('confirmationModalLabelProfile');
        const confirmationModalBodyProfile = document.getElementById('confirmationModalBodyProfile');
        let currentActionCallbackProfile = null; 

        function showConfirmationModalProfilePage(message, callback, title = 'Confirm Action', confirmButtonText = 'Confirm', buttonClass = 'btn-danger') {
            if(confirmationModalLabelProfile) confirmationModalLabelProfile.textContent = title;
            if(confirmationModalBodyProfile) confirmationModalBodyProfile.innerHTML = message; // Use innerHTML for HTML content
            if(confirmActionBtnProfile) {
                confirmActionBtnProfile.textContent = confirmButtonText;
                confirmActionBtnProfile.className = 'btn'; // Reset classes
                confirmActionBtnProfile.classList.add(buttonClass); // Add the specified class
            }
            currentActionCallbackProfile = callback;
            if(confirmationModalProfile.modal) confirmationModalProfile.modal('show');
        }

        if(confirmActionBtnProfile) {
            confirmActionBtnProfile.addEventListener('click', () => {
                if (typeof currentActionCallbackProfile === 'function') {
                    currentActionCallbackProfile();
                }
                // Do not hide modal here if the callback itself is showing another modal
                // The callback (e.g. second confirmation) will handle hiding if needed.
                // if(confirmationModalProfile.modal) confirmationModalProfile.modal('hide'); 
                // currentActionCallbackProfile = null; // Reset only if action is fully complete
            });
        }
        
        // Auto-dismiss alerts from PHP after 3 seconds
        window.setTimeout(function() {
            if (window.jQuery && $.fn.alert) {
                 $("#profilePageAlert .alert").fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }
        }, 3000);

    </script>
</body>
</html>

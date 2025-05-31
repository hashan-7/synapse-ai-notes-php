<?php
session_start();
require_once 'config.php';      // Assuming config.php is in the same root folder
require_once 'db_connect.php';  // Assuming db_connect.php is in the same root folder

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=' . urlencode('Please login to access this page.'));
    exit;
}

$current_user_id = $_SESSION['user_id'];
$message = ''; 
$message_type = ''; 

// Retrieve and clear session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_type'])) {
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message_type']);
}


// Handle Actions (Add, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $subject_name = isset($_POST['subject_name']) ? trim($_POST['subject_name']) : null;
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;

    $temp_message = '';
    $temp_message_type = '';

    if ($action) {
        try {
            if ($action === 'add_subject' && !empty($subject_name)) {
                $checkStmt = $pdo->prepare("SELECT id FROM subjects WHERE user_id = :user_id AND name = :name");
                $checkStmt->execute(['user_id' => $current_user_id, 'name' => $subject_name]);
                if ($checkStmt->fetch()) {
                    $temp_message = 'A subject with this name already exists.';
                    $temp_message_type = 'danger';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO subjects (user_id, name, created_at, updated_at) VALUES (:user_id, :name, NOW(), NOW())");
                    $stmt->execute(['user_id' => $current_user_id, 'name' => $subject_name]);
                    $temp_message = 'Subject added successfully!';
                    $temp_message_type = 'success';
                }
            } elseif ($action === 'update_subject' && $subject_id && !empty($subject_name)) {
                $checkStmt = $pdo->prepare("SELECT id FROM subjects WHERE user_id = :user_id AND name = :name AND id != :id");
                $checkStmt->execute(['user_id' => $current_user_id, 'name' => $subject_name, 'id' => $subject_id]);
                if ($checkStmt->fetch()) {
                    $temp_message = 'Another subject with this name already exists.';
                    $temp_message_type = 'danger';
                } else {
                    $stmt = $pdo->prepare("UPDATE subjects SET name = :name, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
                    $stmt->execute(['name' => $subject_name, 'id' => $subject_id, 'user_id' => $current_user_id]);
                    if ($stmt->rowCount() > 0) {
                        $temp_message = 'Subject updated successfully!';
                        $temp_message_type = 'success';
                    } else {
                        $temp_message = 'Subject not found or no changes made.';
                        $temp_message_type = 'warning';
                    }
                }
            }
            if (!empty($temp_message)) {
                $_SESSION['message'] = $temp_message;
                $_SESSION['message_type'] = $temp_message_type;
            }
            header("Location: subjects_management.php"); 
            exit;

        } catch (PDOException $e) {
            error_log("Database error in subjects_management.php (POST): " . $e->getMessage());
            $_SESSION['message'] = 'Database error: Could not process subject.';
            $_SESSION['message_type'] = 'danger';
            header("Location: subjects_management.php");
            exit;
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete_subject' && isset($_GET['id'])) {
    $subject_id_to_delete = (int)$_GET['id'];
    $temp_message = '';
    $temp_message_type = '';
    try {
        // Notes associated with this subject will have their subject_id set to NULL due to ON DELETE SET NULL constraint
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $subject_id_to_delete, 'user_id' => $current_user_id]);
        if ($stmt->rowCount() > 0) {
            $temp_message = 'Subject deleted successfully. Associated notes are now uncategorized.';
            $temp_message_type = 'success';
        } else {
            $temp_message = 'Subject not found or you do not have permission to delete it.';
            $temp_message_type = 'danger';
        }
    } catch (PDOException $e) {
        error_log("Database error deleting subject: " . $e->getMessage());
        $temp_message = 'Database error: Could not delete subject. ' . $e->getMessage();
        $temp_message_type = 'danger';
    }
    $_SESSION['message'] = $temp_message;
    $_SESSION['message_type'] = $temp_message_type;
    header("Location: subjects_management.php"); 
    exit;
}

// Fetch subjects for the current user for display, including note count
$subjects = [];
try {
    $sqlFetchSubjects = "SELECT s.id, s.name, COUNT(n.id) as note_count 
                         FROM subjects s
                         LEFT JOIN notes n ON s.id = n.subject_id AND n.user_id = s.user_id
                         WHERE s.user_id = :user_id 
                         GROUP BY s.id, s.name
                         ORDER BY s.name ASC";
    $stmtSubjects = $pdo->prepare($sqlFetchSubjects);
    $stmtSubjects->execute(['user_id' => $current_user_id]);
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subjects for display: " . $e->getMessage());
    if(empty($message)) { 
        $message = 'Error fetching subjects list.';
        $message_type = 'danger';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Synapse AI Notes (PHP)</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;500;600&display=swap');
        body { font-family: 'Lexend', sans-serif; color: #cdd5e0; background-color: #0a101f; }
        h1, h2, h3, .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .bg-main-gradient { background: linear-gradient(180deg, #111827 0%, #0a101f 40%, #080c17 100%); min-height: 100vh; }
        .sidebar { background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(10px) saturate(120%); border-right: 1px solid rgba(55, 65, 81, 0.4); padding: 1rem; height: 100vh; position: fixed; width: 100px; }
        .sidebar .nav-link svg { width: 28px; height: 28px; color: #9ca3af; transition: color 0.2s ease-in-out, transform 0.2s ease-out; }
        .sidebar .nav-link:hover svg { color: #a5b4fc; transform: scale(1.1); }
        .sidebar .nav-link.active svg { color: #818cf8; }
        .sidebar .logout-icon:hover svg { color: #f87171; }
        .main-content { margin-left: 100px; padding: 2rem; width: calc(100% - 100px); }
        .card-custom { background-color: rgba(31, 41, 55, 0.7); border: 1px solid rgba(55, 65, 81, 0.35); color: #cdd5e0; }
        .list-group-item-custom { background-color: rgba(42, 56, 77, 0.6); border-color: rgba(55, 65, 81, 0.3); color: #cdd5e0; }
        .list-group-item-custom:hover { background-color: rgba(55, 65, 81, 0.7); }
        .form-control-custom { background-color: rgba(31, 41, 55, 0.8); border: 1px solid rgba(55, 65, 81, 0.5); color: #cdd5e0; }
        .form-control-custom:focus { border-color: #6366f1; box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); background-color: rgba(31, 41, 55, 0.9); color: #cdd5e0; }
        .btn-action-edit:hover svg { color: #3b82f6; }
        .btn-action-delete:hover svg { color: #ef4444; }
        .modal-content { background-color: #1f2937; color: #cdd5e0; border: 1px solid rgba(55, 65, 81, 0.5); }
        .modal-header, .modal-footer { border-color: rgba(55, 65, 81, 0.5); }
        .close { color: #9ca3af; text-shadow: none; }
        .close:hover { color: #e5e7eb; }
        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .spinner-border-sm { width: 1rem; height: 1rem; border-width: .2em;}
        .badge-note-count { font-size: 0.7em; background-color: #374151; color: #9ca3af; }
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
                    <a href="subjects_management.php" class="nav-link sidebar-icon active p-2" title="Manage Subjects">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0A2.25 2.25 0 013.75 7.5h16.5a2.25 2.25 0 012.25 2.25m0 0A2.25 2.25 0 0121.75 12h-1.875a.375.375 0 01-.375-.375V9.776c0-.227.186-.412.413-.412h1.414a.375.375 0 00.375-.375V7.5A2.25 2.25 0 0019.5 5.25h-15A2.25 2.25 0 002.25 7.5v1.414c0 .207.168.375.375.375h1.414c.227 0 .413.185.413.412v1.875a.375.375 0 01-.375-.375H2.25A2.25 2.25 0 010 9.75M7.5 12h9M7.5 15h9" />
                        </svg>
                    </a>
                </li>
            </ul>
            <div class="d-flex flex-column align-items-center mt-auto pb-2">
                 <a href="user_profile.php" class="nav-link profile-icon p-2 mb-2" title="Profile"> 
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 text-indigo-300 font-orbitron">Manage Subjects</h1>
                <a href="dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-1" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z"/>
                    </svg>
                    Back to Notes
                </a>
            </div>

            <div id="messageContainerTop" class="mb-3">
                 <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'danger' ? 'danger' : 'warning'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>


            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card card-custom">
                        <div class="card-body">
                            <h3 class="h5 text-indigo-400 mb-3" id="formSectionTitleSubjects">Add New Subject</h3>
                            <form id="subjectFormPage" method="POST" action="subjects_management.php">
                                <input type="hidden" id="subjectIdInputForm" name="subject_id">
                                <input type="hidden" id="actionInputForm" name="action" value="add_subject">
                                <div class="form-group">
                                    <label for="subjectNameInputForm" class="text-slate-400 small">Subject Name</label>
                                    <input type="text" class="form-control form-control-custom" id="subjectNameInputForm" name="subject_name" placeholder="e.g., Quantum Physics" required>
                                </div>
                                <button type="submit" id="saveSubjectBtnForm" class="btn btn-primary-custom btn-block">
                                    <span id="saveButtonTextForm">Add Subject</span>
                                </button>
                                <button type="button" id="cancelEditSubjectBtnPage" class="btn btn-secondary btn-block mt-2 d-none">
                                    Cancel Edit
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card card-custom">
                        <div class="card-body">
                            <h3 class="h5 text-indigo-400 mb-3">Existing Subjects</h3>
                            <?php if (empty($subjects)): ?>
                                <p class="list-group-item list-group-item-custom text-center">No subjects found. Add one using the form.</p>
                            <?php else: ?>
                                <ul class="list-group" id="subjectsListDisplay">
                                    <?php foreach ($subjects as $subject): ?>
                                        <li class="list-group-item list-group-item-custom d-flex justify-content-between align-items-center" 
                                            data-id="<?php echo htmlspecialchars($subject['id']); ?>" 
                                            data-name="<?php echo htmlspecialchars($subject['name']); ?>">
                                            <span>
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                                <span class="badge badge-note-count ml-2"><?php echo htmlspecialchars($subject['note_count']); ?></span>
                                            </span>
                                            <div>
                                                <button class="btn btn-sm btn-link text-info p-1 btn-action-edit edit-subject-trigger" title="Edit Subject">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square pointer-events-none" viewBox="0 0 16 16">
                                                        <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                                        <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                                                    </svg>
                                                </button>
                                                <button class="btn btn-sm btn-link text-danger p-1 btn-action-delete delete-subject-trigger" title="Delete Subject">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3 pointer-events-none" viewBox="0 0 16 16">
                                                        <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5zM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11zm-7.487 1A.5.5 0 0 1 3.022 2h9.956a.5.5 0 0 1 .498.556l-.833 10.49A1 1 0 0 1 11.115 15h-6.23a1 1 0 0 1-.994-.953L3.513 3.5zM4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06zm6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528zM8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0v-8.5A.5.5 0 0 0 8 4.5z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-orbitron" id="confirmationModalLabel">Confirm Action</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="confirmationModalBody">
            Are you sure you want to proceed?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button> 
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
        
        const logoutBtn = document.getElementById('logoutBtn');
        if(logoutBtn) {
            logoutBtn.addEventListener('click', (e) => { 
                e.preventDefault(); 
                showConfirmationModal('Are you sure you want to log out?', () => {
                    window.location.href = 'logout.php';
                }, 'Confirm Logout', 'Logout'); 
            });
        }


        const subjectForm = document.getElementById('subjectFormPage');
        const subjectIdInput = document.getElementById('subjectIdInputForm');
        const subjectNameInput = document.getElementById('subjectNameInputForm');
        const actionInput = document.getElementById('actionInputForm');
        const saveButtonText = document.getElementById('saveButtonTextForm');
        const cancelEditBtn = document.getElementById('cancelEditSubjectBtnPage');
        const formSectionTitle = document.getElementById('formSectionTitleSubjects');
        
        document.querySelectorAll('.edit-subject-trigger').forEach(button => {
            button.addEventListener('click', function() {
                const listItem = this.closest('.list-group-item');
                subjectIdInput.value = listItem.dataset.id;
                subjectNameInput.value = listItem.dataset.name;
                actionInput.value = 'update_subject'; 
                formSectionTitle.textContent = 'Edit Subject';
                saveButtonText.textContent = 'Update Subject';
                cancelEditBtn.classList.remove('d-none');
                subjectNameInput.focus();
            });
        });

        cancelEditBtn.addEventListener('click', function() {
            subjectIdInput.value = '';
            subjectNameInput.value = ''; 
            actionInput.value = 'add_subject';
            formSectionTitle.textContent = 'Add New Subject';
            saveButtonText.textContent = 'Add Subject';
            this.classList.add('d-none');
        });
        
        document.querySelectorAll('.delete-subject-trigger').forEach(button => {
            button.addEventListener('click', function() {
                const listItem = this.closest('.list-group-item');
                const subjectId = listItem.dataset.id;
                const subjectName = listItem.dataset.name;
                
                showConfirmationModal(
                    `Are you sure you want to delete the subject "${subjectName}"? This will set associated notes to 'uncategorized'.`, 
                    () => {
                        window.location.href = `subjects_management.php?action=delete_subject&id=${subjectId}`;
                    },
                    'Confirm Deletion',
                    'Delete'
                );
            });
        });

        const confirmationModal = $('#confirmationModal'); 
        const confirmActionBtn = document.getElementById('confirmActionBtn');
        const confirmationModalLabel = document.getElementById('confirmationModalLabel');
        const confirmationModalBody = document.getElementById('confirmationModalBody');
        let currentActionCallback = null; 

        function showConfirmationModal(message, callback, title = 'Confirm Action', confirmButtonText = 'Confirm') {
            confirmationModalLabel.textContent = title;
            confirmationModalBody.textContent = message;
            confirmActionBtn.textContent = confirmButtonText; 
            
            confirmActionBtn.className = 'btn'; // Reset classes
            if (confirmButtonText.toLowerCase() === 'delete' || confirmButtonText.toLowerCase() === 'logout') {
                confirmActionBtn.classList.add('btn-danger');
            } else {
                confirmActionBtn.classList.add('btn-primary-custom');
            }

            currentActionCallback = callback;
            confirmationModal.modal('show');
        }

        confirmActionBtn.addEventListener('click', () => {
            if (typeof currentActionCallback === 'function') {
                currentActionCallback();
            }
            confirmationModal.modal('hide');
            currentActionCallback = null;
        });

        window.setTimeout(function() {
            $("#messageContainerTop .alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 3000);

    </script>
</body>
</html>

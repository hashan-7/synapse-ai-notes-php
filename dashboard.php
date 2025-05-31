<?php
session_start();
require_once 'config.php';
require_once 'db_connect.php'; // Provides $pdo

// Check if user is logged in, if not, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=' . urlencode('Please login to access the dashboard.'));
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'User';

// Retrieve and clear session messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_type'])) {
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message_type']);
}

// Fetch subjects for the filter dropdown
$subjects = [];
try {
    $stmtSubjects = $pdo->prepare("SELECT id, name FROM subjects WHERE user_id = :user_id ORDER BY name ASC");
    $stmtSubjects->execute(['user_id' => $current_user_id]);
    $subjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
    if(empty($message)) {
        $message = 'Error loading subjects for filter.';
        $message_type = 'danger';
    }
}

// Fetch notes for the current user
$notes = [];
$selected_subject_id = isset($_GET['subject_id']) && $_GET['subject_id'] !== 'all' ? (int)$_GET['subject_id'] : null;
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : null;

$sqlNotes = "SELECT n.id, n.title, n.content, n.generated_summary, n.ai_suggested_category, 
                  s.name as subject_name, n.subject_id, n.created_at, n.updated_at 
           FROM notes n
           LEFT JOIN subjects s ON n.subject_id = s.id
           WHERE n.user_id = :user_id";
$paramsNotes = ['user_id' => $current_user_id];

if ($selected_subject_id) {
    $sqlNotes .= " AND n.subject_id = :subject_id";
    $paramsNotes['subject_id'] = $selected_subject_id;
}
if ($search_query) {
    $sqlNotes .= " AND n.title LIKE :search_query"; // Simple title search
    $paramsNotes['search_query'] = '%' . $search_query . '%';
}
$sqlNotes .= " ORDER BY n.updated_at DESC";

try {
    $stmtNotes = $pdo->prepare($sqlNotes);
    $stmtNotes->execute($paramsNotes);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching notes: " . $e->getMessage());
     if(empty($message)) {
        $message = 'Error loading your notes.';
        $message_type = 'danger';
    }
}

function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    try {
        $date = new DateTime($dateString);
        return $date->format('M d, Y, g:i A');
    } catch (Exception $e) {
        return $dateString; 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Synapse AI Notes (PHP)</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;500;600&display=swap');
        body { font-family: 'Lexend', sans-serif; color: #cdd5e0; background-color: #0a101f; }
        h1, h2, h3, .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .bg-main-gradient { background: linear-gradient(180deg, #111827 0%, #0a101f 40%, #080c17 100%); min-height: 100vh; }
        .sidebar { background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(10px) saturate(120%); border-right: 1px solid rgba(55, 65, 81, 0.4); padding: 1rem; height: 100vh; position: fixed; width: 100px; z-index: 1030;}
        .sidebar .nav-link svg { width: 28px; height: 28px; color: #9ca3af; transition: color 0.2s ease-in-out, transform 0.2s ease-out; }
        .sidebar .nav-link:hover svg { color: #a5b4fc; transform: scale(1.1); }
        .sidebar .nav-link.active svg { color: #818cf8; }
        .sidebar .logout-icon:hover svg { color: #f87171; }
        .main-content { margin-left: 100px; padding: 2rem; width: calc(100% - 100px); }
        
        .card.note-card-custom { 
            background-color: rgba(31, 41, 55, 0.65); 
            border: 1px solid rgba(55, 65, 81, 0.3); 
            border-radius: 0.6rem; /* Rounded-lg */
            color: #cdd5e0;
            transition: transform 0.25s ease-out, box-shadow 0.25s ease-out, border-color 0.25s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            height: 100%; /* For equal height cards in a row */
        }
        .card.note-card-custom:hover { 
            transform: translateY(-7px); 
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15); 
            border-color: rgba(99, 102, 241, 0.35); 
        }
        .note-card-custom .card-title a { 
            color: #a5b4fc; 
            font-size: 1.1rem; 
            margin-bottom: 0.5rem; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap;
            display: block; /* Make the anchor fill the h5 */
            text-decoration: none;
        }
        .note-card-custom .card-title a:hover {
            color: #c7d2fe; /* Lighter indigo on hover */
            text-decoration: underline;
        }
        .note-card-custom .card-text.summary { font-size: 0.85rem; color: #adb5bd; line-height: 1.5; margin-bottom: 0.75rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex-grow: 1;}
        .note-card-custom .card-footer { background-color: transparent; border-top: 1px solid rgba(55, 65, 81, 0.2); padding: 0.75rem 1rem; margin-top: auto;} /* Adjusted padding */
        .badge-subject { background-color: rgba(99, 102, 241, 0.15); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.25); font-size:0.7rem; padding: 0.25rem 0.5rem;}
        .badge-ai-category { background-color: rgba(20, 160, 140, 0.2); color: #5eead4; border: 1px solid rgba(20,160,140,0.3); font-size:0.7rem; padding: 0.25rem 0.5rem;}
        
        .form-control-custom { background-color: rgba(31, 41, 55, 0.8); border: 1px solid rgba(55, 65, 81, 0.5); color: #cdd5e0; }
        .form-control-custom:focus { border-color: #6366f1; box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); background-color: rgba(31, 41, 55, 0.9); color: #cdd5e0; }
        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .spinner-large { border: 5px solid rgba(209, 213, 219, 0.3); border-left-color: #818cf8; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
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
                    <a href="dashboard.php" class="nav-link sidebar-icon active p-2" title="Dashboard">
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
            <header class="mb-4">
                 <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <h1 class="h2 text-indigo-300 font-orbitron mb-3 mb-md-0">My Notes</h1>
                    <div class="d-flex align-items-center w-100 w-md-auto">
                        <form id="searchFilterFormDashboard" method="GET" action="dashboard.php" class="flex-grow-1 mr-md-2">
                             <div class="input-group">
                                <input type="search" name="search_query" id="searchInputDashboard" placeholder="Search notes by title..." 
                                       value="<?php echo isset($_GET['search_query']) ? htmlspecialchars($_GET['search_query']) : ''; ?>"
                                       class="form-control form-control-custom">
                                <input type="hidden" name="subject_id" id="hiddenSubjectIdForSearchDashboard" 
                                       value="<?php echo isset($_GET['subject_id']) ? htmlspecialchars($_GET['subject_id']) : 'all'; ?>">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="submit" style="border-color: rgba(55, 65, 81, 0.5);">Search</button>
                                </div>
                            </div>
                        </form>
                        <a href="add_edit_note.php" id="addNewNoteBtnDashboard" class="btn btn-primary-custom ml-2 d-flex align-items-center" style="white-space: nowrap;">
                            <svg class="mr-1" style="width:16px; height:16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            New Note
                        </a>
                    </div>
                </div>
                <div class="mt-3 d-flex align-items-center">
                    <label for="subjectFilterSelectDashboard" class="text-muted small mr-2 mb-0">Filter by Subject:</label>
                    <select id="subjectFilterSelectDashboard" name="subject_id_filter" class="form-control form-control-custom form-control-sm" style="width: auto;">
                        <option value="all">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['id']); ?>" <?php echo (isset($_GET['subject_id']) && $_GET['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert" id="dashboardPageAlert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div id="loadingIndicatorDashboard" class="text-center py-5" style="display:none;">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="text-slate-400 mt-2">Loading notes...</p>
            </div>

            <div class="row" id="notesGridDashboard">
                <?php if (empty($notes)): ?>
                    <div class="col-12">
                        <div class="card card-custom p-5 text-center">
                             <svg class="mx-auto mb-3" style="width:48px; height:48px; color: #4a5568;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.879 1.757C3.044.732 1.506.837 1.017 1.752L.683 2.357A1.125 1.125 0 00.683 4.18l4.25 7.362a1.125 1.125 0 001.906-.002L11.317 4.18a1.125 1.125 0 00-.001-1.822l-.685-1.036C10.137.733 8.598.839 7.764 1.757L7.5 2.042M7.5 2.042l1.023 1.364A1.125 1.125 0 009.93 4.18l.902-.002a1.125 1.125 0 00.858-1.705L10.5 1.11M7.5 2.042L6.477 3.406A1.125 1.125 0 015.072 4.18l-.903-.002a1.125 1.125 0 01-.857-1.705L4.5 1.11M19.5 2.042l-1.023 1.364A1.125 1.125 0 0117.072 4.18l-.903-.002a1.125 1.125 0 01-.857-1.705L13.5 1.11M19.5 2.042l1.023 1.364A1.125 1.125 0 0021.93 4.18l.902-.002a1.125 1.125 0 00.858-1.705L22.5 1.11M12 18.375a3.375 3.375 0 003.375-3.375h-6.75A3.375 3.375 0 0012 18.375z" />
                            </svg>
                            <h4 class="h5">No Notes Found</h4>
                            <p class="text-muted small">Click "New Note" to create your first intelligent note, or try different search/filter options.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notes as $index => $note): ?>
                        <div class="col-sm-6 col-lg-4 col-xl-3 mb-4">
                            <div class="card note-card-custom">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title font-weight-bold">
                                        <a href="view_note_details.php?id=<?php echo htmlspecialchars($note['id']); ?>" class="text-indigo-300 hover:text-indigo-200" style="text-decoration: none;" title="<?php echo htmlspecialchars($note['title']); ?>">
                                            <?php echo htmlspecialchars($note['title']); ?>
                                        </a>
                                    </h5>
                                    
                                    <?php if (!empty($note['subject_name'])): ?>
                                        <span class="badge badge-subject mb-2 align-self-start"><?php echo htmlspecialchars($note['subject_name']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                        $aiCategory = $note['ai_suggested_category'];
                                        $displayAiCategory = false;
                                        if (!empty($aiCategory) && strtolower($aiCategory) !== strtolower($note['subject_name'] ?? '') && 
                                            !str_contains(strtolower($aiCategory), 'error') && 
                                            strtolower($aiCategory) !== 'ai category pending' &&
                                            strtolower($aiCategory) !== 'no content for ai category') {
                                            $displayAiCategory = true;
                                        }
                                    ?>
                                    <?php if ($displayAiCategory): ?>
                                        <span class="badge badge-ai-category mb-2 align-self-start">AI: <?php echo htmlspecialchars($aiCategory); ?></span>
                                    <?php endif; ?>

                                    <p class="card-text summary">
                                        <?php echo htmlspecialchars( ($note['generated_summary'] && !str_contains(strtolower($note['generated_summary']), 'error') && strtolower($note['generated_summary']) !== 'content too short for ai summary.' && strtolower($note['generated_summary']) !== 'ai summary pending' && strtolower($note['generated_summary']) !== 'no content for ai summary.') ? $note['generated_summary'] : ( empty($note['content']) ? 'No content.' : substr(strip_tags($note['content']),0,100).'...' )   ); ?>
                                    </p>
                                </div>
                                <div class="card-footer text-muted small d-flex justify-content-between align-items-center">
                                    <span>Updated: <?php echo formatDate($note['updated_at']); ?></span>
                                    </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal fade" id="confirmationModalDashboard" tabindex="-1" aria-labelledby="confirmationModalLabelDashboard" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-orbitron" id="confirmationModalLabelDashboard">Confirm Action</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="confirmationModalBodyDashboard">
            Are you sure you want to proceed?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmActionBtnDashboard">Confirm</button> 
          </div>
        </div>
      </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // --- Navigation Handlers ---
        document.getElementById('dashboardHomeLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'dashboard.php'; });
        document.getElementById('dashboardLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'dashboard.php'; });
        document.getElementById('manageSubjectsLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'subjects_management.php'; });
        document.getElementById('profileLink')?.addEventListener('click', (e) => { e.preventDefault(); window.location.href = 'user_profile.php'; });
        
        const logoutBtnDashboard = document.getElementById('logoutBtn');
        if(logoutBtnDashboard) {
            logoutBtnDashboard.addEventListener('click', (e) => { 
                e.preventDefault(); 
                showDashboardConfirmationModal('Are you sure you want to log out?', () => {
                    window.location.href = 'logout.php'; 
                }, 'Confirm Logout', 'Logout');
            });
        }
        document.getElementById('addNewNoteBtnDashboard')?.addEventListener('click', () => {
            window.location.href = 'add_edit_note.php';
        });

        // --- Search and Filter ---
        const subjectFilterSelectDashboard = document.getElementById('subjectFilterSelectDashboard');
        const searchFilterFormDashboard = document.getElementById('searchFilterFormDashboard');
        const hiddenSubjectIdForSearchDashboard = document.getElementById('hiddenSubjectIdForSearchDashboard');
        const searchInputDashboard = document.getElementById('searchInputDashboard');

        subjectFilterSelectDashboard?.addEventListener('change', () => {
            if(hiddenSubjectIdForSearchDashboard) hiddenSubjectIdForSearchDashboard.value = subjectFilterSelectDashboard.value; 
            searchFilterFormDashboard.submit(); 
        });
        
        searchInputDashboard?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                if(hiddenSubjectIdForSearchDashboard) hiddenSubjectIdForSearchDashboard.value = subjectFilterSelectDashboard.value;
                searchFilterFormDashboard.submit();
            }
        });
        searchInputDashboard?.addEventListener('search', function () { 
            if (!searchInputDashboard.value) {
                 if(hiddenSubjectIdForSearchDashboard) hiddenSubjectIdForSearchDashboard.value = subjectFilterSelectDashboard.value;
                 searchFilterFormDashboard.submit();
            }
        });

        // --- Confirmation Modal Logic ---
        const confirmationModalDashboard = $('#confirmationModalDashboard'); 
        const confirmActionBtnDashboard = document.getElementById('confirmActionBtnDashboard');
        const confirmationModalLabelDashboard = document.getElementById('confirmationModalLabelDashboard');
        const confirmationModalBodyDashboard = document.getElementById('confirmationModalBodyDashboard');
        let currentActionCallbackDashboard = null; 

        function showDashboardConfirmationModal(message, callback, title = 'Confirm Action', confirmButtonText = 'Confirm') {
            if(confirmationModalLabelDashboard) confirmationModalLabelDashboard.textContent = title;
            if(confirmationModalBodyDashboard) confirmationModalBodyDashboard.textContent = message;
            if(confirmActionBtnDashboard) {
                confirmActionBtnDashboard.textContent = confirmButtonText;
                confirmActionBtnDashboard.className = 'btn'; 
                if (confirmButtonText.toLowerCase() === 'delete' || confirmButtonText.toLowerCase() === 'logout') {
                    confirmActionBtnDashboard.classList.add('btn-danger');
                } else {
                    confirmActionBtnDashboard.classList.add('btn-primary-custom');
                }
            }
            currentActionCallbackDashboard = callback;
            if(confirmationModalDashboard.modal) confirmationModalDashboard.modal('show');
        }

        if(confirmActionBtnDashboard) {
            confirmActionBtnDashboard.addEventListener('click', () => {
                if (typeof currentActionCallbackDashboard === 'function') {
                    currentActionCallbackDashboard();
                }
                if(confirmationModalDashboard.modal) confirmationModalDashboard.modal('hide');
                currentActionCallbackDashboard = null;
            });
        }

        // Auto-dismiss alerts from PHP after 3 seconds
        window.setTimeout(function() {
            if (window.jQuery && $.fn.alert) { 
                 $("#dashboardPageAlert .alert").fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }
        }, 3000);
        
        // Hide loading indicator (which is not used in this PHP version as data is pre-loaded)
        // And manage empty message display based on PHP-loaded notes
        document.addEventListener('DOMContentLoaded', () => {
            const loadingIndicator = document.getElementById('loadingIndicatorDashboard');
            const emptyNotesMsg = document.getElementById('emptyNotesMessage');
            const notesGrid = document.getElementById('notesGridDashboard');

            if(loadingIndicator) loadingIndicator.style.display = 'none';
            
            // Check if PHP rendered notes or the empty message initially
            const notesExist = <?php echo !empty($notes) ? 'true' : 'false'; ?>;
            if (notesExist) {
                if(emptyNotesMsg) emptyNotesMsg.classList.add('d-none');
            } else {
                if(emptyNotesMsg) emptyNotesMsg.classList.remove('d-none');
            }
        });

    </script>
</body>
</html>

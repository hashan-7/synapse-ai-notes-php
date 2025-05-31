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
$note_id_from_get = isset($_GET['id']) ? (int)$_GET['id'] : null;

$message = '';
$message_type = '';

// Retrieve and clear session messages first
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_type'])) {
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message_type']);
}

if (!$note_id_from_get) {
    if (empty($message)) { 
        $_SESSION['message'] = 'No note ID provided to view.';
        $_SESSION['message_type'] = 'danger';
    }
    header('Location: dashboard.php');
    exit;
}

$note = null;
// Fetch note details only if we are not in a state where a message is already set
if (empty($message) || $message_type !== 'danger') { 
    try {
        $stmt = $pdo->prepare("SELECT n.*, s.name as subject_name 
                               FROM notes n
                               LEFT JOIN subjects s ON n.subject_id = s.id
                               WHERE n.id = :note_id AND n.user_id = :user_id");
        $stmt->execute(['note_id' => $note_id_from_get, 'user_id' => $current_user_id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$note) {
            if (empty($_SESSION['message'])) { 
                 $_SESSION['message'] = 'Note not found or you do not have permission to view it.';
                 $_SESSION['message_type'] = 'danger';
            }
            header('Location: dashboard.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("PHP: Error fetching note details for view: " . $e->getMessage());
        $_SESSION['message'] = 'Error fetching note details.';
        $_SESSION['message_type'] = 'danger';
        header('Location: dashboard.php');
        exit;
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
    <title><?php echo $note ? htmlspecialchars($note['title']) : 'View Note'; ?> - Synapse AI Notes (PHP)</title>
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
        
        .content-card { background-color: rgba(31, 41, 55, 0.7); border: 1px solid rgba(55, 65, 81, 0.35); color: #cdd5e0; border-radius: 0.75rem; }
        .metadata-item { color: #9ca3af; font-size: 0.8rem; }
        .metadata-item strong { color: #a5b4fc; }
        .note-content-display { white-space: pre-wrap; word-wrap: break-word; line-height: 1.75; color: #e5e7eb; background-color: rgba(17, 24, 39, 0.5); padding: 1rem; border-radius: 0.5rem; border: 1px solid rgba(55, 65, 81, 0.2); }
        .summary-box { background-color: rgba(17, 24, 39, 0.6); border-left: 4px solid #6366f1; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .summary-box p { margin-bottom: 0; line-height: 1.6; }

        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .btn-secondary-custom { background-color: rgba(55, 65, 81, 0.6); border-color: rgba(71, 85, 105, 0.6); color: #cbd5e1;}
        .btn-secondary-custom:hover { background-color: rgba(71, 85, 105, 0.8); border-color: rgba(100, 116, 139, 0.8); }
        .btn-danger-custom { background-color: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #f87171;}
        .btn-danger-custom:hover { background-color: rgba(220, 38, 38, 0.4); border-color: rgba(220, 38, 38, 0.5); color: #fca5a5;}
        .alert-dismissible .close { padding: 0.75rem 1.0rem; }
         /* Alert styling for Bootstrap */
        .alert { padding: 0.75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }
        .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .alert-danger { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .alert-warning { color: #664d03; background-color: #fff3cd; border-color: #ffecb5; }
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
            <div class="container-fluid">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show mt-3" role="alert" id="pageAlertMessageView">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($note): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-1" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z"/>
                            </svg>
                            Back to Notes List
                        </a>
                        <div>
                            <a href="add_edit_note.php?edit_id=<?php echo htmlspecialchars($note['id']); ?>" class="btn btn-sm btn-secondary-custom mr-2 d-flex align-items-center" style="display: inline-flex !important;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-pencil-square mr-1" viewBox="0 0 16 16">
                                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
                                </svg>
                                Edit
                            </a>
                            <button type="button" id="deleteNoteTriggerBtn" class="btn btn-sm btn-danger-custom d-flex align-items-center" style="display: inline-flex !important;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-trash3 mr-1" viewBox="0 0 16 16">
                                     <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5zM11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H2.506a.58.58 0 0 0-.01 0H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1h-.995a.59.59 0 0 0-.01 0H11zm-7.487 1A.5.5 0 0 1 3.022 2h9.956a.5.5 0 0 1 .498.556l-.833 10.49A1 1 0 0 1 11.115 15h-6.23a1 1 0 0 1-.994-.953L3.513 3.5zM4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06zm6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528zM8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0v-8.5A.5.5 0 0 0 8 4.5z"/>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                
                    <div class="content-card p-4 p-md-5">
                        <h1 class="h2 text-indigo-300 font-orbitron mb-2"><?php echo htmlspecialchars($note['title']); ?></h1>
                        
                        <div class="d-flex flex-wrap mb-4 pb-3 border-bottom border-secondary">
                            <span class="metadata-item mr-4 mb-1"><strong>Subject:</strong> <?php echo $note['subject_name'] ? htmlspecialchars($note['subject_name']) : 'N/A'; ?></span>
                            <?php 
                                $aiCategory = $note['ai_suggested_category'];
                                $userSubjectName = $note['subject_name'] ?? '';
                                $displayAiCategoryBadge = false;
                                $aiCategoryBadgeText = 'N/A';
                                $aiCategoryBadgeClass = 'badge-secondary'; // Default

                                if (!empty($aiCategory) && strtolower($aiCategory) !== 'ai category pending' && strtolower($aiCategory) !== 'no content for ai category') {
                                    if (str_contains(strtolower($aiCategory), 'error')) {
                                        $aiCategoryBadgeText = 'AI category unavailable'; // More user-friendly
                                        $aiCategoryBadgeClass = 'badge-warning';
                                        $displayAiCategoryBadge = true;
                                    } elseif (strtolower($userSubjectName) !== strtolower($aiCategory)) {
                                        $aiCategoryBadgeText = htmlspecialchars($aiCategory);
                                        $aiCategoryBadgeClass = 'badge-info';
                                        $displayAiCategoryBadge = true;
                                    }
                                }
                            ?>
                            <?php if ($displayAiCategoryBadge): ?>
                                <span class="metadata-item mr-4 mb-1"><strong>AI Category:</strong> 
                                    <span class="badge <?php echo $aiCategoryBadgeClass; ?>" 
                                          style="<?php if($aiCategoryBadgeClass === 'badge-info') echo 'background-color: rgba(20, 160, 140, 0.2); color: #5eead4; border: 1px solid rgba(20,160,140,0.3);'; ?>">
                                        <?php echo $aiCategoryBadgeText; ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                            <span class="metadata-item mr-4 mb-1"><strong>Created:</strong> <?php echo formatDate($note['created_at']); ?></span>
                            <span class="metadata-item mb-1"><strong>Updated:</strong> <?php echo formatDate($note['updated_at']); ?></span>
                        </div>

                        <?php 
                            $summary = $note['generated_summary'];
                            $displaySummaryBox = false;
                            $summaryTextToDisplay = '';
                            $summaryClass = 'text-muted';

                            if (!empty($summary)) {
                                $lowerSummary = strtolower($summary);
                                if ($lowerSummary === 'ai summary pending' || $lowerSummary === 'no content for ai summary.') {
                                    $summaryTextToDisplay = htmlspecialchars($summary); // Show pending/no content message
                                    $displaySummaryBox = true; 
                                } elseif (str_contains($lowerSummary, 'error')) {
                                    $summaryTextToDisplay = 'AI summary currently unavailable.'; // User-friendly error
                                    $displaySummaryBox = true;
                                } elseif ($lowerSummary !== 'content too short for ai summary.') {
                                    $summaryTextToDisplay = nl2br(htmlspecialchars($summary));
                                    $summaryClass = ''; 
                                    $displaySummaryBox = true;
                                } else { 
                                     $summaryTextToDisplay = htmlspecialchars($summary); // "Content too short..."
                                     $displaySummaryBox = true;
                                }
                            }
                        ?>
                        <?php if ($displaySummaryBox): ?>
                        <div class="mb-5">
                            <h2 class="h5 text-indigo-400 mb-2">AI Generated Summary</h2>
                            <div class="summary-box <?php echo $summaryClass; ?>">
                                <p><?php echo $summaryTextToDisplay; ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <h2 class="h5 text-indigo-400 mb-3">Full Note Content</h2>
                            <article class="note-content-display">
                                <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                            </article>
                        </div>
                    </div>
                <?php else: ?>
                     <div class="alert alert-danger text-center">Note details could not be loaded or note does not exist.</div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title font-orbitron" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="deleteConfirmationModalBody">
            Are you sure you want to delete this note? This action cannot be undone.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <form method="POST" action="delete_note_action.php" id="actualDeleteNoteForm" style="display: inline;">
                <input type="hidden" name="note_id_to_delete" id="noteIdToDeleteInModal" value="">
                <button type="submit" class="btn btn-danger">Delete Note</button> 
            </form>
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
        
        const logoutBtnView = document.getElementById('logoutBtn'); 
        if(logoutBtnView) {
            logoutBtnView.addEventListener('click', (e) => { 
                e.preventDefault(); 
                // Using a generic confirmation modal for logout as well
                const logoutModalBody = document.getElementById('deleteConfirmationModalBody');
                const logoutModalConfirmBtn = document.getElementById('actualDeleteNoteForm').querySelector('button[type="submit"]'); // Get the submit button from the delete form
                const logoutModalLabel = document.getElementById('deleteConfirmationModalLabel');
                
                if(logoutModalBody) logoutModalBody.textContent = 'Are you sure you want to log out?';
                if(logoutModalLabel) logoutModalLabel.textContent = 'Confirm Logout';
                if(logoutModalConfirmBtn) {
                    logoutModalConfirmBtn.textContent = 'Logout';
                    logoutModalConfirmBtn.classList.remove('btn-danger');
                    logoutModalConfirmBtn.classList.add('btn-primary-custom'); // Or another suitable class
                    // Change form action for logout
                    document.getElementById('actualDeleteNoteForm').action = 'logout.php';
                    // Remove note_id_to_delete input if it exists, or ensure it's not submitted
                    const noteIdInput = document.getElementById('noteIdToDeleteInModal');
                    if(noteIdInput) noteIdInput.name = ''; // Temporarily disable it
                }
                $('#deleteConfirmationModal').modal('show');
            });
        }

        const deleteNoteTriggerBtn = document.getElementById('deleteNoteTriggerBtn');
        const noteIdToDeleteInModalInput = document.getElementById('noteIdToDeleteInModal');
        const currentNoteIdFromPHPJS = <?php echo json_encode($note_id_from_get ?? null); ?>; 
        const currentNoteTitleFromPHPJS = <?php echo json_encode($note ? $note['title'] : 'this note'); ?>;

        if(deleteNoteTriggerBtn && currentNoteIdFromPHPJS){
            deleteNoteTriggerBtn.addEventListener('click', () => {
                const modalBody = document.getElementById('deleteConfirmationModalBody');
                const modalLabel = document.getElementById('deleteConfirmationModalLabel');
                const confirmBtn = document.getElementById('actualDeleteNoteForm').querySelector('button[type="submit"]');
                const deleteForm = document.getElementById('actualDeleteNoteForm');


                if(modalBody) modalBody.textContent = `Are you sure you want to delete the note "${currentNoteTitleFromPHPJS}"? This action cannot be undone.`;
                if(modalLabel) modalLabel.textContent = 'Confirm Deletion';
                if(noteIdToDeleteInModalInput) noteIdToDeleteInModalInput.value = currentNoteIdFromPHPJS;
                if(confirmBtn) {
                    confirmBtn.textContent = 'Delete Note';
                    confirmBtn.classList.remove('btn-primary-custom');
                    confirmBtn.classList.add('btn-danger');
                }
                if(deleteForm) deleteForm.action = 'delete_note_action.php'; // Set correct action script
                
                $('#deleteConfirmationModal').modal('show');
            });
        }
        
        const editNoteBtnView = document.getElementById('editNoteBtn');
        if(editNoteBtnView && currentNoteIdFromPHPJS){
            editNoteBtnView.addEventListener('click', () => {
                window.location.href = `add_edit_note.php?edit_id=${currentNoteIdFromPHPJS}`; 
            });
        }
        
        window.setTimeout(function() {
            if (window.jQuery && $.fn.alert) {
                 $("#pageAlertMessageView .alert").fadeTo(500, 0).slideUp(500, function(){
                    $(this).remove(); 
                });
            }
        }, 3000);

    </script>
</body>
</html>

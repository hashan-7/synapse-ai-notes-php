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

// --- AI Service Function (Simplified cURL example with timeout) ---
function callHuggingFaceApi($apiUrl, $apiToken, $payload) {
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout in seconds
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Total timeout for the cURL operation in seconds

    // For localhost development, you might need to disable SSL verification (NOT recommended for production)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    error_log("Sending API request to: " . $apiUrl . " with payload: " . json_encode($payload)); // Log request

    $response_body = curl_exec($ch); // This is line 30 in your error
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error_num = curl_errno($ch);
    $curl_error_msg = curl_error($ch);
    curl_close($ch);

    error_log("API Response Code: " . $http_code); // Log response code
    if ($response_body !== false) {
        error_log("API Response Body (first 500 chars): " . substr($response_body, 0, 500)); // Log part of response body
    }


    if ($curl_error_num) {
        error_log("Hugging Face API cURL Error ($apiUrl) - Code: $curl_error_num, Message: " . $curl_error_msg);
        return ['error' => true, 'message' => "API Connection Error: " . $curl_error_msg . " (cURL Error Code: $curl_error_num)", 'data' => null];
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        return ['error' => false, 'message' => 'API call successful', 'data' => json_decode($response_body, true)];
    } else {
        error_log("Hugging Face API HTTP Error ($apiUrl): Status " . $http_code . " - Response: " . $response_body);
        return ['error' => true, 'message' => "API Error (Status: $http_code). Please check server logs for details.", 'data' => json_decode($response_body, true)];
    }
}
// --- End AI Service Function ---

$page_title = "Create New Note";
$form_action = "add_note";
$note_title = '';
$note_content = '';
$note_subject_id = null; 
$note_id_to_edit = null;

// Check if it's an edit request
if (isset($_GET['edit_id']) && !empty($_GET['edit_id'])) {
    $note_id_to_edit = (int)$_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $note_id_to_edit, 'user_id' => $current_user_id]);
        $note_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($note_to_edit) {
            $page_title = "Edit Note";
            $form_action = "update_note";
            $note_title = $note_to_edit['title'];
            $note_content = $note_to_edit['content'];
            $note_subject_id = $note_to_edit['subject_id'];
        } else {
            $_SESSION['message'] = 'Note not found or you do not have permission to edit it.';
            $_SESSION['message_type'] = 'danger';
            header("Location: dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching note for edit: " . $e->getMessage());
        $_SESSION['message'] = 'Error fetching note details.';
        $_SESSION['message_type'] = 'danger';
        header("Location: dashboard.php");
        exit;
    }
}

// Handle Form Submission (Add or Update Note)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $posted_title = isset($_POST['note_title']) ? trim($_POST['note_title']) : '';
    $posted_content = isset($_POST['note_content']) ? trim($_POST['note_content']) : '';
    $posted_subject_id = isset($_POST['note_subject_id']) && $_POST['note_subject_id'] !== '' ? (int)$_POST['note_subject_id'] : null;
    $posted_note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : null;

    $temp_message = '';
    $temp_message_type = '';

    if (empty($posted_title) || empty($posted_content)) {
        $temp_message = 'Title and Content are required.';
        $temp_message_type = 'danger';
        $_SESSION['message'] = $temp_message;
        $_SESSION['message_type'] = $temp_message_type;
        $redirect_url = "add_edit_note.php";
        if ($action === 'update_note' && $posted_note_id) {
            $redirect_url .= "?edit_id=" . $posted_note_id;
        }
        header("Location: " . $redirect_url);
        exit;
    } else { 
        $ai_suggested_category = "AI Processing..."; 
        $generated_summary = "AI Processing...";     

        if (!empty($posted_content)) {
            // 1. Get AI Suggested Category
            $classification_payload = [
                "inputs" => $posted_content,
                "parameters" => [
                    "candidate_labels" => ["Programming", "Mathematics", "History", "Science", "Literature", "AI Concepts", "Project Ideas", "Personal", "Work", "Research", "Finance", "Health"]
                ]
            ];
            $classification_result = callHuggingFaceApi(HF_API_URL_CLASSIFICATION, HF_API_TOKEN, $classification_payload);
            
            if (!$classification_result['error'] && isset($classification_result['data'][0]['label'])) {
                $ai_suggested_category = $classification_result['data'][0]['label'];
            } else {
                $ai_suggested_category = "AI Category Error: " . ($classification_result['message'] ?? 'Unknown');
                error_log("Classification API call failed or returned unexpected data for category: " . ($classification_result['message'] ?? 'Unknown error'));
            }

            // 2. Get AI Generated Summary (if content is long enough)
            if (str_word_count($posted_content) >= 20) { // Reduced word count for testing
                $summarization_payload = ["inputs" => $posted_content];
                $summarization_result = callHuggingFaceApi(HF_API_URL_SUMMARIZATION, HF_API_TOKEN, $summarization_payload);

                if (!$summarization_result['error'] && isset($summarization_result['data'][0]['summary_text'])) {
                    $generated_summary = $summarization_result['data'][0]['summary_text'];
                } else {
                    $generated_summary = "AI Summary Error: " . ($summarization_result['message'] ?? 'Unknown');
                     error_log("Summarization API call failed or returned unexpected data for summary: " . ($summarization_result['message'] ?? 'Unknown error'));
                }
            } else {
                $generated_summary = "Content too short for AI summary.";
            }
        } else {
            $ai_suggested_category = "No content for AI category.";
            $generated_summary = "No content for AI summary.";
        }

        try {
            if ($action === 'add_note') {
                $stmt = $pdo->prepare("INSERT INTO notes (user_id, subject_id, title, content, generated_summary, ai_suggested_category, created_at, updated_at) 
                                       VALUES (:user_id, :subject_id, :title, :content, :summary, :category, NOW(), NOW())");
                $stmt->execute([
                    'user_id' => $current_user_id,
                    'subject_id' => $posted_subject_id,
                    'title' => $posted_title,
                    'content' => $posted_content,
                    'summary' => $generated_summary,
                    'category' => $ai_suggested_category
                ]);
                $temp_message = 'Note added successfully!';
                $temp_message_type = 'success';
            } elseif ($action === 'update_note' && $posted_note_id) {
                $stmt = $pdo->prepare("UPDATE notes SET title = :title, content = :content, subject_id = :subject_id, 
                                       generated_summary = :summary, ai_suggested_category = :category, updated_at = NOW() 
                                       WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    'title' => $posted_title,
                    'content' => $posted_content,
                    'subject_id' => $posted_subject_id,
                    'summary' => $generated_summary,
                    'category' => $ai_suggested_category,
                    'id' => $posted_note_id,
                    'user_id' => $current_user_id
                ]);
                if ($stmt->rowCount() > 0) {
                    $temp_message = 'Note updated successfully!';
                    $temp_message_type = 'success';
                } else {
                    $temp_message = 'Note not found or no changes made (AI data might be same).';
                    $temp_message_type = 'warning';
                }
            } else { 
                 $temp_message = 'Invalid action specified for note processing.';
                 $temp_message_type = 'danger';
            }
            
            $_SESSION['message'] = $temp_message;
            $_SESSION['message_type'] = $temp_message_type;
            header("Location: dashboard.php"); 
            exit;

        } catch (PDOException $e) {
            error_log("Database error in add_edit_note.php: " . $e->getMessage());
            $_SESSION['message'] = 'Database error: Could not process note.';
            $_SESSION['message_type'] = 'danger';
            $redirect_url_on_db_error = "add_edit_note.php";
            if ($action === 'update_note' && $posted_note_id) {
                 $redirect_url_on_db_error .= "?edit_id=" . $posted_note_id;
            }
            header("Location: " . $redirect_url_on_db_error);
            exit;
        }
    } 
}

$subjects_for_dropdown = [];
try {
    $stmtDropdown = $pdo->prepare("SELECT id, name FROM subjects WHERE user_id = :user_id ORDER BY name ASC");
    $stmtDropdown->execute(['user_id' => $current_user_id]);
    $subjects_for_dropdown = $stmtDropdown->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching subjects for dropdown: " . $e->getMessage());
}

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
    <title><?php echo htmlspecialchars($page_title); ?> - Synapse AI Notes (PHP)</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;500;600&display=swap');
        body { font-family: 'Lexend', sans-serif; color: #cdd5e0; background-color: #0a101f; }
        h1, h2, h3, .font-orbitron { font-family: 'Orbitron', sans-serif; }
        .bg-main-gradient { background: linear-gradient(180deg, #111827 0%, #0a101f 40%, #080c17 100%); min-height: 100vh; }
        .form-container { background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(10px) saturate(130%); border: 1px solid rgba(55, 65, 81, 0.4); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.25); }
        .form-control-custom { background-color: rgba(31, 41, 55, 0.8); border: 1px solid rgba(55, 65, 81, 0.5); color: #cdd5e0; }
        .form-control-custom:focus { border-color: #6366f1; box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25); background-color: rgba(31, 41, 55, 0.9); color: #cdd5e0; }
        .btn-primary-custom { background-color: #4f46e5; border-color: #4f46e5; color: #e0e7ff; }
        .btn-primary-custom:hover { background-color: #4338ca; border-color: #4338ca; }
        .btn-secondary-custom { background-color: rgba(55, 65, 81, 0.6); border-color: rgba(71, 85, 105, 0.6); color: #cbd5e1;}
        .btn-secondary-custom:hover { background-color: rgba(71, 85, 105, 0.8); border-color: rgba(100, 116, 139, 0.8); }
        .spinner-border-sm { width: 1rem; height: 1rem; border-width: .2em;}
        textarea.form-control-custom { min-height: 150px; }
    </style>
</head>
<body class="bg-main-gradient py-4 py-md-5 d-flex align-items-center justify-content-center">

    <div class="form-container container" style="max-width: 700px;">
        <div class="p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-indigo-300 font-orbitron" id="formTitle"><?php echo htmlspecialchars($page_title); ?></h1>
                <a href="dashboard.php" class="btn btn-sm btn-outline-light d-flex align-items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left-short mr-1" viewBox="0 0 16 16">
                      <path fill-rule="evenodd" d="M12 8a.5.5 0 0 1-.5.5H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5a.5.5 0 0 1 .5.5z"/>
                    </svg>
                    Back to Notes
                </a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'danger' ? 'danger' : 'warning'); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form id="noteForm" method="POST" action="add_edit_note.php">
                <input type="hidden" name="action" value="<?php echo $form_action; ?>">
                <?php if ($note_id_to_edit): ?>
                    <input type="hidden" name="note_id" value="<?php echo htmlspecialchars($note_id_to_edit); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="noteTitleInput" class="text-slate-400 small">Title</label>
                    <input type="text" class="form-control form-control-custom" id="noteTitleInput" name="note_title" placeholder="Enter note title" value="<?php echo htmlspecialchars($note_title); ?>" required>
                </div>

                <div class="form-group">
                    <label for="noteSubjectSelect" class="text-slate-400 small">Subject / Category (Optional)</label>
                    <select class="form-control form-control-custom" id="noteSubjectSelect" name="note_subject_id">
                        <option value="">-- Select a Subject --</option>
                        <?php foreach ($subjects_for_dropdown as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['id']); ?>" <?php echo ($note_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="noteContentInput" class="text-slate-400 small">Content</label>
                    <textarea class="form-control form-control-custom" id="noteContentInput" name="note_content" rows="10" placeholder="Start typing your note here..." required><?php echo htmlspecialchars($note_content); ?></textarea>
                </div>
                
                <div class="pt-3 d-flex justify-content-end">
                    <a href="dashboard.php" class="btn btn-secondary-custom mr-2">Cancel</a>
                    <button type="submit" id="saveNoteBtn" class="btn btn-primary-custom d-flex align-items-center justify-content-center">
                        <span id="saveNoteButtonText"><?php echo $note_id_to_edit ? 'Update Note' : 'Save Note'; ?></span>
                        <div id="saveNoteSpinner" class="spinner-border spinner-border-sm ml-2 d-none" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        const noteForm = document.getElementById('noteForm');
        const saveNoteBtn = document.getElementById('saveNoteBtn');
        const saveNoteButtonText = document.getElementById('saveNoteButtonText');
        const saveNoteSpinner = document.getElementById('saveNoteSpinner');

        if (noteForm) {
            noteForm.addEventListener('submit', function() {
                const title = document.getElementById('noteTitleInput').value.trim();
                const content = document.getElementById('noteContentInput').value.trim();
                if (!title || !content) {
                    return; 
                }
                saveNoteBtn.disabled = true;
                saveNoteButtonText.textContent = 'Processing...';
                saveNoteSpinner.classList.remove('d-none');
            });
        }

        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 3000); // 3 seconds
    </script>
</body>
</html>

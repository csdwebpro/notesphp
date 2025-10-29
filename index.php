<?php
// notion_notes_app.php - Complete Notion-like Notes Application
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'notes_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// Initialize database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // If database doesn't exist, create it
    if ($e->getCode() == 1049) {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->exec("CREATE DATABASE " . DB_NAME);
        $pdo->exec("USE " . DB_NAME);
        
        // Create tables
        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at DATETIME
        )");
        
        $pdo->exec("CREATE TABLE notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            category VARCHAR(50) DEFAULT 'general',
            created_at DATETIME,
            updated_at DATETIME,
            deleted_at DATETIME
        )");
        
        // Create default user (username: admin, password: admin123)
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, email, password, created_at) 
                   VALUES ('admin', 'admin@example.com', '$hashed_password', NOW())");
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function login($email, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        return true;
    }
    return false;
}

function register($username, $email, $password) {
    global $pdo;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
    return $stmt->execute([$username, $email, $hashed_password]);
}

function logout() {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// Note functions
function getAllNotes($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNoteById($id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function createNote($user_id, $title, $content, $category = 'general') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, category, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    return $stmt->execute([$user_id, $title, $content, $category]);
}

function updateNote($id, $user_id, $title, $content, $category) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, category = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    return $stmt->execute([$title, $content, $category, $id, $user_id]);
}

function deleteNote($id, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notes SET deleted_at = NOW() WHERE id = ? AND user_id = ?");
    return $stmt->execute([$id, $user_id]);
}

function searchNotes($user_id, $query) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? AND deleted_at IS NULL AND (title LIKE ? OR content LIKE ?) ORDER BY updated_at DESC");
    $searchTerm = "%$query%";
    $stmt->execute([$user_id, $searchTerm, $searchTerm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle actions
$action = $_GET['action'] ?? 'dashboard';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'login':
            if (login($_POST['email'], $_POST['password'])) {
                header('Location: ?action=dashboard');
                exit;
            } else {
                $message = 'Invalid email or password.';
                $action = 'login';
            }
            break;
            
        case 'register':
            if (register($_POST['username'], $_POST['email'], $_POST['password'])) {
                $message = 'Registration successful! Please login.';
                $action = 'login';
            } else {
                $message = 'Registration failed. Email might already exist.';
                $action = 'register';
            }
            break;
            
        case 'create_note':
            if (isLoggedIn()) {
                if (createNote($_SESSION['user_id'], $_POST['title'], $_POST['content'], $_POST['category'])) {
                    $message = 'Note created successfully!';
                } else {
                    $message = 'Error creating note.';
                }
                $action = 'dashboard';
            }
            break;
            
        case 'update_note':
            if (isLoggedIn()) {
                if (updateNote($_POST['note_id'], $_SESSION['user_id'], $_POST['title'], $_POST['content'], $_POST['category'])) {
                    $message = 'Note updated successfully!';
                } else {
                    $message = 'Error updating note.';
                }
                $action = 'dashboard';
            }
            break;
            
        case 'delete_note':
            if (isLoggedIn()) {
                if (deleteNote($_POST['note_id'], $_SESSION['user_id'])) {
                    $message = 'Note deleted successfully!';
                } else {
                    $message = 'Error deleting note.';
                }
                $action = 'dashboard';
            }
            break;
    }
}

// Redirect to login if not authenticated
if (!isLoggedIn() && !in_array($action, ['login', 'register'])) {
    $action = 'login';
}

// Display the appropriate page
switch ($action) {
    case 'login':
        displayLoginPage($message);
        break;
    case 'register':
        displayRegisterPage($message);
        break;
    case 'logout':
        logout();
        break;
    case 'dashboard':
        displayDashboard($message);
        break;
    case 'edit_note':
        displayEditNote($_GET['id'] ?? null);
        break;
    case 'create_note':
        displayCreateNote();
        break;
    default:
        displayDashboard();
}
?>

<?php
// Page display functions
function displayLoginPage($message = '') { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NotionNotes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f7f6f3; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .login-container { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; width: 100%; max-width: 400px; }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header i { font-size: 48px; color: #2eaadc; margin-bottom: 16px; }
        .login-header h1 { font-size: 24px; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e9e9e7; border-radius: 4px; font-size: 14px; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: #2eaadc; }
        .btn { width: 100%; padding: 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #2eaadc; color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .error { color: #e53935; font-size: 14px; margin-bottom: 16px; text-align: center; }
        .register-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .register-link a { color: #2eaadc; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-sticky-note"></i>
            <h1>NotionNotes</h1>
        </div>
        <?php if ($message): ?><div class="error"><?= $message ?></div><?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>
        <div class="register-link">
            Don't have an account? <a href="?action=register">Sign up</a>
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
            Default: admin@example.com / admin123
        </div>
    </div>
</body>
</html>
<?php }

function displayRegisterPage($message = '') { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NotionNotes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f7f6f3; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .register-container { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; width: 100%; max-width: 400px; }
        .register-header { text-align: center; margin-bottom: 30px; }
        .register-header i { font-size: 48px; color: #2eaadc; margin-bottom: 16px; }
        .register-header h1 { font-size: 24px; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e9e9e7; border-radius: 4px; font-size: 14px; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: #2eaadc; }
        .btn { width: 100%; padding: 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; }
        .btn-primary { background: #2eaadc; color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .error { color: #e53935; font-size: 14px; margin-bottom: 16px; text-align: center; }
        .login-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .login-link a { color: #2eaadc; text-decoration: none; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <i class="fas fa-sticky-note"></i>
            <h1>Create Account</h1>
        </div>
        <?php if ($message): ?><div class="error"><?= $message ?></div><?php endif; ?>
        <form method="POST" action="">
            <input type="hidden" name="action" value="register">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="?action=login">Sign in</a>
        </div>
    </div>
</body>
</html>
<?php }

function displayDashboard($message = '') {
    global $pdo;
    $user_id = $_SESSION['user_id'];
    $search_query = $_GET['search'] ?? '';
    
    if ($search_query) {
        $notes = searchNotes($user_id, $search_query);
    } else {
        $notes = getAllNotes($user_id);
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotionNotes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root {
            --bg-color: #ffffff; --sidebar-bg: #f7f6f3; --text-color: #37352f; --text-light: #787774;
            --accent-color: #2eaadc; --border-color: #e9e9e7; --hover-color: #f1f1ef; --card-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        body { background-color: var(--bg-color); color: var(--text-color); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 240px; background: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; padding: 16px; }
        .logo { display: flex; align-items: center; padding: 8px 12px; margin-bottom: 16px; font-weight: 700; font-size: 20px; }
        .logo i { margin-right: 8px; color: var(--accent-color); }
        .user-info { padding: 12px; background: var(--hover-color); border-radius: 4px; margin-bottom: 16px; }
        .user-info p { font-size: 14px; color: var(--text-light); }
        .nav-item { display: flex; align-items: center; padding: 8px 12px; border-radius: 4px; margin-bottom: 4px; cursor: pointer; transition: background-color 0.2s; }
        .nav-item:hover { background-color: var(--hover-color); }
        .nav-item.active { background-color: var(--accent-color); color: white; }
        .nav-item i { margin-right: 8px; width: 20px; text-align: center; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .top-bar { display: flex; align-items: center; padding: 12px 24px; border-bottom: 1px solid var(--border-color); }
        .search-bar { flex: 1; max-width: 400px; position: relative; }
        .search-bar input { width: 100%; padding: 8px 12px 8px 36px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--sidebar-bg); font-size: 14px; }
        .search-bar i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light); }
        .user-actions { display: flex; align-items: center; margin-left: auto; gap: 10px; }
        .btn { padding: 8px 16px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; }
        .btn i { margin-right: 6px; }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-color); }
        .btn-outline:hover { background: var(--hover-color); }
        .notes-container { flex: 1; padding: 24px; overflow-y: auto; }
        .notes-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .notes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .note-card { background: white; border: 1px solid var(--border-color); border-radius: 8px; padding: 16px; cursor: pointer; transition: all 0.2s; box-shadow: var(--card-shadow); }
        .note-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .note-title { font-weight: 600; margin-bottom: 8px; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .note-preview { color: var(--text-light); font-size: 14px; line-height: 1.4; height: 42px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .note-meta { display: flex; justify-content: space-between; margin-top: 12px; font-size: 12px; color: var(--text-light); }
        .note-category { background: var(--hover-color); padding: 2px 6px; border-radius: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
        .message { padding: 12px; background: #d4edda; color: #155724; border-radius: 4px; margin: 0 24px 0; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 8px; width: 90%; max-width: 700px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { flex: 1; padding: 20px; overflow-y: auto; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 14px; transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--accent-color); }
        textarea.form-control { min-height: 200px; resize: vertical; }
        .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-sticky-note"></i><span>NotionNotes</span></div>
        <div class="user-info"><p>Welcome, <strong><?= $_SESSION['username'] ?></strong></p></div>
        <div class="nav-item active"><i class="fas fa-home"></i><span>All Notes</span></div>
        <div class="nav-item"><i class="fas fa-star"></i><span>Favorites</span></div>
        <div class="nav-item"><i class="fas fa-tags"></i><span>Categories</span></div>
        <div style="margin-top: auto;">
            <div class="nav-item" onclick="location.href='?action=logout'"><i class="fas fa-sign-out-alt"></i><span>Logout</span></div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <form method="GET" action="" id="searchForm">
                    <input type="hidden" name="action" value="dashboard">
                    <input type="text" name="search" placeholder="Search notes..." value="<?= htmlspecialchars($search_query) ?>">
                </form>
            </div>
            <div class="user-actions">
                <button class="btn btn-primary" onclick="openNoteModal()"><i class="fas fa-plus"></i> New Note</button>
            </div>
        </div>
        
        <?php if ($message): ?><div class="message"><?= $message ?></div><?php endif; ?>
        
        <div class="notes-container">
            <div class="notes-header">
                <h2><?= $search_query ? "Search Results" : "All Notes" ?></h2>
            </div>
            
            <?php if (empty($notes)): ?>
                <div class="empty-state">
                    <i class="fas fa-sticky-note"></i>
                    <h3>No notes found</h3>
                    <p><?= $search_query ? "Try a different search term" : "Create your first note to get started" ?></p>
                    <button class="btn btn-primary" style="margin-top: 16px;" onclick="openNoteModal()">
                        <i class="fas fa-plus"></i> Create Note
                    </button>
                </div>
            <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($notes as $note): ?>
                        <div class="note-card" onclick="editNote(<?= $note['id'] ?>)">
                            <div class="note-title"><?= htmlspecialchars($note['title']) ?></div>
                            <div class="note-preview"><?= htmlspecialchars(substr($note['content'], 0, 100)) ?></div>
                            <div class="note-meta">
                                <span class="note-category"><?= htmlspecialchars($note['category']) ?></span>
                                <span><?= date('M j, Y', strtotime($note['updated_at'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="modal" id="noteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">New Note</h3>
                <button type="button" onclick="closeNoteModal()" style="background: none; border: none; font-size: 18px; cursor: pointer;"><i class="fas fa-times"></i></button>
            </div>
            <form id="noteForm" method="POST" action="">
                <input type="hidden" name="action" value="create_note">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="noteTitle">Title</label>
                        <input type="text" class="form-control" id="noteTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="noteCategory">Category</label>
                        <select class="form-control" id="noteCategory" name="category">
                            <option value="general">General</option>
                            <option value="work">Work</option>
                            <option value="personal">Personal</option>
                            <option value="ideas">Ideas</option>
                            <option value="todo">To-Do</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="noteContent">Content</label>
                        <textarea class="form-control" id="noteContent" name="content" rows="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeNoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openNoteModal() {
            document.getElementById('modalTitle').textContent = 'New Note';
            document.getElementById('noteForm').reset();
            document.getElementById('noteForm').action = '';
            document.getElementById('noteForm').querySelector('input[name="action"]').value = 'create_note';
            document.getElementById('noteModal').style.display = 'flex';
        }
        
        function closeNoteModal() {
            document.getElementById('noteModal').style.display = 'none';
        }
        
        function editNote(noteId) {
            window.location.href = '?action=edit_note&id=' + noteId;
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('noteModal');
            if (event.target === modal) closeNoteModal();
        }
        
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('searchForm').submit();
            }, 500);
        });
    </script>
</body>
</html>
<?php }

function displayEditNote($note_id) {
    if (!$note_id) {
        header('Location: ?action=dashboard');
        return;
    }
    
    $note = getNoteById($note_id, $_SESSION['user_id']);
    if (!$note) {
        header('Location: ?action=dashboard');
        return;
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Note - NotionNotes</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root {
            --bg-color: #ffffff; --sidebar-bg: #f7f6f3; --text-color: #37352f; --text-light: #787774;
            --accent-color: #2eaadc; --border-color: #e9e9e7; --hover-color: #f1f1ef;
        }
        body { background-color: var(--bg-color); color: var(--text-color); }
        .editor-container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        .editor-header { margin-bottom: 30px; }
        .editor-title { width: 100%; font-size: 40px; font-weight: 700; border: none; outline: none; padding: 10px 0; margin-bottom: 10px; background: transparent; }
        .editor-meta { display: flex; gap: 20px; color: var(--text-light); font-size: 14px; margin-bottom: 20px; }
        .editor-content textarea { width: 100%; min-height: 500px; border: none; outline: none; resize: none; font-size: 16px; line-height: 1.6; padding: 10px 0; background: transparent; }
        .editor-actions { position: fixed; bottom: 20px; right: 20px; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; }
        .btn i { margin-right: 6px; }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-color); }
        .btn-danger { background: #e53935; color: white; }
    </style>
</head>
<body>
    <div class="editor-container">
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_note">
            <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
            
            <div class="editor-header">
                <input type="text" class="editor-title" name="title" value="<?= htmlspecialchars($note['title']) ?>" placeholder="Note title...">
                <div class="editor-meta">
                    <div>
                        <select name="category" style="padding: 5px 10px; border-radius: 4px; border: 1px solid var(--border-color);">
                            <option value="general" <?= $note['category'] == 'general' ? 'selected' : '' ?>>General</option>
                            <option value="work" <?= $note['category'] == 'work' ? 'selected' : '' ?>>Work</option>
                            <option value="personal" <?= $note['category'] == 'personal' ? 'selected' : '' ?>>Personal</option>
                            <option value="ideas" <?= $note['category'] == 'ideas' ? 'selected' : '' ?>>Ideas</option>
                            <option value="todo" <?= $note['category'] == 'todo' ? 'selected' : '' ?>>To-Do</option>
                        </select>
                    </div>
                    <div>Last edited: <?= date('M j, Y g:i A', strtotime($note['updated_at'])) ?></div>
                </div>
            </div>
            
            <div class="editor-content">
                <textarea name="content" placeholder="Start writing..."><?= htmlspecialchars($note['content']) ?></textarea>
            </div>
            
            <div class="editor-actions">
                <button type="button" class="btn btn-outline" onclick="window.location.href='?action=dashboard'">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-danger" onclick="deleteNote(<?= $note['id'] ?>)">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
    
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="delete_note">
        <input type="hidden" name="note_id" id="deleteNoteId">
    </form>
    
    <script>
        function deleteNote(noteId) {
            if (confirm('Are you sure you want to delete this note?')) {
                document.getElementById('deleteNoteId').value = noteId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
<?php }

function displayCreateNote() {
    // Similar to edit note but for creating new notes
    displayEditNote(null);
}
?>

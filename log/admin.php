<?php
require_once 'config.php';
session_start();

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        die("Unauthorized");
    }
    $id_to_delete = $_POST['id'] ?? null;
    $redirect_title = $_POST['title'] ?? null;
    
    if ($id_to_delete) {
        $stmt = $pdo->prepare("DELETE FROM logs WHERE id = ?");
        $stmt->execute([$id_to_delete]);
    }
    
    // Redirect back to the list
    header("Location: admin.php?title=" . urlencode($redirect_title));
    exit;
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $error = "Incorrect Password";
    }
}

// Check Auth
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login - Log Viewer</title>
        <style>
            body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f4f4f4; }
            .login-box { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            input { padding: 8px; width: 100%; margin-bottom: 10px; box-sizing: border-box; }
            button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #0056b3; }
            .error { color: red; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>Admin Login</h2>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -- Authenticated Area --

// Get Distinct Titles for Sidebar
$stmt = $pdo->query("SELECT DISTINCT title, COUNT(*) as count FROM logs GROUP BY title ORDER BY title ASC");
$titles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_title = $_GET['title'] ?? null;
$current_id = $_GET['id'] ?? null;

$current_title = $_GET['title'] ?? null;
$current_id = $_GET['id'] ?? null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$logs = [];
$total_pages = 0;
$log_detail = null;

if ($current_title) {
    // Get Total Count for Pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE title = ?");
    $count_stmt->execute([$current_title]);
    $total_logs = $count_stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);

    // Get Paginated Data
    // Note: We cannot use SQL SUBSTRING on encrypted data, so we fetch the full 'data' and decrypt in PHP loop
    $stmt = $pdo->prepare("SELECT id, title, created_at, data FROM logs WHERE title = ? ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    // Bind parameters as integers
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(1, $current_title); // Positional bind for title
    $stmt->execute();
    $raw_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process for display (Decrypt + Snippet)
    foreach ($raw_logs as $log) {
        $decrypted = decrypt_data($log['data']);
        if ($decrypted === false) $decrypted = "[Decryption Failed - Key Mismatch?]";
        
        $logs[] = [
            'id' => $log['id'],
            'title' => $log['title'],
            'created_at' => $log['created_at'],
            'snippet' => substr($decrypted, 0, 100) . (strlen($decrypted) > 100 ? '...' : '')
        ];
    }
}

if ($current_id) {
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE id = ?");
    $stmt->execute([$current_id]);
    $log_detail = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($log_detail) {
        // Decrypt detail view
        $log_detail['data'] = decrypt_data($log_detail['data']);
        if ($log_detail['data'] === false) $log_detail['data'] = "[Decryption Failed - Key Mismatch?]";

        // Ensure we keep the title selected if we direct-link an ID
        $current_title = $log_detail['title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log Viewer</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: #2c3e50; color: white; display: flex; flex-direction: column; border-right: 1px solid #ddd; flex-shrink: 0; }
        .sidebar-header { padding: 15px; background: #1a252f; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .sidebar-header a { color: #aaa; text-decoration: none; font-size: 0.8em; }
        .sidebar-content { flex: 1; overflow-y: auto; }
        .nav-item { display: block; padding: 10px 15px; color: #ecf0f1; text-decoration: none; border-bottom: 1px solid #34495e; transition: background 0.2s; }
        .nav-item:hover { background: #34495e; }
        .nav-item.active { background: #3498db; }
        .badge { background: #e74c3c; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7em; float: right; }

        /* Main Content */
        .main { flex: 1; display: flex; flex-direction: column; background: #f9f9f9; overflow: hidden; }
        .header { padding: 15px; background: white; border-bottom: 1px solid #ddd; font-size: 1.2rem; font-weight: bold; color: #333; }
        
        .content-area { flex: 1; display: flex; overflow: hidden; }
        
        /* List Column */
        .list-col { width: 300px; background: white; border-right: 1px solid #ddd; overflow-y: auto; display: <?php echo $current_title ? 'block' : 'none'; ?>; }
        .list-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; text-decoration: none; color: inherit; display: block; }
        .list-item:hover { background: #f0f7ff; }
        .list-item.active { background: #e6f3ff; border-left: 4px solid #3498db; }
        .list-time { font-size: 0.8em; color: #888; margin-bottom: 4px; }
        .list-snippet { font-size: 0.9em; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Detail Column */
        .detail-col { flex: 1; padding: 20px; overflow-y: auto; background: white; }
        .empty-state { color: #888; text-align: center; margin-top: 50px; }
        
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: 'Consolas', 'Monaco', monospace; }
        .meta { margin-bottom: 20px; color: #666; font-size: 0.9em; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        @media (max-width: 768px) {
            .content-area { flex-direction: column; }
            .list-col { width: 100%; height: 200px; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            Log Viewer
            <a href="admin.php?logout=1">Logout</a>
        </div>
        <div class="sidebar-content">
            <?php foreach ($titles as $t): ?>
                <a href="admin.php?title=<?php echo urlencode($t['title']); ?>" 
                   class="nav-item <?php echo ($current_title === $t['title']) ? 'active' : ''; ?>">
                   <?php echo htmlspecialchars($t['title']); ?>
                   <span class="badge"><?php echo $t['count']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main">
        <div class="header">
            <?php echo $current_title ? htmlspecialchars($current_title) : 'Dashboard'; ?>
        </div>
        
        <div class="content-area">
            <?php if ($current_title): ?>
                <div class="list-col">
                    <?php if (empty($logs)): ?>
                        <div style="padding:15px; color:#888;">No logs found.</div>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <a href="admin.php?title=<?php echo urlencode($current_title); ?>&id=<?php echo $l['id']; ?>&page=<?php echo $page; ?>" 
                               class="list-item <?php echo ($current_id == $l['id']) ? 'active' : ''; ?>">
                                <div class="list-time"><?php echo $l['created_at']; ?></div>
                                <div class="list-snippet"><?php echo htmlspecialchars($l['snippet']); ?></div>
                            </a>
                        <?php endforeach; ?>
                        
                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <div style="padding: 10px; border-top: 1px solid #eee; text-align: center; background: #fafafa;">
                                <?php if ($page > 1): ?>
                                    <a href="admin.php?title=<?php echo urlencode($current_title); ?>&page=<?php echo $page - 1; ?><?php echo $current_id ? '&id='.$current_id : ''; ?>" style="text-decoration: none; color: #3498db; margin-right: 10px;">&laquo; Prev</a>
                                <?php endif; ?>
                                <span style="color:#888; font-size: 0.9em;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                                <?php if ($page < $total_pages): ?>
                                    <a href="admin.php?title=<?php echo urlencode($current_title); ?>&page=<?php echo $page + 1; ?><?php echo $current_id ? '&id='.$current_id : ''; ?>" style="text-decoration: none; color: #3498db; margin-left: 10px;">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="detail-col">
                    <?php if ($log_detail): ?>
                        <div class="meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>ID:</strong> <?php echo $log_detail['id']; ?> | 
                                <strong>Date:</strong> <?php echo $log_detail['created_at']; ?>
                            </div>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this log?');" style="margin: 0;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $log_detail['id']; ?>">
                                <input type="hidden" name="title" value="<?php echo $current_title; ?>">
                                <button type="submit" style="background: #e74c3c; padding: 5px 10px; font-size: 0.8em; width: auto;">Delete Log</button>
                            </form>
                        </div>
                        <h3>Data</h3>
                        <pre><?php echo htmlspecialchars($log_detail['data']); ?></pre>
                    <?php elseif ($current_title): ?>
                        <div class="empty-state">Select a log entry from the list to view details.</div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="detail-col">
                    <div class="empty-state">
                        <h3>Welcome to Log Viewer</h3>
                        <p>Select a title from the sidebar to view logs.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

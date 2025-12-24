<?php
session_start();
require_once 'db.php';

// Auth Handling
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['proxy_admin_logged_in'] = true;
    } else {
        $login_error = "Invalid Password";
    }
}

if (!isset($_SESSION['proxy_admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Proxy Manager Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8f9fa; }
            .login-card { max-width: 400px; width: 100%; }
        </style>
    </head>
    <body class="bg-light">
        <div class="card shadow p-4 login-card">
            <form method="post">
                <h3 class="mb-3 text-center">Proxy Manager</h3>
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <div class="mb-3">
                    <input type="password" name="login_password" class="form-control" placeholder="Enter Admin Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getProxyDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM proxies WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $msg = "Proxy deleted successfully.";
    } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE proxies SET status = 1 - status WHERE id = ?");
        $stmt->execute([$_POST['id']]);
    } elseif ($_POST['action'] === 'save') {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? 'cpanel';
        $host = $_POST['host'] ?? '';
        $port = $_POST['port'] ?? '';
        $username = $_POST['username'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $error_msg = null;
        if ($type === 'cpanel') {
            $api_ok = verifyCpanel($host, $port, $username, $password);
            if (!$api_ok && $port != 2083) $api_ok = verifyCpanel($host, 2083, $username, $password);
            if (!$api_ok && $port != 2082) $api_ok = verifyCpanel($host, 2082, $username, $password);
            
            if (!$api_ok) {
                $error_msg = "Verification Failed: Could not login to cPanel on $host. Proxy not saved.";
            }
        } elseif ($type === 'ssh') {
            // Check SSH only via python helper
            $cmd = "python3 check_ssh.py " . escapeshellarg($host) . " " . escapeshellarg($port) . " " . escapeshellarg($username) . " " . escapeshellarg($password);
            $output = shell_exec($cmd);
            $res = json_decode($output, true);
            if (!($res['ok'] ?? false)) {
                $error_msg = "SSH Verification Failed: " . ($res['message'] ?? "Unknown error");
            }
        }

        if (!$error_msg) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE proxies SET name=?, type=?, host=?, port=?, username=?, password=? WHERE id=?");
                $stmt->execute([$name, $type, $host, $port, $username, $password, $id]);
                $msg = "Proxy updated successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO proxies (name, type, host, port, username, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $type, $host, $port, $username, $password]);
                $msg = "Proxy added successfully.";
            }
        }
    }
}

$proxies = $pdo->query("SELECT * FROM proxies ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxy Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .badge-cpanel { background-color: #ff6c2c; }
        .badge-ssh { background-color: #6f42c1; }
        .badge-real { background-color: #0d6efd; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand">Proxy Manager</span>
            <a href="?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </nav>

    <div class="container mb-5">
        <?php if (isset($msg)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Proxy List</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#proxyModal" onclick="resetForm()">Add Proxy</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Endpoint</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proxies as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td>
                                    <?php 
                                        $badge_class = 'badge-real';
                                        if ($p['type'] === 'cpanel') $badge_class = 'badge-cpanel';
                                        elseif ($p['type'] === 'ssh') $badge_class = 'badge-ssh';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= strtoupper($p['type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['host'] . ":" . $p['port']) ?></td>
                                <td>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $p['status'] ? 'btn-success' : 'btn-secondary' ?>">
                                            <?= $p['status'] ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info me-1" onclick="checkConnection(<?= $p['id'] ?>, this)" title="Test Connection">
                                        <i class="bi bi-arrow-repeat"></i> Check
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick='editProxy(<?= json_encode($p) ?>)' title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this proxy?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($proxies)): ?>
                                <tr><td colspan="5" class="text-center py-4">No proxies configured yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Proxy Modal -->
    <div class="modal fade" id="proxyModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="post">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Proxy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="p_id">
                        <div class="mb-3">
                            <label class="form-label">Name / Label</label>
                            <input type="text" name="name" id="p_name" class="form-control" required placeholder="My Store Proxy">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                             <select name="type" id="p_type" class="form-select">
                                <option value="cpanel">cPanel (SSH Tunnel)</option>
                                <option value="ssh">SSH (Direct Tunnel)</option>
                                <option value="real">Real Proxy (Direct)</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-8">
                                <label class="form-label">Host / IP</label>
                                <input type="text" name="host" id="p_host" class="form-control" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Port</label>
                                <input type="number" name="port" id="p_port" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="p_username" class="form-control">
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" id="p_password" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <div>
                            <span id="testResult" class="small"></span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-info" id="testBtn" onclick="testModalProxy()">Test</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Proxy</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            document.getElementById('modalTitle').innerText = 'Add Proxy';
            document.getElementById('p_id').value = '';
            document.getElementById('p_name').value = '';
            document.getElementById('p_type').value = 'cpanel';
            document.getElementById('p_host').value = '';
            document.getElementById('p_port').value = '';
            document.getElementById('p_username').value = '';
            document.getElementById('p_password').value = '';
        }

        function editProxy(data) {
            document.getElementById('modalTitle').innerText = 'Edit Proxy';
            document.getElementById('p_id').value = data.id;
            document.getElementById('p_name').value = data.name;
            document.getElementById('p_type').value = data.type;
            document.getElementById('p_host').value = data.host;
            document.getElementById('p_port').value = data.port;
            document.getElementById('p_username').value = data.username;
            document.getElementById('p_password').value = data.password;
            
            const modal = new bootstrap.Modal(document.getElementById('proxyModal'));
            modal.show();
        }

        async function checkConnection(id, btn) {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Checking...';
            btn.className = 'btn btn-sm btn-info';

            try {
                const response = await fetch(`check_proxy.php?id=${id}`);
                const result = await response.json();
                
                if (result.ok) {
                    btn.className = 'btn btn-sm btn-success';
                    btn.innerHTML = '<i class="bi bi-check-circle"></i> Working';
                } else {
                    btn.className = 'btn btn-sm btn-danger';
                    btn.innerHTML = '<i class="bi bi-x-circle"></i> Failed';
                    alert(result.message);
                }
            } catch (error) {
                alert('Request failed');
            } finally {
                setTimeout(() => {
                    btn.className = 'btn btn-sm btn-outline-info me-1';
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }, 3000);
            }
        }
        async function testModalProxy() {
            const btn = document.getElementById('testBtn');
            const resultSpan = document.getElementById('testResult');
            
            const data = {
                type: document.getElementById('p_type').value,
                host: document.getElementById('p_host').value,
                port: document.getElementById('p_port').value,
                username: document.getElementById('p_username').value,
                password: document.getElementById('p_password').value
            };

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
            resultSpan.innerHTML = '';

            try {
                const response = await fetch('test_connection.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.ok) {
                    resultSpan.className = 'small text-success';
                    resultSpan.innerHTML = '<i class="bi bi-check-circle"></i> ' + result.message;
                } else {
                    resultSpan.className = 'small text-danger';
                    resultSpan.innerHTML = '<i class="bi bi-x-circle"></i> ' + result.message;
                }
            } catch (error) {
                resultSpan.className = 'small text-danger';
                resultSpan.innerHTML = 'Test failed to run.';
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    </script>
</body>
</html>

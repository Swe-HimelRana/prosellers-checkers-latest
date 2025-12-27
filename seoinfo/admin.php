<?php
session_start();
require_once 'db.php';

// Auth Handling
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Invalid Password";
    }
}

if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
        <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
            <form method="post">
                <h3 class="mb-3 text-center">Admin Login</h3>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error ?? '') ?></div>
                <?php endif; ?>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Enter Password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getDB();

// Handle Actions (Delete/Upsert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM seo_data WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $message = "Record deleted.";
    } elseif ($_POST['action'] === 'save') {
        // Simple upsert logic or update
        $fields = [
            'domain', 'ip', 'update_date', 'product_type', 'product_id', 'seller', 'domain_authority', 
            'page_authority', 'spam_score', 'backlink', 'referring_domains', 
            'inbound_links', 'outbound_links', 'domain_age', 'domain_blacklist', 'ip_blacklist'
        ];
        
        $params = [];
        foreach ($fields as $field) {
            $val = $_POST[$field] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            if ($val === '') {
                $val = null;
            }
            $params[$field] = $val;
        }

        // Auto-IP Resolution
        if (empty($params['ip']) && !empty($params['domain'])) {
            $resolved = gethostbyname($params['domain']);
            if ($resolved !== $params['domain']) {
                $params['ip'] = $resolved;
            } else {
                $error = "Invalid domain: IP resolution failed.";
            }
        }

        if (!isset($error)) {



        if (!empty($_POST['id'])) {
            // Update
            $sql = "UPDATE seo_data SET ";
            $setParts = [];
            foreach ($fields as $field) {
                $setParts[] = "$field = :$field";
            }
            $sql .= implode(', ', $setParts) . " WHERE id = :id";
            $params['id'] = $_POST['id'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = "Record updated.";
        } else {
            // Insert
            $sql = "INSERT INTO seo_data (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_map(fn($f) => ":$f", $fields)) . ")";
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute($params);
                $message = "Record added.";
            } catch (PDOException $e) {
                $error = "Error adding record: " . $e->getMessage();
            }
        }
    }
    }
}

// Pagination & Search
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;
$search = $_GET['search'] ?? '';

$where = '';
$params = [];
if ($search) {
    $where = "WHERE domain LIKE :search";
    $params[':search'] = "%$search%";
}

// Get Total Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM seo_data $where");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Get Records - Use parameterized LIMIT and OFFSET
$sql = "SELECT * FROM seo_data $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
// Bind LIMIT and OFFSET as integers
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
// Execute with search params if any
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Info Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 0.9rem; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">SEO Info Admin</span>
            <div>
                <a href="?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error ?? '') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal" onclick="clearModal()">Add New</button>
            <form class="d-flex" method="get">
                <input class="form-control me-2" type="search" name="search" placeholder="Search Domain" value="<?= htmlspecialchars($search ?? '') ?>">
                <button class="btn btn-outline-success" type="submit">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>DA</th>
                        <th>PA</th>
                        <th>Spam</th>
                        <th>Backlinks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['domain'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['domain_authority'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['page_authority'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['spam_score'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['backlink'] ?? '') ?></td>
                            <td>
                                <button class="btn btn-sm btn-info text-white" 
                                    onclick='editRecord(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)'>Edit</button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Del</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                </li>
                <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $totalPages ?></span></li>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="post">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="modal_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-control" name="domain" id="modal_domain" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" name="ip" id="modal_ip">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Update Date</label>
                                <input type="text" class="form-control" name="update_date" id="modal_update_date" placeholder="dd-mm-yyyy">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Seller</label>
                                <input type="text" class="form-control" name="seller" id="modal_seller">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product Type</label>
                                <input type="text" class="form-control" name="product_type" id="modal_product_type">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product ID</label>
                                <input type="text" class="form-control" name="product_id" id="modal_product_id">
                            </div>
                            <!-- metrics -->
                            <div class="col-md-3">
                                <label class="form-label">DA</label>
                                <input type="number" class="form-control" name="domain_authority" id="modal_domain_authority">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">PA</label>
                                <input type="number" class="form-control" name="page_authority" id="modal_page_authority">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Spam Score</label>
                                <input type="number" class="form-control" name="spam_score" id="modal_spam_score">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Backlinks</label>
                                <input type="number" class="form-control" name="backlink" id="modal_backlink">
                            </div>
                             <div class="col-md-3">
                                <label class="form-label">Ref Domains</label>
                                <input type="number" class="form-control" name="referring_domains" id="modal_referring_domains">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Inbound</label>
                                <input type="number" class="form-control" name="inbound_links" id="modal_inbound_links">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Outbound</label>
                                <input type="number" class="form-control" name="outbound_links" id="modal_outbound_links">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Domain Age</label>
                                <input type="text" class="form-control" name="domain_age" id="modal_domain_age">
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">Domain Blacklist</label>
                                <input type="text" class="form-control" name="domain_blacklist" id="modal_domain_blacklist">
                            </div>
                             <div class="col-md-6">
                                <label class="form-label">IP Blacklist</label>
                                <input type="text" class="form-control" name="ip_blacklist" id="modal_ip_blacklist">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));

        function clearModal() {
            document.querySelectorAll('#editModal input').forEach(input => {
                if(input.name !== 'action') input.value = '';
            });
        }

        function editRecord(data) {
            clearModal();
            for (const key in data) {
                const input = document.getElementById('modal_' + key);
                if (input) {
                    input.value = data[key];
                }
            }
            editModal.show();
        }
    </script>
</body>
</html>

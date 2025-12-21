<?php
header('Content-Type: application/json');
require_once 'db.php';

// auth check
if (!isset($_GET['key']) || $_GET['key'] !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['domain'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Domain parameter is required']);
    exit;
}

$domain = $_GET['domain'];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM seo_data WHERE domain = :domain");
    $stmt->execute([':domain' => $domain]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        echo json_encode($data);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json');
require_once 'db.php';

// auth check
if (!isset($_GET['key']) || $_GET['key'] !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDB();
    
    // Get 10 most recent domains where update_date is empty
    $sql = "SELECT * FROM seo_data WHERE update_date = '' OR update_date IS NULL ORDER BY id DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'count' => count($results),
        'data' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

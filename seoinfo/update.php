<?php
header('Content-Type: application/json');
require_once 'db.php';

// auth check
$key = $_REQUEST['key'] ?? null;
if (!$key || $key !== API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$encodedSql = $_REQUEST['sql'] ?? null;
if (!$encodedSql) {
    http_response_code(400);
    echo json_encode(['error' => 'SQL parameter is required']);
    exit;
}

$sql = base64_decode($encodedSql);
if (!$sql) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Base64']);
    exit;
}

try {
    $pdo = getDB();
    
    // Execute the SQL
    // We use exec() for non-select queries which returns number of affected rows
    // User might send whatever, but strictly speaking this is an "update" script
    $affected = $pdo->exec($sql);
    
    // If exec returns false (and not 0), it might be an error or just a query that doesn't affect rows but isn't an error.
    // PDO throws exception on error because we set ERRMODE_EXCEPTION in db.php
    
    echo json_encode([
        'success' => true, 
        'message' => 'Query executed successfully',
        'affected_rows' => $affected
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

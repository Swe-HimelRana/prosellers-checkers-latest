<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Check API Key
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? null;

if ($api_key !== LOG_API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized: Invalid API Key']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$title = $input['title'] ?? null;
$data = $input['data'] ?? null;

if (!$title || !$data) {
    // Try form data if JSON is null
    $title = $_POST['title'] ?? null;
    $data = $_POST['data'] ?? null;
}

if (!$title || !$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'title and data are required']);
    exit;
}

// If data is an array/object, jsonify it for storage
if (is_array($data) || is_object($data)) {
    $data = json_encode($data, JSON_PRETTY_PRINT);
}

try {
    // Generate Hash for duplicate checking
    $data_hash = hash_hmac('sha256', $data, $encryption_key);

    // Check if duplicate exists
    $check = $pdo->prepare("SELECT id FROM logs WHERE title = ? AND data_hash = ? LIMIT 1");
    $check->execute([$title, $data_hash]);
    if ($check->fetch()) {
        echo json_encode(['ok' => true, 'message' => 'Log saved (duplicate ignored)']);
        exit;
    }

    $encrypted_data = encrypt_data($data);
    $stmt = $pdo->prepare("INSERT INTO logs (title, data, data_hash) VALUES (:title, :data, :hash)");
    $stmt->execute(['title' => $title, 'data' => $encrypted_data, 'hash' => $data_hash]);
    
    echo json_encode(['ok' => true, 'message' => 'Log saved', 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database Error', 'details' => $e->getMessage()]);
}
?>

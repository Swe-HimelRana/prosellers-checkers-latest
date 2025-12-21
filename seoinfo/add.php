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

$domain = trim($_GET['domain']);

// Validate domain format (basic)
if (!filter_var(gethostbyname($domain), FILTER_VALIDATE_IP) && !checkdnsrr($domain, 'A')) {
    // This check is a bit loose, mainly to see if it even resolves, but we'll do the IP resolution below anyway.
    // Let's just rely on the IP resolution step.
}

try {
    $pdo = getDB();
    
    // Check if exists
    $stmt = $pdo->prepare("SELECT id FROM seo_data WHERE domain = :domain");
    $stmt->execute([':domain' => $domain]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'skipped', 'message' => 'Domain already exists']);
        exit;
    }

    // Get IP
    $ip = gethostbyname($domain);
    // gethostbyname returns the domain itself on failure
    if ($ip === $domain) {
        $ip = null; 
    }

    // Insert
    $sql = "INSERT INTO seo_data (domain, ip) VALUES (:domain, :ip)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':domain' => $domain, ':ip' => $ip]);

    echo json_encode([
        'status' => 'success', 
        'message' => 'Domain added',
        'domain' => $domain,
        'ip' => $ip
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

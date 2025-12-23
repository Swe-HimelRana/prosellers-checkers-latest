<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['proxy_admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "message" => "Unauthorized"], JSON_UNESCAPED_SLASHES);
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(["ok" => false, "message" => "Missing ID"], JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = getProxyDB();
$stmt = $pdo->prepare("SELECT * FROM proxies WHERE id = ?");
$stmt->execute([$id]);
$proxy = $stmt->fetch();

if (!$proxy) {
    echo json_encode(["ok" => false, "message" => "Proxy not found"], JSON_UNESCAPED_SLASHES);
    exit;
}

$proxy_url = "";
if ($proxy['type'] === 'real') {
    $auth = $proxy['username'] ? $proxy['username'] . ":" . $proxy['password'] . "@" : "";
    $proxy_url = "http://" . $auth . $proxy['host'] . ":" . $proxy['port'];
} else {
    if (!$proxy['tunnel_port']) {
        echo json_encode(["ok" => false, "message" => "No tunnel port assigned. Ensure the account has status=Active and tunnel manager is running."]);
        exit;
    }
    // Check locally if tunnel is up
    $proxy_url = "socks5://127.0.0.1:" . $proxy['tunnel_port'];
}

$working = verifyProxy($proxy_url);

// Update last_checked
$upd = $pdo->prepare("UPDATE proxies SET last_checked = CURRENT_TIMESTAMP WHERE id = ?");
$upd->execute([$id]);

echo json_encode([
    "ok" => $working,
    "message" => $working ? "Proxy is working!" : "Proxy check failed. Host might be down or credentials invalid.",
    "details" => [
        "type" => $proxy['type'],
        "port" => $proxy['tunnel_port'] ?? $proxy['port'],
        "last_checked" => date("Y-m-d H:i:s")
    ]
], JSON_UNESCAPED_SLASHES);

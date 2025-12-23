<?php
require_once 'db.php';

header('Content-Type: application/json');

$auth_key = $_SERVER['HTTP_X_PROXY_KEY'] ?? '';
if ($auth_key !== PROXY_API_KEY) {
    http_response_code(401);
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit;
}

$pdo = getProxyDB();

// Fetch active proxies (real or cpanel with valid tunnel_port)
$exclude_ids = isset($_GET['exclude']) ? explode(',', $_GET['exclude']) : [];
$exclude_ids = array_map('intval', array_filter($exclude_ids, 'is_numeric'));
$exclude_sql = "";
if (!empty($exclude_ids)) {
    $exclude_sql = " AND id NOT IN (" . implode(',', $exclude_ids) . ")";
}

$stmt = $pdo->query("SELECT * FROM proxies WHERE status = 1 AND (type = 'real' OR (type IN ('cpanel', 'ssh') AND tunnel_port IS NOT NULL)) $exclude_sql ORDER BY RANDOM()");

$all_mode = isset($_GET['all']) && $_GET['all'] == '1';
$working_proxies = [];
$total_checked = 0;

while ($proxy = $stmt->fetch()) {
    $total_checked++;
    $current_verify_url = "";
    $public_proxy_url = "";
    
    if ($proxy['type'] === 'real') {
        $auth = $proxy['username'] ? $proxy['username'] . ":" . $proxy['password'] . "@" : "";
        $public_proxy_url = "http://" . $auth . $proxy['host'] . ":" . $proxy['port'];
        $current_verify_url = $public_proxy_url;
    } else {
        $port = $proxy['tunnel_port'];
        if (!$port) continue;
        
        $current_verify_url = "socks5://127.0.0.1:" . $port;
        $public_proxy_url = "socks5://127.0.0.1:" . $port;
    }

    if (verifyProxy($current_verify_url)) {
        $proxy_data = [
            "id" => $proxy['id'],
            "type" => $proxy['type'],
            "url" => $public_proxy_url
        ];
        
        if ($all_mode) {
            $working_proxies[] = $proxy_data;
        } else {
            $working_proxies = [$proxy_data];
            $selected_id = $proxy['id'];
            break;
        }
    } else {
        $upd = $pdo->prepare("UPDATE proxies SET last_checked = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->execute([$proxy['id']]);
    }
}

if (!empty($working_proxies)) {
    if (!$all_mode) {
        $pdo->prepare("UPDATE proxies SET last_used = CURRENT_TIMESTAMP, last_checked = CURRENT_TIMESTAMP WHERE id = ?")->execute([$selected_id]);
        echo json_encode([
            "ok" => true, 
            "proxy" => $working_proxies[0]
        ], JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode([
            "ok" => true, 
            "proxies" => $working_proxies
        ], JSON_UNESCAPED_SLASHES);
    }
} else {
    echo json_encode([
        "ok" => false, 
        "message" => "No active or working proxies available (Checked $total_checked candidates)"
    ], JSON_UNESCAPED_SLASHES);
}

<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['proxy_admin_logged_in'])) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'cpanel';
$host = trim(($data['host'] ?? ''), ": \t\n\r\0\x0B");
$port = (int)($data['port'] ?? 0);
$user = $data['username'] ?? '';
$pass = $data['password'] ?? '';

if (!$host || !$port) {
    echo json_encode(["ok" => false, "message" => "Host and Port are required"], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($type === 'real') {
    $auth = $user ? "$user:$pass@" : "";
    $proxy_url = "http://$auth$host:$port";
    $working = verifyProxy($proxy_url, 10);
    echo json_encode([
        "ok" => $working,
        "message" => $working ? "Proxy is working!" : "Proxy verification failed."
    ], JSON_UNESCAPED_SLASHES);
} elseif ($type === 'ssh') {
    $cmd = "python3 check_ssh.py " . escapeshellarg($host) . " " . escapeshellarg($port) . " " . escapeshellarg($user) . " " . escapeshellarg($pass);
    $output = shell_exec($cmd);
    $res = json_decode($output, true);
    
    echo json_encode([
        "ok" => ($res['ok'] ?? false),
        "message" => ($res['message'] ?? "SSH check failed")
    ], JSON_UNESCAPED_SLASHES);
} else {
    // cpanel type
    // 1. Check SSH
    $cmd = "python3 check_ssh.py " . escapeshellarg($host) . " " . escapeshellarg($port) . " " . escapeshellarg($user) . " " . escapeshellarg($pass);
    $output = shell_exec($cmd);
    $ssh_res = json_decode($output, true);
    $ssh_ok = ($ssh_res['ok'] ?? false);
    
    // 2. Check cPanel Web API
    $api_ok = verifyCpanel($host, $port, $user, $pass);
    if (!$api_ok && $port != 2083) $api_ok = verifyCpanel($host, 2083, $user, $pass);
    if (!$api_ok && $port != 2082) $api_ok = verifyCpanel($host, 2082, $user, $pass);
    
    if ($ssh_ok && $api_ok) {
        echo json_encode(["ok" => true, "message" => "Success: SSH and cPanel API are both working!"], JSON_UNESCAPED_SLASHES);
    } elseif ($ssh_ok && !$api_ok) {
        echo json_encode(["ok" => false, "message" => "SSH works, but cPanel Web Login failed. Check the cPanel password."], JSON_UNESCAPED_SLASHES);
    } elseif (!$ssh_ok && $api_ok) {
        echo json_encode(["ok" => false, "message" => "Web Login works, but SSH failed: " . ($ssh_res['message'] ?? "Unknown error")], JSON_UNESCAPED_SLASHES);
    } else {
        echo json_encode(["ok" => false, "message" => "Both SSH and API checks failed."], JSON_UNESCAPED_SLASHES);
    }
}

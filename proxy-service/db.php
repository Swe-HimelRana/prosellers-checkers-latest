<?php
require_once 'config.php';

function getProxyDB() {
    try {
        $pdo = new PDO("sqlite:" . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS proxies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            type TEXT NOT NULL, -- 'cpanel', 'ssh', or 'real'
            host TEXT NOT NULL,
            port INTEGER NOT NULL,
            username TEXT NOT NULL,
            password TEXT NOT NULL,
            status INTEGER DEFAULT 1, -- 1 active, 0 inactive
            tunnel_port INTEGER, -- The allocated local port for cpanel SSH tunnels
            last_used DATETIME,
            last_checked DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        return $pdo;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

/**
 * Verifies if a proxy is working by making a request to Google.
 */
function verifyProxy($proxy_url, $timeout = 8) {
    if (empty($proxy_url)) return false;
    
    $ch = curl_init("http://www.google.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");
    
    if (strpos($proxy_url, 'socks5://') === 0) {
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        $clean_url = str_replace('socks5://', '', $proxy_url);
        curl_setopt($ch, CURLOPT_PROXY, $clean_url);
    }

    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $http_code = $info['http_code'];
    $error = curl_error($ch);
    curl_close($ch);
    
    return $http_code > 0;
}

/**
 * Verifies cPanel login credentials via the web API.
 */
function verifyCpanel($host, $port, $user, $pass, $timeout = 15) {
    if (empty($host) || empty($user) || empty($pass)) return false;

    $scheme = ($port == 2083 || $port == 2087) ? "https" : "http";
    // Clean host
    $host = trim($host, ": \t\n\r\0\x0B");
    $url = "$scheme://$host:$port/login/?login_only=1";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['user' => $user, 'pass' => $pass]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200 && $http_code != 302) return false;
    
    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] == 1) {
        return true;
    }
    
    // Fallback for older cPanel or different response types
    if (strpos($response, 'security_token') !== false || strpos($response, 'cpanel_json_ok') !== false) {
        return true;
    }
    
    return false;
}

<?php
require_once 'config.php';

function getProxyDB() {
    try {
        // MySQL Connection
        $dsn = "mysql:host=" . PROXY_DB_HOST . ";dbname=" . PROXY_DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, PROXY_DB_USER, PROXY_DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // MySQL Table Creation
        $pdo->exec("CREATE TABLE IF NOT EXISTS proxies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL, -- 'cpanel', 'ssh', or 'real'
            host VARCHAR(255) NOT NULL,
            port INT NOT NULL,
            username VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status TINYINT DEFAULT 1, -- 1 active, 0 inactive
            tunnel_port INT, -- The allocated local port for cpanel SSH tunnels
            last_used DATETIME,
            last_checked DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return $pdo;
    } catch (PDOException $e) {
        die("Database error (mysql): " . $e->getMessage());
    }
}

/**
 * Verifies if a proxy is working by making a request to Google, then Example.com.
 */
function verifyProxy($proxy_url, $timeout = 8) {
    if (empty($proxy_url)) return false;
    
    // Try Google first
    if (checkUrlWithProxy("http://www.google.com", $proxy_url, $timeout)) {
        return true;
    }
    
    // Fallback to example.com
    return checkUrlWithProxy("http://example.com", $proxy_url, $timeout);
}

function checkUrlWithProxy($target_url, $proxy_url, $timeout = 8) {
    $ch = curl_init($target_url);
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

<?php
// Admin Password
$admin_password = getenv('LOG_ADMIN_PASSWORD') ?: '@Pass123@Pass'; // Change this!

// --- OPTION 1: SQLite (Great for local testing) ---
$db_file = getenv('DB_FILE_PATH') ?: __DIR__ . '/database.sqlite';
try {
    $install = !file_exists($db_file);
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($install) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            data TEXT,
            data_hash TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_title ON logs(title)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hash ON logs(data_hash)");
    } else {
        // Migration: Check if data_hash exists, if not add it
        $cols = $pdo->query("PRAGMA table_info(logs)")->fetchAll(PDO::FETCH_ASSOC);
        $has_hash = false;
        foreach ($cols as $col) {
            if ($col['name'] === 'data_hash') {
                $has_hash = true;
                break;
            }
        }
        if (!$has_hash) {
            $pdo->exec("ALTER TABLE logs ADD COLUMN data_hash TEXT");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hash ON logs(data_hash)");
        }
    }
} catch (PDOException $e) {
    die("SQLite Connection Failed: " . $e->getMessage());
}

// --- OPTION 2: MySQL (Use this for hosting) ---
/*
$db_host = '127.0.0.1';
$db_name = 'log_viewer';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("MySQL Connection Failed: " . $e->getMessage());
}
*/
// --- Encryption Configuration ---
//$encryption_key = 'CHANGE_ME_TO_A_STRONG_RANDOM_KEY_AT_LEAST_32_CHARS'; // IMPORTANT: keep this safe!
$encryption_key = getenv('LOG_ENCRYPTION_KEY') ?: 'r8F#2Z!qA9mT@xK7D$S5LwP^cN4YB&UeHfGJ0RkC6VQ1'; // IMPORTANT: keep this safe!
$cipher_method = "AES-256-CBC";

function encrypt_data($data) {
    global $encryption_key, $cipher_method;
    $iv_length = openssl_cipher_iv_length($cipher_method);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, $cipher_method, $encryption_key, 0, $iv);
    // Return IV + Encrypted Data (base64 encoded)
    return base64_encode($iv . $encrypted);
}

function decrypt_data($data) {
    global $encryption_key, $cipher_method;
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length($cipher_method);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, $cipher_method, $encryption_key, 0, $iv);
}
?>

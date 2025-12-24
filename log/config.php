<?php
// Admin Password
$admin_password = getenv('LOG_ADMIN_PASSWORD') ?: '@Pass123@Pass'; // Change this!

// --- Database Configuration (MySQL or SQLite) ---
$db_type = getenv('LOG_DB_TYPE') ?: 'sqlite'; // 'mysql' or 'sqlite'

try {
    if ($db_type === 'mysql') {
        // MySQL Configuration
        $db_host = getenv('LOG_DB_HOST') ?: '127.0.0.1';
        $db_name = getenv('LOG_DB_NAME') ?: 'seoinfo_db';
        $db_user = getenv('LOG_DB_USER') ?: 'root';
        $db_pass = getenv('LOG_DB_PASS') ?: '';

        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if table exists (MySQL specific)
        $tableExists = $pdo->query("SHOW TABLES LIKE 'logs'")->rowCount() > 0;
        
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title TEXT,
                data LONGTEXT,
                data_hash VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_title (title(255)),
                INDEX idx_hash (data_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } else {
        // SQLite Configuration (Default)
        $db_file = getenv('DB_FILE_PATH') ?: __DIR__ . '/database.sqlite';
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
    }
} catch (PDOException $e) {
    die(strtoupper($db_type) . " Connection Failed: " . $e->getMessage());
}
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

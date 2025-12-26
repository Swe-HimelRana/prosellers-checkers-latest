<?php
require_once 'config.php';

function getDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Auto-create table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS seo_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain VARCHAR(255) UNIQUE,
            ip VARCHAR(45),
            update_date VARCHAR(255) DEFAULT '',
            product_type VARCHAR(255),
            product_id VARCHAR(255),
            seller VARCHAR(255),
            domain_authority INT,
            page_authority INT,
            spam_score INT,
            backlink INT,
            referring_domains INT,
            inbound_links INT,
            outbound_links INT,
            domain_age VARCHAR(255),
            domain_blacklist TEXT,
            ip_blacklist TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

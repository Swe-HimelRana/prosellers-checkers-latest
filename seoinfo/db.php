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
            ip VARCHAR(45) NULL,
            update_date VARCHAR(255) NULL,
            product_type VARCHAR(255) NULL,
            product_id VARCHAR(255) NULL,
            seller VARCHAR(255) NULL,
            domain_authority INT NULL,
            page_authority INT NULL,
            spam_score INT NULL,
            backlink INT NULL,
            referring_domains INT NULL,
            inbound_links INT NULL,
            outbound_links INT NULL,
            domain_age VARCHAR(255) NULL,
            domain_blacklist TEXT NULL,
            ip_blacklist TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

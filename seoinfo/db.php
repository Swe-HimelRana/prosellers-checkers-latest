<?php
require_once 'config.php';

function getDB() {
    try {
        if (DB_TYPE === 'sqlite') {
            // For SQLite, DB_NAME is the file path
            $dbPath = __DIR__ . '/' . DB_NAME . '.sqlite';
            $dsn = "sqlite:$dbPath";
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Initialize schema for SQLite if it doesn't exist
            initSQLite($pdo);
        } else {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function initSQLite($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS seo_data (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT UNIQUE,
        ip TEXT,
        update_date TEXT DEFAULT '',
        product_type TEXT,
        product_id TEXT,
        seller TEXT,
        domain_authority INTEGER,
        page_authority INTEGER,
        spam_score INTEGER,
        backlink INTEGER,
        referring_domains INTEGER,
        inbound_links INTEGER,
        outbound_links INTEGER,
        domain_age TEXT,
        domain_blacklist TEXT,
        ip_blacklist TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
}

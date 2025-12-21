<?php
// Configuration

// Database Configuration
define('DB_TYPE', 'sqlite'); // 'sqlite' or 'mysql'
define('DB_HOST', 'localhost');
define('DB_NAME', 'seoinfo_db'); // Schema name for MySQL, File path for SQLite (relative to this file if simple filename)
define('DB_USER', 'root');
define('DB_PASS', '');

// Security
define('API_KEY', 'my_secret_api_key_123'); // Change this!
define('ADMIN_PASSWORD', 'admin123'); // Change this!

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

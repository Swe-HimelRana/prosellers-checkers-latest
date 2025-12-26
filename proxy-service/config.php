<?php
// Proxy Manager Configuration

define('ADMIN_PASSWORD', getenv('PROXY_ADMIN_PASSWORD') ?: 'prosellers@2025');
define('PROXY_API_KEY', getenv('PROXY_API_KEY') ?: '83853b46-5d66-45f2-9de6-f4c563003147');
// Database Configuration
define('PROXY_DB_HOST', getenv('PROXY_DB_HOST') ?: '127.0.0.1');
define('PROXY_DB_NAME', getenv('PROXY_DB_NAME') ?: 'seoinfo_db');
define('PROXY_DB_USER', getenv('PROXY_DB_USER') ?: 'root');
define('PROXY_DB_PASS', getenv('PROXY_DB_PASS') ?: '');

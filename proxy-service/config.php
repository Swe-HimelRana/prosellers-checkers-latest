<?php
// Proxy Manager Configuration

define('ADMIN_PASSWORD', getenv('PROXY_ADMIN_PASSWORD') ?: 'prosellers@2025');
define('PROXY_API_KEY', getenv('PROXY_API_KEY') ?: '83853b46-5d66-45f2-9de6-f4c563003147');
define('DB_PATH', __DIR__ . '/proxies.sqlite');

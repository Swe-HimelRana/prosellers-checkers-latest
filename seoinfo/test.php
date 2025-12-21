<?php
// Test Script

$baseUrl = 'http://localhost:8888'; // Assuming we run php -S localhost:8000
$apiKey = 'my_secret_api_key_123';

echo "1. Testing DB Initialization (via api.php call to trigger db.php)...\n";
// Just curling a non-existent domain should trigger DB init if not exists
$response = file_get_contents("$baseUrl/api.php?domain=test.com&key=$apiKey", false, stream_context_create(['http' => ['ignore_errors' => true]]));
echo "Response: " . substr($response, 0, 100) . "...\n\n";

echo "2. Testing Update API (Insert)...\n";
$sql = "INSERT OR IGNORE INTO seo_data (domain, ip, domain_authority) VALUES ('example.com', '127.0.0.1', 50)";
$encodedSql = base64_encode($sql);
$data = http_build_query(['key' => $apiKey, 'sql' => $encodedSql]);

$opts = ['http' => [
    'method'  => 'POST',
    'header'  => 'Content-type: application/x-www-form-urlencoded',
    'content' => $data
]];
$context  = stream_context_create($opts);
$result = file_get_contents("$baseUrl/update.php", false, $context);
echo "Update Result: $result\n\n";

echo "3. Testing API Read...\n";
$result = file_get_contents("$baseUrl/api.php?domain=example.com&key=$apiKey");
echo "Read Result: $result\n\n";

echo "4. Testing Add API...\n";
// Dry run with a real domain
$domain = "google.com";
$result = file_get_contents("$baseUrl/add.php?domain=$domain&key=$apiKey");
echo "Add Result (New): $result\n";
// Try adding again
$result = file_get_contents("$baseUrl/add.php?domain=$domain&key=$apiKey");
echo "Add Result (Existing): $result\n";


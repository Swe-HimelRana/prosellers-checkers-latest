# API Documentation - Prosellers Checkers

## Overview

The Prosellers Checkers API is a Flask-based REST API that provides credential validation services for various protocols including cPanel, SMTP, SSH, RDP, and Proxy servers. 

It also includes a **built-in Proxy Management system** that allows you to use cPanel accounts as SSH tunnels or pass traffic through real proxies.

## Base URL

```
http://localhost:8888
```

> **Note**: The port can be configured via the `PORT` environment variable (default: 8888)

## Architecture Change: Asynchronous Jobs (New!)

To handle high volumes of requests and prevent worker timeouts, the Prosellers Checkers API now uses an **Asynchronous Job Queue** (Redis-backed).

### How to use the API now:

1.  **Step 1: Submit a Task**: Call any of the `/check/...` endpoints as usual. Instead of waiting for the result, the API will return a `job_id` immediately with a `202 Accepted` status.
2.  **Step 2: Poll for Results**: Use the new `/results/<job_id>` endpoint to check if the task is finished and get the final result.

---

## Centralized Job Results

Use this endpoint to retrieve the status and result of any previously queued check.

**Endpoint**: `GET /results/<job_id>`

**Authentication**: Required

#### Success Response (Task Processing)
**Status Code**: `200 OK`
```json
{
  "ok": true,
  "message": "Task is still processing",
  "status": "queued"
}
```

#### Success Response (Task Finished)
**Status Code**: `200 OK`
```json
{
  "ok": true,
  "message": "cPanel login is working",
  "details": { ... },
  "status": "finished"
}
```

---

## Endpoints (All 202 Accepted)

### 1. Health Check

Check if the API service is running and healthy.

**Endpoint**: `GET /health`

**Authentication**: Required

#### Request Example

```bash
curl -X GET http://localhost:8888/health \
  -H "X-API-Key: your_api_key_here"
```

#### Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "Healthy",
  "status": "healthy"
}
```

---

### 2. cPanel Login Check

Validates cPanel login credentials and performs PTR record verification.

**Endpoint**: `POST /check/cpanel`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | cPanel server hostname or IP          |
| username   | string  | Yes      | -       | cPanel username                       |
| password   | string  | Yes      | -       | cPanel password                       |
| ssl        | boolean | No       | true    | Use SSL/TLS connection                |
| port       | integer | No       | 2083/2082 | cPanel port (2083 for SSL, 2082 for non-SSL) |
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/cpanel \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "example.com",
    "username": "cpanel_user",
    "password": "cpanel_pass123",
    "ssl": true,
    "port": 2083
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "cPanel login is working",
  "details": {
    "http_status": 200,
    "ptr_match": "yes",
    "ptr_record": "example.com",
    "response": {
      "status": 1,
      "redirect": "/cpsess1234567890/frontend/jupiter/index.html"
    }
  }
}
```

#### Failure Response Example (Invalid Credentials)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "cPanel login is not working",
  "details": {
    "http_status": 200,
    "ptr_match": "no",
    "ptr_record": "server.hosting.com",
    "response": {
      "status": 0,
      "error": "Login failed"
    }
  }
}
```

#### Error Response Example (Server Unreachable)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "cPanel Server is not reachable",
  "details": {
    "error": "Connection timeout",
    "ptr_match": "unknown"
  }
}
```

#### Validation Error Example

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "host, username, password are required"
}
```

#### Special Features

- **WAF/Imunify360 Bypass**: Uses `curl_cffi` to impersonate Chrome browser
- **PTR Record Check**: Validates if the PTR record matches the hostname
- **Automatic Logging**: Successful checks are logged via `log_check()`

---

### 2b. cPanel Upload Check

Upload a PHP file to cPanel and verify it's accessible via HTTP. This tests both file upload capability and public web access. The endpoint automatically creates a file named `prosellers_check.php` in the `public_html` directory with the current timestamp.

**Endpoint**: `POST /check/cpanel/upload`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | cPanel/Domain hostname                |
| username   | string  | Yes      | -       | cPanel username                       |
| password   | string  | Yes      | -       | cPanel password                       |
| port       | integer | No       | 2083    | cPanel port (auto-detects SSL)        |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/cpanel/upload \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "example.com",
    "username": "cpanel_user",
    "password": "cpanel_pass123",
    "port": 2083
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "File uploaded and verified successfully",
  "file_url": "https://example.com/prosellers_check.php",
  "details": {
    "upload_status_code": 200,
    "upload_result": {
      "status": 1,
      "metadata": {
        "reason": "OK",
        "result": 1
      }
    },
    "verify_status_code": 200,
    "tried_https": "https://example.com/prosellers_check.php"
  }
}
```

#### Failure Response Example (Verification Failed)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "File uploaded but not accessible via HTTP/HTTPS",
  "details": {
    "upload_status_code": 200,
    "upload_result": {
      "status": 1
    },
    "tried_https": "https://example.com/prosellers_check.php",
    "tried_http": "http://example.com/prosellers_check.php"
  }
}
```

#### Validation Error Example

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "host, username, password are required"
}
```

#### Special Features

- **Simplified Input**: Only requires basic cPanel credentials
- **Auto-SSL Detection**: Automatically determines whether to use SSL for the cPanel API based on the port
- **Dual Verification**: Attempts to verify the uploaded file via both HTTPS and HTTP
- **Automatic Logging**: Successful checks are logged via `log_check()`
- **WAF Tolerance**: Uses `verify=False` to handle servers with self-signed certificates

---

### 3. SMTP Login Check

Validates SMTP server credentials with support for SSL/TLS and STARTTLS.

**Endpoint**: `POST /check/smtp`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | SMTP server hostname or IP            |
| username   | string  | Yes      | -       | SMTP username                         |
| password   | string  | Yes      | -       | SMTP password                         |
| port       | integer | No       | 587     | SMTP port                             |
| ssl        | boolean | No       | false   | Use SSL connection (port 465)         |
| starttls   | boolean | No       | true    | Use STARTTLS for encryption           |
| timeout    | integer | No       | 10      | Connection timeout in seconds         |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/smtp \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "smtp.gmail.com",
    "username": "user@gmail.com",
    "password": "app_password_here",
    "port": 587,
    "ssl": false,
    "starttls": true,
    "timeout": 10
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "SMTP login is working"
}
```

#### Failure Response Example (Authentication Error)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "SMTP login is not working",
  "details": {
    "code": 535,
    "msg": "5.7.8 Username and Password not accepted"
  }
}
```

#### Error Response Example (Server Unreachable)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "SMTP Server is not reachable",
  "details": "[Errno 111] Connection refused"
}
```

---

### 3b. SMTP Email Sending Check

Validates SMTP credentials by attempting to send a test email. The email will have the subject "Prosellers Email check" and a matching body.

**Endpoint**: `POST /check/smtp/send`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | SMTP server hostname or IP            |
| port       | integer | Yes      | -       | SMTP port                             |
| username   | string  | Yes      | -       | SMTP username                         |
| password   | string  | Yes      | -       | SMTP password                         |
| to         | string  | Yes      | -       | Recipient email address               |
| ssl        | boolean | No       | false   | Use SSL connection (port 465)         |
| starttls   | boolean | No       | true    | Use STARTTLS for encryption           |
| timeout    | integer | No       | 10      | Connection timeout in seconds         |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/smtp/send \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "user@gmail.com",
    "password": "app_password_here",
    "to": "recipient@example.com",
    "ssl": false,
    "starttls": true
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "Email sent successfully",
  "details": {}
}
```

#### Failure Response Example (Authentication Error)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "SMTP login is not working",
  "details": {
    "code": 535,
    "msg": "5.7.8 Username and Password not accepted"
  }
}
```

---

### 4. SSH Login Check

Validates SSH server credentials and executes a test command.

**Endpoint**: `POST /check/ssh`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | SSH server hostname or IP             |
| username   | string  | Yes      | -       | SSH username                          |
| password   | string  | Yes      | -       | SSH password                          |
| port       | integer | No       | 22      | SSH port                              |
| timeout    | integer | No       | 10      | Connection timeout in seconds         |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/ssh \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "192.168.1.100",
    "username": "root",
    "password": "secure_password",
    "port": 22,
    "timeout": 10
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "SSH login is working",
  "details": {
    "echo": "ok"
  }
}
```

#### Failure Response Example (Authentication Failed)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "SSH login is not working"
}
```

#### Error Response Example (Server Unreachable)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "SSH Server is not reachable",
  "details": "[Errno 113] No route to host"
}
```

---

### 5. RDP Login Check

Validates RDP (Remote Desktop Protocol) credentials using FreeRDP.

**Endpoint**: `POST /check/rdp`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | RDP server hostname or IP             |
| username   | string  | Yes      | -       | RDP username                          |
| password   | string  | Yes      | -       | RDP password                          |
| port       | integer | No       | 3389    | RDP port                              |
| domain     | string  | No       | ""      | Windows domain (optional)             |
| timeout    | integer | No       | 15      | Connection timeout in seconds         |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/rdp \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "192.168.1.50",
    "username": "Administrator",
    "password": "Win@dmin123",
    "port": 3389,
    "domain": "WORKGROUP",
    "timeout": 15
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "RDP login is working"
}
```

#### Failure Response Example (Invalid Credentials)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "RDP Login is not working",
  "details": {
    "return_code": 131,
    "stdout": "",
    "stderr": "Authentication failure"
  }
}
```

#### Error Response Example (Invalid IP/Port)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "Invalid rdp ip and port"
}
```

#### Error Response Example (xfreerdp Not Found)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "xfreerdp not found. Install FreeRDP to enable RDP login check."
}
```

#### Prerequisites

- **xfreerdp**: FreeRDP must be installed on the system
- **xvfb-run**: Required for headless environments (Docker)

---

### 6. Proxy Server Check

Validates proxy server connectivity and credentials.

**Endpoint**: `POST /check/proxy`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | Proxy server hostname or IP           |
| port       | integer | Yes      | -       | Proxy port                            |
| username   | string  | No       | -       | Proxy username (if auth required)     |
| password   | string  | No       | -       | Proxy password (if auth required)     |
| protocol   | string  | No       | "http"  | Proxy protocol (http/https/socks5)    |
| timeout    | integer | No       | 10      | Connection timeout in seconds         |

#### Request Example (With Authentication)

```bash
curl -X POST http://localhost:8888/check/proxy \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "proxy.example.com",
    "port": 8080,
    "username": "proxy_user",
    "password": "proxy_pass",
    "protocol": "http",
    "timeout": 10
  }'
```

#### Request Example (Without Authentication)

```bash
curl -X POST http://localhost:8888/check/proxy \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "public-proxy.com",
    "port": 3128,
    "protocol": "http"
  }'
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "message": "Proxy is working",
  "details": {
    "external_ip": "203.0.113.45"
  }
}
```

#### Failure Response Example (Proxy Not Working)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "Proxy is not working",
  "details": {
    "http_status": 407
  }
}
```

#### Error Response Example (Server Unreachable)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Proxy Server is not reachable"
}
```

---

### 7. IP Information Lookup

Get geolocation and ASN (Autonomous System Number) information for any IP address using GeoLite2 databases.

**Endpoint**: `GET /ipinfo/<ip_address>`

**Authentication**: Required

#### Request Parameters

| Parameter    | Type   | Required | Description                           |
|--------------|--------|----------|---------------------------------------|
| ip_address   | string | Yes      | IP address to lookup (in URL path)   |

#### Request Example

```bash
curl -X GET http://localhost:8888/ipinfo/8.8.8.8 \
  -H "X-API-Key: your_api_key_here"
```

#### Success Response Example

**Status Code**: `200 OK`

```json
{
  "ip": "8.8.8.8",
  "country_name": "United States",
  "country_code": "US",
  "region": "",
  "city": "",
  "zipcode": "",
  "asn": 15169,
  "organization": "GOOGLE"
}
```

#### Response Fields

| Field          | Type    | Description                                      |
|----------------|---------|--------------------------------------------------|
| ip             | string  | The IP address that was queried                  |
| country_name   | string  | Full country name (empty if not found)           |
| country_code   | string  | ISO 3166-1 alpha-2 country code                  |
| region         | string  | State/Province/Region name                       |
| city           | string  | City name                                        |
| zipcode        | string  | Postal/ZIP code                                  |
| asn            | integer | Autonomous System Number (0 if not found)        |
| organization   | string  | Organization/ISP name (empty if not found)       |

#### Additional Examples

**Cloudflare DNS (1.1.1.1)**:
```bash
curl -X GET http://localhost:8888/ipinfo/1.1.1.1 \
  -H "X-API-Key: your_api_key_here"
```

Response:
```json
{
  "ip": "1.1.1.1",
  "country_name": "",
  "country_code": "",
  "region": "",
  "city": "",
  "zipcode": "",
  "asn": 13335,
  "organization": "CLOUDFLARENET"
}
```

#### Error Response Example (Invalid IP)

**Status Code**: `400 Bad Request`

```json
{
  "ok": false,
  "message": "Error processing IP: '999.999.999.999' does not appear to be an IPv4 or IPv6 address"
}
```

#### Error Response Example (Database Not Found)

**Status Code**: `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "GeoLite2-City.mmdb not found"
}
```

#### Prerequisites

- **GeoLite2-City.mmdb**: MaxMind GeoLite2 City database file
- **GeoLite2-ASN.mmdb**: MaxMind GeoLite2 ASN database file

Both database files must be present in the application root directory.

#### Notes

- Empty strings are returned for fields where data is not available
- ASN will be 0 if not found in the database
- The endpoint handles both IPv4 and IPv6 addresses
- No logging is performed for IP lookups

---

### 8. Proxy Status & GeoIP
Check all active proxies tracked by the proxy-service and return their GeoIP information. This uses `reallyfreegeoip.org` through each proxy to verify real-world connectivity.

**Endpoint**: `GET /proxies/status`

**Authentication**: Required

#### Request Example
```bash
curl -X GET http://localhost:8888/proxies/status \
  -H "X-API-Key: your_api_key_here"
```

#### Success Response Example
**Status Code**: `200 OK`
```json
{
  "ok": true,
  "count": 1,
  "proxies": [
    {
      "id": 5,
      "type": "cpanel",
      "proxy_url": "socks5://proxy-service:9900",
      "ip": "103.253.38.12",
      "country_name": "Bangladesh",
      "country_code": "BD",
      "time_zone": "Asia/Dhaka",
      "ok": true
    }
  ]
}
```

---

## Response Structure

All API responses follow a consistent structure:

### Success Response

```json
{
  "ok": true,
  "message": "Success message",
  "details": {
    // Optional additional details
  }
}
```

### Error Response

```json
{
  "ok": false,
  "message": "Error message",
  "details": {
    // Optional error details
  }
}
```

---

## HTTP Status Codes

| Status Code | Description                                      |
|-------------|--------------------------------------------------|
| 200         | Success - Request completed successfully         |
| 400         | Bad Request - Invalid parameters or failed check |
| 401         | Unauthorized - Invalid or missing API key        |
| 500         | Internal Server Error - Server/connection error  |

---

## Logging

Successful credential checks are automatically logged using the `log_check()` function from the `logs` module. The following information is logged:

- **Service Type**: cPanel, SMTP, SSH, RDP, or Proxy
- **Host/Port**: Connection details
- **Username**: Account username
- **Status**: "working" for successful checks
- **Additional Info**: PTR match (cPanel), external IP (Proxy), etc.

---

## Configuration

The API uses a `config.py` file for configuration:

```python
# config.py
API_KEY = "your_secure_api_key_here"
```

---

## Running the Application

### Standard Python

```bash
python app.py
```

### With Custom Port

```bash
PORT=9000 python app.py
```

### Docker

```bash
docker run -p 8888:8888 -e PORT=8888 prosellers-checkers
```

---

## Error Handling

The API implements comprehensive error handling:

1. **Authentication Errors**: Invalid credentials return 400 with specific error details
2. **Connection Errors**: Unreachable servers return 500 with error descriptions
3. **Validation Errors**: Missing required parameters return 400 with validation messages
4. **Timeout Errors**: Connection timeouts return 500 with timeout messages

---

## Security Features

1. **API Key Authentication**: All endpoints protected with API key
2. **SSL/TLS Support**: Secure connections for all protocols
3. **WAF Bypass**: cPanel checks use browser impersonation to bypass WAF/Imunify360
4. **PTR Verification**: cPanel checks include PTR record validation
5. **Timeout Protection**: All requests have configurable timeouts

---

## Dependencies

- **Flask**: Web framework
- **requests**: HTTP client
- **curl_cffi**: Browser impersonation for WAF bypass
- **paramiko**: SSH client
- **geoip2**: MaxMind GeoIP2 database reader for IP geolocation
- **winrm**: Windows Remote Management (imported but not actively used)
- **xfreerdp**: FreeRDP for RDP checks (system dependency)
- **xvfb-run**: Virtual framebuffer for headless RDP checks (system dependency)

---

## Sample Integration Code

### Python

```python
import requests

API_URL = "http://localhost:8888"
API_KEY = "your_api_key_here"

headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json"
}

# Check cPanel
response = requests.post(
    f"{API_URL}/check/cpanel",
    headers=headers,
    json={
        "host": "example.com",
        "username": "cpanel_user",
        "password": "cpanel_pass",
        "ssl": True
    }
)

if response.status_code == 200:
    data = response.json()
    if data["ok"]:
        print("cPanel login successful!")
        print(f"PTR Match: {data['details']['ptr_match']}")
    else:
        print("cPanel login failed!")
else:
    print(f"Error: {response.status_code}")
```

### JavaScript (Node.js)

```javascript
const axios = require('axios');

const API_URL = 'http://localhost:8888';
const API_KEY = 'your_api_key_here';

async function checkCPanel() {
  try {
    const response = await axios.post(
      `${API_URL}/check/cpanel`,
      {
        host: 'example.com',
        username: 'cpanel_user',
        password: 'cpanel_pass',
        ssl: true
      },
      {
        headers: {
          'X-API-Key': API_KEY,
          'Content-Type': 'application/json'
        }
      }
    );

    if (response.data.ok) {
      console.log('cPanel login successful!');
      console.log('PTR Match:', response.data.details.ptr_match);
    } else {
      console.log('cPanel login failed!');
    }
  } catch (error) {
    console.error('Error:', error.response?.data || error.message);
  }
}

checkCPanel();
```

### PHP

```php
<?php

$apiUrl = 'http://localhost:8888';
$apiKey = 'your_api_key_here';

$data = [
    'host' => 'example.com',
    'username' => 'cpanel_user',
    'password' => 'cpanel_pass',
    'ssl' => true
];

$ch = curl_init($apiUrl . '/check/cpanel');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && $result['ok']) {
    echo "cPanel login successful!\n";
    echo "PTR Match: " . $result['details']['ptr_match'] . "\n";
} else {
    echo "cPanel login failed!\n";
}
?>
```

---

## Troubleshooting

### Common Issues

1. **401 Unauthorized**
   - Verify API key is correct in `config.py`
   - Ensure `X-API-Key` header is included in request

2. **RDP Check Fails**
   - Install FreeRDP: `apt-get install freerdp2-x11` (Ubuntu/Debian)
   - Install Xvfb for headless: `apt-get install xvfb`

3. **Connection Timeouts**
   - Increase timeout parameter in request
   - Check firewall rules
   - Verify server is accessible

4. **PTR Match Always "no"**
   - DNS PTR records may not be configured
   - This is informational and doesn't affect login validation

---

## Version Information

- **API Version**: 1.0
- **Flask Version**: Latest
- **Python Version**: 3.7+

---

## Support

For issues or questions, please refer to the project repository or contact the development team.

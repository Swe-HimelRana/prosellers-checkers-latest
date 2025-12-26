# API Documentation - Prosellers Checkers

## Overview

The Prosellers Checkers API is a Flask-based REST API that provides credential validation services for various protocols including cPanel, SMTP, SSH, RDP, and Proxy servers.

It also includes a **built-in Proxy Management system** that allows you to use cPanel accounts as SSH tunnels or pass traffic through real proxies.

## Base URL

```
http://localhost:8888
```

> **Note**: The port can be configured via the `PORT` environment variable (default: 8888)

## Architecture: Asynchronous Job Queue

To handle high volumes of requests and prevent worker timeouts, the Prosellers Checkers API uses an **Asynchronous Job Queue** (Redis-backed).

### How to use the API:

1.  **Step 1: Submit a Task**: Call any of the `/check/...` or `/proxies/status` endpoints. The API will return a `job_id` immediately with a `202 Accepted` status.
2.  **Step 2: Poll for Results**: Use the `/results/<job_id>` endpoint to check if the task is finished and get the final result.

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
  "status": "queued",
  "progress": 0
}
```

#### Success Response (Task Finished)
**Status Code**: `200 OK`
```json
{
  "ok": true,
  "message": "cPanel login is working",
  "details": { ... },
  "status": "finished",
  "progress": 100
}
```

#### Failure Response (Job Failed/Error)
**Status Code**: `500 Internal Server Error`
```json
{
  "ok": false,
  "message": "Job failed",
  "status": "failed",
  "progress": 100
}
```

---

## Endpoints

### 1. Health Check

Check if the API service is running and healthy. This endpoint is **synchronous**.

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

Validates cPanel login credentials and performs PTR record verification. **(Asynchronous)**

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
    "port": 2083,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "c4ca4238a0b923820dcc509a6f75849b"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "cPanel login is working",
  "details": {
    "http_status": 200,
    "ptr_match": "yes",
    "ptr_record": "example.com",
    "attempts": 1,
    "proxy_used": {
      "id": 5,
      "url": "socks5://10.20.30.40:5000"
    },
    "proxies_tried": [
      {
        "id": 5,
        "url": "socks5://10.20.30.40:5000"
      }
    ],
    "response": {
      "status": 1,
      "redirect": "/cpsess1234567890/frontend/jupiter/index.html"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 2b. cPanel Upload Check

Upload a PHP file to cPanel and verify it's accessible via HTTP. This tests both file upload capability and public web access. **(Asynchronous)**

**Endpoint**: `POST /check/cpanel/upload`

**Authentication**: Required

#### Request Parameters

| Parameter  | Type    | Required | Default | Description                           |
|------------|---------|----------|---------|---------------------------------------|
| host       | string  | Yes      | -       | cPanel/Domain hostname                |
| username   | string  | Yes      | -       | cPanel username                       |
| password   | string  | Yes      | -       | cPanel password                       |
| port       | integer | No       | 2083    | cPanel port (auto-detects SSL)        |
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

#### Request Example

```bash
curl -X POST http://localhost:8888/check/cpanel/upload \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "host": "example.com",
    "username": "cpanel_user",
    "password": "cpanel_pass123",
    "port": 2083,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "c81e728d9d4c2f636f067f89cc14862c"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "File uploaded and verified successfully",
  "file_url": "https://example.com/prosellers_check.php",
  "details": {
    "upload_status_code": 200,
    "verify_status_code": 200,
    "attempts": 1,
    "proxy_used": {
      "id": 8,
      "url": "socks5://50.60.70.80:6000"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 3. SMTP Login Check

Validates SMTP server credentials with support for SSL/TLS and STARTTLS. **(Asynchronous)**

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
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

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
    "timeout": 10,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "eccbc87e4b5ce2fe28308fd9f2a7baf3"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "SMTP login is working",
  "details": {
    "attempts": 1,
    "proxy_used": {
      "id": 12,
      "url": "http://user:pass@1.2.3.4:8080"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 3b. SMTP Email Sending Check

Validates SMTP credentials by attempting to send a test email. **(Asynchronous)**

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
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

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
    "starttls": true,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "a87ff679a2f3e71d9181a67b7542122c"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "Email sent successfully",
  "details": {
    "attempts": 1,
    "proxy_used": {
      "id": 12,
      "url": "http://user:pass@1.2.3.4:8080"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 4. SSH Login Check

Validates SSH server credentials and executes a test command. **(Asynchronous)**

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
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

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
    "timeout": 10,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "e4d909c290d0fb1ca068ffaddf22cbd0"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "SSH login is working",
  "details": {
    "echo": "ok",
    "attempts": 1,
    "proxy_used": {
      "id": 15,
      "url": "socks5://vpn:3000"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 5. RDP Login Check

Validates RDP (Remote Desktop Protocol) credentials using FreeRDP. **(Asynchronous)**

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
| use_proxy  | boolean | No       | false   | Use an active proxy from the manager  |

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
    "timeout": 15,
    "use_proxy": true
  }'
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "1679091c5a880faf6fb5e6087eb1b2dc"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "RDP login is working",
  "details": {
    "attempts": 1,
    "proxy_used": {
      "id": 7,
      "url": "socks5://192.168.1.200:1080"
    }
  },
  "status": "finished",
  "progress": 100
}
```

---

### 6. Proxy Server Check

Validates proxy server connectivity and credentials. **(Asynchronous)**

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

#### Request Example

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

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "c9f0f895fb98ab9159f51fd0297e236d"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
  "message": "Proxy is working",
  "details": {
    "external_ip": "203.0.113.45"
  },
  "status": "finished",
  "progress": 100
}
```

---

### 7. IP Information Lookup

Get geolocation and ASN (Autonomous System Number) information for any IP address. **(Synchronous)**

**Endpoint**: `GET /ip/info/<ip_address>`

**Authentication**: Required

#### Request Example

```bash
curl -X GET http://localhost:8888/ip/info/8.8.8.8 \
  -H "X-API-Key: your_api_key_here"
```

#### Success Response

**Status Code**: `200 OK`

```json
{
  "ok": true,
  "details": {
    "ip": "8.8.8.8",
    "country_name": "United States",
    "country_code": "US",
    "region": "",
    "city": "",
    "zipcode": "",
    "asn": 15169,
    "organization": "GOOGLE"
  }
}
```

---

### 8. Proxy Status & GeoIP

Check all active proxies tracked by the proxy-service and return their GeoIP information. **(Asynchronous)**

**Endpoint**: `GET /proxies/status`

**Authentication**: Required

#### Request Example
```bash
curl -X GET http://localhost:8888/proxies/status \
  -H "X-API-Key: your_api_key_here"
```

#### Initial Response (Job Queued)

**Status Code**: `202 Accepted`

```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "d41d8cd98f00b204e9800998ecf8427e"
}
```

#### Final Result Example (via `/results/<job_id>`)

```json
{
  "ok": true,
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
  ],
  "count": 1,
  "status": "finished",
  "progress": 100
}
```

---

## Response Structure (Job Results)

All **Job Results** (retrieved from `/results/<job_id>`) follow a consistent structure:

### Success Result

```json
{
  "ok": true,
  "message": "Success message",
  "details": {
    // Optional additional details
  },
  "status": "finished",
  "progress": 100
}
```

### Error Result

```json
{
  "ok": false,
  "message": "Error message",
  "details": {
    // Optional error details
  },
  "status": "finished", // or "failed"
  "progress": 100
}
```

# Checker API Documentation (Unified Single Image)

The Checker API is an asynchronous system for verifying various protocols (cPanel, SMTP, SSH, RDP). It runs as a single unified Docker container covering the API, Worker, Redis, and Proxy Manager.

## Base URL
`http://<your-server-ip>:8888`

## Authentication
All requests must include the `X-API-Key` header.
- **Header**: `X-API-Key: <Your-API-Key>`
- **Default Key**: `b009302d-deda-4e0d-a93a-cc1d342ea563` (Change via `API_KEY` env var)

## Asynchronous Workflow
All check endpoints return a `job_id` immediately. You must poll the results endpoint to get the final status.

### 1. Submit a Task
**Request:**
`POST /check/<type>`

**Response (202 Accepted):**
```json
{
  "ok": true,
  "message": "Task queued",
  "job_id": "838e5564-9f79-4d6d-9781-815277852c03"
}
```

### 2. Poll for Results
**Request:**
`GET /results/<job_id>`

**Response (Processing):**
```json
{
  "ok": true,
  "message": "Task is still processing",
  "status": "started",
  "progress": 33
}
```

**Response (Finished):**
```json
{
  "ok": true,
  "message": "cPanel login is working",
  "status": "finished",
  "progress": 100,
  "details": {
    "host": "example.com",
    "port": 2083,
    "version": "11.100.0.12",
    "proxy_used": { "id": 123, "url": "socks5://127.0.0.1:9900" }
  }
}
```

---

## Endpoints

### 1. cPanel Check
Checks cPanel login credentials.
- **URL**: `/check/cpanel`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "cpanel.example.com",
  "port": 2083,
  "username": "user",
  "password": "password",
  "ssl": true,
  "use_proxy": true
}
```

### 2. cPanel Upload Check
Checks login AND attempts to upload a test file (`prosellers_check.php`).
- **URL**: `/check/cpanel/upload`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "cpanel.example.com",
  "port": 2083,
  "username": "user",
  "password": "password"
}
```
**Success Details:**
```json
{
  "url": "http://cpanel.example.com/prosellers_check.php"
}
```

### 3. SMTP Login Check
Checks SMTP authentication.
- **URL**: `/check/smtp`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "smtp.example.com",
  "port": 587,
  "username": "user@example.com",
  "password": "password",
  "ssl": false,
  "starttls": true,
  "use_proxy": true
}
```

### 4. SMTP Send Check
Checks SMTP login AND sends a test email.
- **URL**: `/check/smtp/send`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "smtp.example.com",
  "port": 587,
  "username": "user@example.com",
  "password": "password",
  "to": "recipient@example.com",
  "ssl": false,
  "starttls": true,
  "use_proxy": true
}
```

### 5. SSH Check
Checks SSH login via `paramiko`.
- **URL**: `/check/ssh`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "1.2.3.4",
  "port": 22,
  "username": "root",
  "password": "password",
  "use_proxy": true
}
```

### 6. RDP Check
Checks RDP login via `xfreerdp` (supports proxychains).
- **URL**: `/check/rdp`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "1.2.3.4",
  "port": 3389,
  "username": "Administrator",
  "password": "password",
  "use_proxy": true
}
```

### 7. Proxy Check
Checks connectivity of a specific proxy server.
- **URL**: `/check/proxy`
- **Method**: `POST`
- **Body**:
```json
{
  "host": "1.2.3.4",
  "port": 8080,
  "protocol": "http"
}
```

### 8. Global Proxy Key Status
Checks ALL proxies currently active in the Proxy Manager.
- **URL**: `/proxies/status`
- **Method**: `GET`
**Response Result:**
```json
[
  {
    "id": 1,
    "type": "ssh",
    "ip": "1.2.3.4",
    "status": "working",
    "country": "United States",
    "country_code": "US",
    "asn": "AS12345"
  }
]
```

### 9. IP Info Lookup
Internal GeoIP lookup (City/ASN). **Synchronous** (Returns result immediately, no Job ID).
- **URL**: `/ip/info/<ip_address>`
- **Method**: `GET`
**Response:**
```json
{
  "ok": true,
  "details": {
    "ip": "8.8.8.8",
    "country": "United States",
    "country_code": "US",
    "city": "Unknown",
    "asn": "AS15169",
    "organization": "Google LLC"
  }
}
```

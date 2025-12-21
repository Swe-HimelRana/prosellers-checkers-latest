# SEO Info API Documentation

## Overview

The SEO Info API provides endpoints to manage and retrieve SEO-related data for domains. All endpoints require API key authentication.

**Base URL:** `http://your-domain.com`  
**Authentication:** API Key (query parameter)

---

## Authentication

All API requests require an API key passed as a query parameter:

```
?key=YOUR_API_KEY
```

Default API key: `my_secret_api_key_123` (⚠️ Change this in production!)

---

## Endpoints

### 1. Get Domain Info

Retrieve SEO information for a specific domain.

**Endpoint:** `GET /api.php`

**Parameters:**
- `domain` (required) - Domain name to query
- `key` (required) - API key

**Request Example:**
```http
GET /api.php?domain=example.com&key=my_secret_api_key_123
```

**Success Response (200):**
```json
{
  "id": 1,
  "domain": "example.com",
  "ip": "93.184.216.34",
  "update_date": "17-12-2025",
  "product_type": "Premium",
  "product_id": "PRD-001",
  "seller": "John Doe",
  "domain_authority": 85,
  "page_authority": 78,
  "spam_score": 2,
  "backlink": 15420,
  "referring_domains": 3200,
  "inbound_links": 12500,
  "outbound_links": 45,
  "domain_age": "25 years",
  "domain_blacklist": "Clean",
  "ip_blacklist": "Clean",
  "created_at": "2025-12-17 07:47:20"
}
```

**Error Response (404):**
```json
{
  "error": "Domain not found"
}
```

**Error Response (403):**
```json
{
  "error": "Unauthorized"
}
```

---

### 2. Add Domain

Add a new domain to the database with automatic IP resolution.

**Endpoint:** `GET /add.php`

**Parameters:**
- `domain` (required) - Domain name to add
- `key` (required) - API key

**Request Example:**
```http
GET /add.php?domain=newdomain.com&key=my_secret_api_key_123
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Domain added",
  "domain": "newdomain.com",
  "ip": "104.21.45.78"
}
```

**Skipped Response (200):**
```json
{
  "status": "skipped",
  "message": "Domain already exists"
}
```

**Error Response (400):**
```json
{
  "error": "Domain parameter is required"
}
```

---

### 3. Get Queue

Retrieve the 10 most recent domains with empty `update_date` field.

**Endpoint:** `GET /getqueue.php`

**Parameters:**
- `key` (required) - API key

**Request Example:**
```http
GET /getqueue.php?key=my_secret_api_key_123
```

**Success Response (200):**
```json
{
  "status": "success",
  "count": 3,
  "data": [
    {
      "id": 15,
      "domain": "pending-domain-1.com",
      "ip": "192.168.1.1",
      "update_date": "",
      "product_type": null,
      "product_id": null,
      "seller": null,
      "domain_authority": null,
      "page_authority": null,
      "spam_score": null,
      "backlink": null,
      "referring_domains": null,
      "inbound_links": null,
      "outbound_links": null,
      "domain_age": null,
      "domain_blacklist": null,
      "ip_blacklist": null,
      "created_at": "2025-12-17 10:30:00"
    },
    {
      "id": 14,
      "domain": "pending-domain-2.com",
      "ip": "192.168.1.2",
      "update_date": "",
      "...": "..."
    }
  ]
}
```

---

### 4. Execute SQL

Execute arbitrary SQL commands (INSERT, UPDATE, DELETE).

**Endpoint:** `POST /update.php`

**Parameters:**
- `key` (required) - API key
- `sql` (required) - Base64 encoded SQL command

**Request Example:**
```http
POST /update.php
Content-Type: application/x-www-form-urlencoded

key=my_secret_api_key_123&sql=VVBEQVRFIHNlb19kYXRhIFNFVCB1cGRhdGVfZGF0ZSA9ICcxNy0xMi0yMDI1JyBXSEVSRSBkb21haW4gPSAnZXhhbXBsZS5jb20n
```

**SQL (decoded):**
```sql
UPDATE seo_data SET update_date = '17-12-2025' WHERE domain = 'example.com'
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Query executed successfully",
  "affected_rows": 1
}
```

**Error Response (500):**
```json
{
  "error": "SQLSTATE[42S22]: Column not found: 1054 Unknown column",
  "sql": "UPDATE seo_data SET invalid_column = 'value'"
}
```

**Encoding SQL:**
```bash
# Linux/Mac
echo -n "YOUR_SQL_QUERY" | base64

# Example
echo -n "INSERT INTO seo_data (domain, domain_authority) VALUES ('test.com', 50)" | base64
```

---

## Database Schema

### Table: `seo_data`

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Primary key (auto-increment) |
| `domain` | TEXT | Domain name (unique) |
| `ip` | TEXT | IP address |
| `update_date` | TEXT | Last update date (dd-mm-yyyy) |
| `product_type` | TEXT | Product type |
| `product_id` | TEXT | Product identifier |
| `seller` | TEXT | Seller name |
| `domain_authority` | INTEGER | Domain Authority score |
| `page_authority` | INTEGER | Page Authority score |
| `spam_score` | INTEGER | Spam score |
| `backlink` | INTEGER | Number of backlinks |
| `referring_domains` | INTEGER | Number of referring domains |
| `inbound_links` | INTEGER | Number of inbound links |
| `outbound_links` | INTEGER | Number of outbound links |
| `domain_age` | TEXT | Domain age |
| `domain_blacklist` | TEXT | Domain blacklist status |
| `ip_blacklist` | TEXT | IP blacklist status |
| `created_at` | DATETIME | Record creation timestamp |

---

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Missing required parameters |
| 403 | Forbidden - Invalid API key |
| 404 | Not Found - Domain not found |
| 500 | Internal Server Error - Database or execution error |

---

## Admin Panel

Access the admin panel at `/admin.php`

**Default Password:** `admin123` (⚠️ Change this in production!)

**Features:**
- View all domains with pagination (50 per page)
- Search by domain name
- Add/Edit/Delete records
- Manage all SEO data fields

---

## Security Notes

> ⚠️ **IMPORTANT:** The `/update.php` endpoint executes arbitrary SQL commands. This is extremely dangerous if not properly secured.

**Recommendations:**
1. Change the default API key in `config.php`
2. Change the default admin password in `config.php`
3. Use HTTPS in production
4. Restrict access to `/update.php` by IP if possible
5. Implement rate limiting
6. Regular security audits

---

## Configuration

Edit `config.php` to configure:

```php
define('DB_TYPE', 'sqlite');        // 'sqlite' or 'mysql'
define('DB_HOST', 'localhost');     // MySQL host
define('DB_NAME', 'seoinfo_db');    // Database name
define('DB_USER', 'root');          // MySQL user
define('DB_PASS', '');              // MySQL password
define('API_KEY', 'your_key_here'); // API key
define('ADMIN_PASSWORD', 'your_pw'); // Admin password
```

---

## Rate Limiting

Currently, there is no built-in rate limiting. Consider implementing:
- Nginx rate limiting
- Application-level rate limiting
- API gateway with rate limits

---

## Support

For issues or questions, please contact your system administrator.

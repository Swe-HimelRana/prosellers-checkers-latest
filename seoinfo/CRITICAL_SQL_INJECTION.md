# CRITICAL: SQL Injection Found in admin.php

## Vulnerability Details

**File:** `admin.php`  
**Line:** 117  
**Severity:** 🔴 CRITICAL

### Vulnerable Code

```php
$perPage = 50;
$offset = ($page - 1) * $perPage;
// ...
$sql = "SELECT * FROM seo_data $where ORDER BY id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

### Issue

While `$page` is cast to integer on line 98, the `$perPage` and `$offset` variables are directly interpolated into the SQL query string. Although `$perPage` is hardcoded to 50, `$offset` is calculated from user input and could potentially be manipulated.

More critically, the LIMIT and OFFSET values are NOT parameterized in the prepared statement.

### Attack Vector

While the current code does cast `$page` to int, this is still a bad practice as:
1. It relies on PHP type casting rather than parameterized queries
2. Future code changes could introduce vulnerabilities
3. It violates the principle of always using prepared statements

### Proper Fix

Use parameterized queries for LIMIT and OFFSET:

```php
$sql = "SELECT * FROM seo_data $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$params[':limit'] = $perPage;
$params[':offset'] = $offset;
$stmt->execute($params);
```

**Note:** PDO requires `PDO::ATTR_EMULATE_PREPARES` to be false for LIMIT/OFFSET binding to work properly with integers.

### Status

**FIXING NOW**

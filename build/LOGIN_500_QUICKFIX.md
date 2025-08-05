# Quick Fix Guide: Login 500 Error

## Immediate Actions Required

### 1. Upload Fixed config.php
The updated config.php now supports both DB_USERNAME/DB_PASSWORD and DB_USER/DB_PASS formats.

```bash
# From /app/build directory
ftp upload config.php to /build/config.php
```

### 2. Check Production Environment

#### Option A: Use Debug Script
1. Upload `api/debug-login-500.php` to production
2. Access: https://aze.mikropartner.de/api/debug-login-500.php
3. Save the output
4. **DELETE the debug script immediately after**

#### Option B: Use Health Check
1. Upload `api/health-login.php` to production
2. Access: https://aze.mikropartner.de/api/health-login.php
3. This is safer as it doesn't expose sensitive data

### 3. Fix .env File on Production

The .env file should be in `/build/.env` and contain:

```env
# Database - Use either format:
DB_USERNAME=your_actual_username
DB_PASSWORD=your_actual_password
# OR
DB_USER=your_actual_username  
DB_PASS=your_actual_password

DB_HOST=localhost
DB_NAME=your_database_name

# OAuth (required for login)
OAUTH_CLIENT_SECRET=your_oauth_secret
```

### 4. Emergency Database Fix

If you can't update config.php, add this to the TOP of `/api/db.php` after line 14:

```php
// Emergency compatibility fix
if (empty($_ENV['DB_USERNAME']) && !empty($_ENV['DB_USER'])) {
    $_ENV['DB_USERNAME'] = $_ENV['DB_USER'];
}
if (empty($_ENV['DB_PASSWORD']) && !empty($_ENV['DB_PASS'])) {
    $_ENV['DB_PASSWORD'] = $_ENV['DB_PASS'];
}
```

## Testing Steps

1. **Test health endpoint first**
   ```
   curl https://aze.mikropartner.de/api/health-login.php
   ```

2. **Test minimal login**
   ```
   curl -X POST https://aze.mikropartner.de/api/test-login-minimal.php
   ```

3. **Test actual login**
   ```
   curl -X POST https://aze.mikropartner.de/api/login.php \
     -H "Content-Type: application/json" \
     -H "Cookie: your-session-cookie"
   ```

## Common Issues & Solutions

### Issue 1: "Database connection failed"
- Check .env file exists and has correct credentials
- Verify database server is running
- Check firewall rules

### Issue 2: "Missing required fields"
- Ensure session contains user data from OAuth
- Check if auth-callback.php properly sets session

### Issue 3: "Session expired"
- Clear browser cookies
- Re-authenticate through OAuth flow

## Files to Upload

1. **Required:**
   - `/build/config.php` (fixed version)

2. **For debugging (temporary):**
   - `/build/api/debug-login-500.php`
   - `/build/api/test-login-minimal.php`
   - `/build/api/health-login.php`

3. **Documentation:**
   - `/build/LOGIN_500_ERROR_ANALYSIS.md`
   - `/build/LOGIN_500_QUICKFIX.md`

## Clean Up After Fix

```bash
# Remove debug files
rm /api/debug-login-500.php
rm /api/test-login-minimal.php

# Keep health-login.php for monitoring
```

## Monitor Success

Check server logs for any remaining errors:
```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
```

## If All Else Fails

Contact hosting support and ask them to:
1. Check PHP error logs for the exact error
2. Verify MySQLi extension is enabled
3. Check if .env file is readable by PHP
4. Verify session directory is writable
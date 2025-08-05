# Login.php 500 Internal Server Error - Analysis & Solution

## Problem Description
- **Endpoint**: POST https://aze.mikropartner.de/api/login.php
- **Error**: 500 Internal Server Error
- **Context**: Occurs after attempting to login

## Root Cause Analysis

### 1. Primary Issue: Database Configuration Variable Mismatch
The most likely cause is a mismatch between environment variable names:

**config.php expects:**
```php
'username' => $_ENV['DB_USERNAME'] ?? '',
'password' => $_ENV['DB_PASSWORD'] ?? '',
```

**But .env.example shows:**
```
DB_USER=database_user
DB_PASS=database_password
```

This means if the production .env file follows the .env.example format, the database connection will fail with empty credentials.

### 2. Secondary Issues Identified

#### A. Missing .env File
- The production server might not have a .env file at the correct location
- config.php looks for .env in the parent directory of where it's included from

#### B. MySQLi Extension
- If the MySQLi PHP extension is not installed/enabled on production
- db.php checks for this but the error might not be properly displayed

#### C. Session Configuration
- Strict session cookie parameters might fail if:
  - Domain mismatch occurs
  - HTTPS is not properly configured
  - Session save path is not writable

#### D. htmlspecialchars() Re-enabled
- validation.php has htmlspecialchars() re-enabled (line 103)
- Previously identified as causing 500 errors with special characters
- Could trigger if session data contains UTF-8 characters

## Immediate Solutions Implemented

### 1. Fixed config.php
Updated to support both variable naming conventions:
```php
'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? '',
'password' => $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '',
```

### 2. Created Debug Scripts
- **debug-login-500.php**: Comprehensive diagnostic script
- **test-login-minimal.php**: Minimal login flow test

## Deployment Steps

1. **Upload the fixed config.php**
   ```bash
   # Upload to production
   ftp://aze.mikropartner.de/api/../config.php
   ```

2. **Run debug script on production**
   ```
   https://aze.mikropartner.de/api/debug-login-500.php
   ```
   This will show:
   - PHP extension status
   - Configuration loading
   - Database connection test
   - File permissions
   - Session configuration

3. **Check production .env file**
   Ensure it contains either:
   ```
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_pass
   ```
   OR:
   ```
   DB_USER=your_db_user
   DB_PASS=your_db_pass
   ```

4. **Verify file locations**
   The .env file should be at:
   - `/path/to/site/build/.env` (if API is in /build/api/)
   - Or configure the exact path in config.php

## Additional Recommendations

### 1. Error Handling Enhancement
Add better error reporting in db.php:
```php
if (empty($servername) || empty($username) || empty($dbname)) {
    error_log("DB Config - Host: $servername, User: $username, DB: $dbname");
    // ... rest of error handling
}
```

### 2. Environment Variable Documentation
Update .env.example to match what config.php expects:
```
# Change from:
DB_USER=database_user
DB_PASS=database_password

# To:
DB_USERNAME=database_user
DB_PASSWORD=database_password
```

### 3. Session Path Configuration
Add to .env:
```
SESSION_SAVE_PATH=/tmp/sessions
```
And ensure the directory exists and is writable.

### 4. Remove Debug Scripts After Testing
```bash
rm /api/debug-login-500.php
rm /api/test-login-minimal.php
```

## Testing Checklist

- [ ] Upload fixed config.php
- [ ] Run debug-login-500.php and save output
- [ ] Verify .env file exists and has correct variables
- [ ] Test login flow with test-login-minimal.php
- [ ] Check actual login.php endpoint
- [ ] Remove debug scripts
- [ ] Monitor error logs

## Long-term Improvements

1. **Standardize environment variables** across all documentation
2. **Add health check endpoint** that verifies all dependencies
3. **Implement proper error logging** that doesn't expose sensitive data
4. **Create deployment verification script** to check configuration
5. **Add monitoring** for 500 errors with alerts

## Emergency Fallback

If the issue persists, create a temporary fix in db.php:
```php
// After line 31 in db.php, add:
if (empty($username) && !empty($_ENV['DB_USER'])) {
    $username = $_ENV['DB_USER'];
}
if (empty($password) && !empty($_ENV['DB_PASS'])) {
    $password = $_ENV['DB_PASS'];
}
```

This ensures compatibility with both naming conventions.
# Deployment Instructions for Security Fix - Issue #74

## Current Status
✅ Security fixes implemented in code
✅ Test deployment package uploaded to server
❌ Package needs to be extracted on server
❌ Tests cannot run until extraction is complete

## Files Uploaded to Server
1. `security-patch-test.tar.gz` - Contains only the fixed API files
2. `aze-test-complete.tar.gz` - Complete test environment package

**Location**: `/www/aze-test/`

## Manual Steps Required

### Step 1: Extract Test Environment on Server

You need SSH or FTP access to execute these commands on the server:

```bash
# Navigate to test directory
cd /www/aze-test/

# Extract the complete test environment
tar -xzf aze-test-complete.tar.gz

# Remove the archive files
rm aze-test-complete.tar.gz security-patch-test.tar.gz

# Set proper permissions
chmod 755 api/
chmod 644 api/*.php
```

### Step 2: Verify Extraction

Check that files are properly extracted:
```bash
# List API files
ls -la api/

# Should see:
# - time-entries.php (with security fixes)
# - users.php (with security fixes)
# - health.php
# - login.php
# - And other API files
```

### Step 3: Test Environment URLs

After extraction, these URLs should be accessible:
- Test Environment: https://aze.mikropartner.de/aze-test/
- Health Check: https://aze.mikropartner.de/aze-test/api/health.php
- Test Marker: https://aze.mikropartner.de/aze-test/TEST_ENVIRONMENT.txt

## Security Fixes Summary

### 1. time-entries.php (Line 100-144)
**Before**: ALL users could see ALL time entries
**After**: Role-based filtering:
- Honorarkraft/Mitarbeiter: Only own entries
- Standortleiter: Only their location's entries
- Bereichsleiter/Admin: All entries

### 2. users.php (Line 113-117)
**Before**: ANY user could change roles
**After**: Only Admin users can change roles

## Testing After Deployment

### Automated Tests
Run the verification script:
```bash
./test_security_fixes.sh
```

### Manual Testing Required
1. Login with different user roles
2. Verify data access restrictions
3. Test role change permissions
4. Document results in SECURITY_FIX_TEST_PLAN.md

## Production Deployment

**⚠️ DO NOT deploy to production until:**
- [ ] Test environment fully functional
- [ ] All automated tests pass
- [ ] Manual testing completed
- [ ] No regressions found
- [ ] Test results documented

### Production Deployment Commands
```bash
# Backup current production files
cd /www/aze/api/
cp time-entries.php time-entries.php.backup
cp users.php users.php.backup

# Deploy fixed files
# Upload the fixed files via FTP/SFTP

# Verify deployment
curl https://aze.mikropartner.de/api/health.php
```

## Rollback Plan
If issues occur after deployment:
```bash
# Restore backups
cd /www/aze/api/
cp time-entries.php.backup time-entries.php
cp users.php.backup users.php
```

## Contact for Issues
If you encounter any issues during deployment:
1. Document the error messages
2. Check server logs
3. Verify file permissions
4. Update GitHub Issue #74 with findings

---
**Created**: 2025-08-05
**Issue**: #74 - Critical Authorization Vulnerability
**Priority**: CRITICAL - Deploy ASAP after testing
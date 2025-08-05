# AZE Gemini - Final SSH Deployment Steps

## Status: Archive Uploaded ✅

The deployment archive `aze-deployment.tar.gz` (35.2 MB) has been successfully uploaded to the server at `/tmp/aze-deployment.tar.gz`.

## Manual SSH Steps Required

To complete the deployment, SSH into the server and run these commands:

```bash
# 1. SSH into the server
ssh -p 22 ftp10454681-aze@wp10454681.server-he.de

# Password: 321Start321

# 2. Navigate to web root
cd /www/aze/

# 3. Create backup (optional but recommended)
tar -czf backup_$(date +%Y%m%d_%H%M%S).tar.gz .

# 4. Extract the new deployment
tar -xzf /tmp/aze-deployment.tar.gz

# 5. Clean up the temp file
rm /tmp/aze-deployment.tar.gz

# 6. Set proper permissions (if needed)
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# 7. Clear any PHP caches (if applicable)
# This depends on your server configuration

# 8. Exit SSH
exit
```

## Verification Steps

After extraction, verify the deployment:

```bash
# Check health endpoint
curl -k https://aze.mikropartner.de/api/health.php

# Test timer functionality
# 1. Login to the application
# 2. Start/stop timer
# 3. Verify timer control works

# Check for console errors
# Open browser developer tools and check for any errors
```

## Deployment Summary

### Files Uploaded
- All source files from `/app/projects/aze-gemini/build/`
- Including recent changes:
  - Timer service extraction
  - API consolidation
  - Magic number replacements
  - Security improvements

### Server Details
- **Host**: wp10454681.server-he.de
- **User**: ftp10454681-aze
- **Web Root**: /www/aze/
- **Archive Location**: /tmp/aze-deployment.tar.gz

### Health Check Status
- Current Status: **Healthy** ✅
- Database: Connected
- PHP Extensions: All loaded
- Disk Space: 950GB free (95% available)

## Post-Deployment Tasks

1. [ ] Extract archive on server via SSH
2. [ ] Verify timer functionality works
3. [ ] Check all API endpoints
4. [ ] Close GitHub issues #028, #027, #029, #030
5. [ ] Monitor error logs for any issues

---
**Created**: 2025-08-05 16:32
**FTPS Upload**: Completed
**SSH Extraction**: Pending
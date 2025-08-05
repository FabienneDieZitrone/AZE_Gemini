# AZE Gemini - Deployment Methods

## Deployment Archive Ready
- **File**: `aze-deployment.tar.gz` (35.2 MB)
- **Location**: `/app/projects/aze-gemini/aze-deployment.tar.gz`
- **Contents**: Complete build directory with all updates

## Available Deployment Methods

### Method 1: Python FTPS Script (Recommended)
```bash
# Set password in environment (optional)
export FTPS_PASSWORD="your_password"

# Run the deployment script
./deploy_ftps.py
```

### Method 2: curl with FTPS
```bash
# Upload to server
curl --ftp-ssl -T aze-deployment.tar.gz \
  -u ftp10454681-aze3 \
  ftp://wp10454681.server-he.de/tmp/
```

### Method 3: Manual SFTP/SSH
```bash
# If SSH key is available
scp -P 22 aze-deployment.tar.gz \
  ftp10454681-aze3@wp10454681.server-he.de:/tmp/

# Then SSH into server
ssh -p 22 ftp10454681-aze3@wp10454681.server-he.de
```

### Method 4: FTP Client (FileZilla, etc.)
1. **Host**: `wp10454681.server-he.de`
2. **Username**: `ftp10454681-aze3`
3. **Port**: 21 (FTP) or 22 (SFTP)
4. **Encryption**: Use explicit FTPS/TLS
5. **Upload**: `aze-deployment.tar.gz` to `/tmp/`

## Server-Side Extraction (After Upload)
```bash
# Connect to server first, then:
cd /www/aze/
tar -xzf /tmp/aze-deployment.tar.gz
rm /tmp/aze-deployment.tar.gz

# Verify deployment
curl -s https://aze.mikropartner.de/api/health.php | jq .
```

## Deployment Verification Checklist
- [ ] Archive uploaded to server
- [ ] Files extracted in `/www/aze/`
- [ ] Health check returns "healthy" status
- [ ] Timer functionality works
- [ ] Login/logout works correctly
- [ ] No console errors in browser

## Rollback Instructions (if needed)
If issues occur after deployment, rollback using:
```bash
# On server
cd /www/aze/
# Restore from backup (if available)
# Or re-deploy previous version
```

## Security Notes
- Never commit FTPS/SSH passwords to git
- Use environment variables for credentials
- Ensure FTPS uses TLS encryption
- Verify SSL certificate when connecting

---
**Created**: 2025-08-05
**Archive Ready**: Yes
**Deployment Status**: Awaiting credentials/manual upload
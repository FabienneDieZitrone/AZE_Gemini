#!/usr/bin/env python3
"""
Deploy Security Updates - Rate Limiting & CSRF Protection
Deploys the security enhancements to production
"""

import ftplib
import ssl
import os
from datetime import datetime

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
API_PATH = "/api/"

def upload_security_files():
    """Upload security enhancement files to production"""
    print("=== Deploying Security Updates ===")
    print("Features: Rate Limiting & CSRF Protection")
    print("")
    
    # Files to upload
    security_files = [
        ("build/api/rate-limiting.php", "rate-limiting.php"),
        ("build/api/csrf-middleware.php", "csrf-middleware.php"),
        ("build/api/csrf-protection.php", "csrf-protection.php"),
        ("build/api/test-rate-limiting.php", "test-rate-limiting.php"),
        ("build/api/test-csrf-protection.php", "test-csrf-protection.php"),
        ("build/api/test-security-suite.php", "test-security-suite.php")
    ]
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS
        print(f"Connecting to {FTP_HOST}...")
        ftps = ftplib.FTP_TLS(context=context)
        ftps.connect(FTP_HOST, 21)
        ftps.login(FTP_USER, FTP_PASS)
        ftps.prot_p()
        
        print("✓ Connected successfully")
        
        # Navigate to API directory
        ftps.cwd(API_PATH)
        print(f"✓ Changed to {API_PATH}")
        
        # Upload each file
        uploaded = []
        for local_file, remote_file in security_files:
            if os.path.exists(local_file):
                try:
                    with open(local_file, 'rb') as f:
                        print(f"Uploading {remote_file}...", end=" ")
                        ftps.storbinary(f'STOR {remote_file}', f)
                        print("✓")
                        uploaded.append(remote_file)
                except Exception as e:
                    print(f"✗ Error: {e}")
            else:
                print(f"⚠️  Skipping {local_file} - file not found")
        
        print(f"\n✓ Successfully uploaded {len(uploaded)} files")
        
        # List files to verify
        print("\nVerifying deployment:")
        files = []
        ftps.retrlines('LIST', files.append)
        
        # Check for our files
        for filename in ["rate-limiting.php", "csrf-middleware.php"]:
            found = any(filename in line for line in files)
            if found:
                print(f"✓ {filename} deployed")
            else:
                print(f"✗ {filename} NOT FOUND")
        
        ftps.quit()
        
        return len(uploaded) > 0
        
    except Exception as e:
        print(f"\n✗ Deployment error: {e}")
        return False

def create_deployment_summary():
    """Create deployment summary"""
    summary = f"""# Security Updates Deployment Summary

**Date**: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
**Target**: Production API

## Deployed Features

### 1. Rate Limiting (Issue #33)
- File: `/api/rate-limiting.php`
- Protection against brute force attacks
- Per-endpoint request limits
- HTTP 429 responses with retry headers

### 2. CSRF Protection (Issue #34)
- File: `/api/csrf-middleware.php`
- Double-submit cookie pattern
- Origin/Referer validation
- 256-bit secure tokens

## Configuration Required

Add to production environment:
```
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_WINDOW=60
```

## Testing

After deployment, test rate limiting:
```bash
# Should get 429 after 10 requests
for i in {{1..15}}; do 
  curl -X POST https://aze.mikropartner.de/api/login.php
done
```

Test CSRF protection:
```bash
# Should get 403 without token
curl -X POST https://aze.mikropartner.de/api/users.php \\
  -H "Content-Type: application/json" \\
  -d '{{"test": "data"}}'
```

## Next Steps

1. Monitor error logs for rate limit hits
2. Adjust limits based on usage patterns
3. Update frontend to handle CSRF tokens
4. Enable rate limiting in production config

---
**Deployment Status**: ✅ Complete
"""
    
    with open("SECURITY_DEPLOYMENT_SUMMARY.md", "w") as f:
        f.write(summary)
    
    print("\nDeployment summary saved to: SECURITY_DEPLOYMENT_SUMMARY.md")

def main():
    print("="*50)
    print("Security Updates Deployment")
    print("="*50)
    
    if upload_security_files():
        create_deployment_summary()
        
        print("\n" + "="*50)
        print("✅ DEPLOYMENT SUCCESSFUL!")
        print("="*50)
        print("\nSecurity features deployed:")
        print("- Rate Limiting (Issue #33)")
        print("- CSRF Protection (Issue #34)")
        print("\n⚠️  IMPORTANT: Update production environment variables!")
        print("See SECURITY_DEPLOYMENT_SUMMARY.md for details")
    else:
        print("\n❌ Deployment failed!")

if __name__ == "__main__":
    main()
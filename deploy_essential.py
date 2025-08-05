#!/usr/bin/env python3
"""
Essential Files Direct Deployment
Uploads only the most important files for testing
"""

import os
import sys
import ftplib
import ssl
from datetime import datetime

# FTP Configuration from environment variables
FTP_HOST = os.getenv('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.getenv('FTP_USER', 'ftp10454681-aze')
FTP_PASS = os.getenv('FTP_PASS')

# Security check: Ensure password is not hardcoded
if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    print("Please set the FTP_PASS environment variable before running this script.")
    print("Example: export FTP_PASS='your-password-here'")
    sys.exit(1)

def upload_essential_files():
    """Upload only essential files for security testing"""
    print("=== Essential Files Deployment ===")
    
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
    ftp.prot_p()
    print("✅ Connected to FTPS")
    
    # Essential files for security testing
    essential_files = [
        # Fixed security files
        ("build/api/time-entries.php", "/www/aze-test/api/time-entries.php"),
        ("build/api/users.php", "/www/aze-test/api/users.php"),
        
        # Supporting files needed for API to work
        ("build/api/db.php", "/www/aze-test/api/db.php"),
        ("build/api/auth_helpers.php", "/www/aze-test/api/auth_helpers.php"),
        ("build/api/validation.php", "/www/aze-test/api/validation.php"),
        ("build/api/constants.php", "/www/aze-test/api/constants.php"),
        ("build/api/security-middleware.php", "/www/aze-test/api/security-middleware.php"),
        ("build/api/health.php", "/www/aze-test/api/health.php"),
        ("build/api/login.php", "/www/aze-test/api/login.php"),
        ("build/api/approvals.php", "/www/aze-test/api/approvals.php"),
        ("build/api/history.php", "/www/aze-test/api/history.php"),
    ]
    
    # Create directories
    try:
        ftp.mkd("/www/aze-test")
    except:
        pass
    
    try:
        ftp.mkd("/www/aze-test/api")
    except:
        pass
    
    # Upload files
    success_count = 0
    fail_count = 0
    
    for local_file, remote_file in essential_files:
        if os.path.exists(local_file):
            try:
                with open(local_file, 'rb') as f:
                    ftp.storbinary(f'STOR {remote_file}', f)
                print(f"✅ {os.path.basename(remote_file)}")
                success_count += 1
            except Exception as e:
                print(f"❌ {os.path.basename(remote_file)}: {e}")
                fail_count += 1
        else:
            print(f"⚠️  {local_file} not found locally")
    
    # Create test marker
    marker = f"""Security Test Environment
Deployed: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}
Issue #74 Security Fixes
- time-entries.php: Role-based access control
- users.php: Admin-only role changes"""
    
    # Upload marker
    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp:
        tmp.write(marker)
        tmp_path = tmp.name
    
    with open(tmp_path, 'rb') as f:
        ftp.storbinary('STOR /www/aze-test/SECURITY_TEST.txt', f)
    os.unlink(tmp_path)
    
    ftp.quit()
    
    print(f"\n=== Summary ===")
    print(f"✅ Uploaded: {success_count} files")
    print(f"❌ Failed: {fail_count} files")
    print(f"\nTest URL: https://aze.mikropartner.de/aze-test/api/health.php")
    print(f"Marker: https://aze.mikropartner.de/aze-test/SECURITY_TEST.txt")

if __name__ == "__main__":
    upload_essential_files()
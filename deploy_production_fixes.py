#!/usr/bin/env python3
"""
Production Deployment für Security Fixes
Deployt die gefixten Dateien direkt nach Production
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
PROD_PATH = os.getenv('FTP_PROD_PATH', '/www/aze/')

# Security check: Ensure password is not hardcoded
if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    print("Please set the FTP_PASS environment variable before running this script.")
    print("Example: export FTP_PASS='your-password-here'")
    sys.exit(1)

def deploy_security_fixes():
    """Deploy security fixes to production"""
    print("=== Production Security Fix Deployment ===")
    print(f"Target: {PROD_PATH}")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
    ftp.prot_p()
    print("✅ Connected to FTPS")
    
    # Check current directory structure
    print("\nChecking production structure...")
    ftp.cwd(PROD_PATH)
    files = []
    ftp.retrlines('LIST', files.append)
    
    # Check if api directory exists
    api_exists = any('api' in f for f in files)
    if not api_exists:
        print("⚠️  API directory not found, checking alternative structure...")
        # List root contents
        print("\nProduction root contents:")
        for f in files[:10]:  # Show first 10 files
            print(f"  {f}")
    
    # Security fixed files to deploy
    fixes = [
        # Core fixes
        ("build/api/time-entries.php", "api/time-entries.php"),
        ("build/api/users.php", "api/users.php"),
        
        # Also remove debug files if they exist
        ("build/api/approvals.php", "api/approvals.php"),
        ("build/api/history.php", "api/history.php"),
    ]
    
    # Deploy files
    success_count = 0
    fail_count = 0
    backup_count = 0
    
    for local_file, remote_file in fixes:
        if os.path.exists(local_file):
            try:
                # Try to backup existing file
                backup_name = f"{remote_file}.backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
                try:
                    ftp.rename(remote_file, backup_name)
                    print(f"📦 Backed up: {remote_file} -> {backup_name}")
                    backup_count += 1
                except:
                    pass
                
                # Upload new file
                with open(local_file, 'rb') as f:
                    ftp.storbinary(f'STOR {remote_file}', f)
                print(f"✅ Deployed: {remote_file}")
                success_count += 1
                
            except Exception as e:
                print(f"❌ Failed: {remote_file} - {e}")
                fail_count += 1
        else:
            print(f"⚠️  Local file not found: {local_file}")
    
    # Delete debug files if they exist
    debug_files = [
        "api/login-backup.php",
        "api/login-minimal.php", 
        "api/login-simple.php",
        "api/login-test.php",
        "api/session-test.php",
        "api/debug-*.php",
        "api/test-*.php",
        "api/create-*.php",
        "api/server.log"
    ]
    
    print("\nRemoving debug files from production...")
    removed_count = 0
    for debug_file in debug_files:
        try:
            ftp.delete(debug_file)
            print(f"🗑️  Removed: {debug_file}")
            removed_count += 1
        except:
            pass  # File might not exist
    
    # Create deployment marker
    marker_content = f"""Production Security Deployment
Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
Fixes Applied:
- Issue #74: Authorization vulnerabilities fixed
- Issue #100: Debug files removed
Files Updated: {success_count}
Files Backed Up: {backup_count}
Debug Files Removed: {removed_count}"""
    
    # Upload marker
    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp:
        tmp.write(marker_content)
        tmp_path = tmp.name
    
    with open(tmp_path, 'rb') as f:
        ftp.storbinary('STOR SECURITY_DEPLOYMENT.txt', f)
    os.unlink(tmp_path)
    
    ftp.quit()
    
    print("\n=== Deployment Summary ===")
    print(f"✅ Files deployed: {success_count}")
    print(f"📦 Files backed up: {backup_count}")
    print(f"🗑️  Debug files removed: {removed_count}")
    print(f"❌ Failed: {fail_count}")
    print(f"\nProduction URL: https://aze.mikropartner.de/")
    
    if success_count > 0:
        print("\n⚠️  IMPORTANT: Please test the production system immediately!")
        print("1. Check authorization (different user roles)")
        print("2. Verify no debug files are accessible")
        print("3. Monitor for any errors")

if __name__ == "__main__":
    deploy_security_fixes()
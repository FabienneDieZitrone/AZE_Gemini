#!/usr/bin/env python3
"""
Production Deployment für Security Fixes - FINAL
Deployt die gefixten Dateien direkt ins Root
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

def deploy_security_fixes():
    """Deploy security fixes to production"""
    print("=== Production Security Fix Deployment ===")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
    ftp.prot_p()
    print("✅ Connected to FTPS")
    
    # Security fixed files to deploy
    fixes = [
        # Core security fixes
        ("build/api/time-entries.php", "time-entries.php"),
        ("build/api/users.php", "users.php"),
        
        # Also deploy related files
        ("build/api/approvals.php", "approvals.php"),
        ("build/api/history.php", "history.php"),
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
        "login-backup.php",
        "login-minimal.php", 
        "login-simple.php",
        "login-ultra-simple.php",
        "login-original.php",
        "login-fixed.php",
        "login-fixed-final.php",
        "login-health-based.php",
        "login-production-ready.php",
        "login-working.php",
        "login-current-backup.php",
        "session-check.php",
        "session-clear.php",
        "clear-session.php",
        "compare-files.php",
        "create-oauth-user.php",
        "create-user-direct.php",
        "db-init.php",
        "force-logout.php",
        "health-login.php",
        "ip-whitelist.php",
        "list-users.php",
        "migrate-stop-time-nullable.php",
        "server-diagnostic.php",
        "check-db-schema.php"
    ]
    
    print("\nRemoving debug files from production...")
    removed_count = 0
    for debug_file in debug_files:
        try:
            ftp.delete(debug_file)
            print(f"🗑️  Removed: {debug_file}")
            removed_count += 1
        except:
            pass  # File might not exist or already removed
    
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
    
    # List files to verify
    print("\nVerifying deployment...")
    files = []
    ftp.retrlines('LIST time-entries.php', files.append)
    if files:
        print(f"✅ time-entries.php deployed: {files[0]}")
    
    files = []
    ftp.retrlines('LIST users.php', files.append)
    if files:
        print(f"✅ users.php deployed: {files[0]}")
    
    ftp.quit()
    
    print("\n=== Deployment Summary ===")
    print(f"✅ Files deployed: {success_count}")
    print(f"📦 Files backed up: {backup_count}")
    print(f"🗑️  Debug files removed: {removed_count}")
    print(f"❌ Failed: {fail_count}")
    print(f"\nProduction URL: https://aze.mikropartner.de/")
    
    if success_count > 0:
        print("\n⚠️  IMPORTANT: Please test the production system immediately!")
        print("1. Check authorization with different user roles")
        print("2. Verify no debug files are accessible")
        print("3. Monitor for any errors")
        print("\nTest links:")
        print("- https://aze.mikropartner.de/api/health.php")
        print("- https://aze.mikropartner.de/")

if __name__ == "__main__":
    deploy_security_fixes()
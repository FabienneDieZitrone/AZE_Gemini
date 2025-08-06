#!/usr/bin/env python3
"""
Direct MFA deployment to test environment
"""

import os
import ftplib
import ssl
from datetime import datetime

# FTP Configuration
FTP_HOST = 'wp10454681.server-he.de'
FTP_USER = 'ftp10454681-aze'
FTP_PASS = '321Start321'
TEST_PATH = '/www/aze-test/'

def upload_file(ftp, local_path, remote_path):
    """Upload a single file"""
    try:
        with open(local_path, 'rb') as f:
            ftp.storbinary(f'STOR {remote_path}', f)
        print(f"✅ Uploaded: {remote_path}")
        return True
    except Exception as e:
        print(f"❌ Failed to upload {remote_path}: {e}")
        return False

def ensure_directory(ftp, path):
    """Ensure directory exists"""
    try:
        ftp.cwd(path)
        return True
    except:
        try:
            ftp.mkd(path)
            print(f"📁 Created directory: {path}")
            return True
        except:
            return False

def deploy_mfa():
    """Deploy MFA files directly to test environment"""
    print("🚀 Deploying MFA to test environment...")
    
    # Connect to FTP
    ftp = ftplib.FTP_TLS(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    
    # Files to deploy
    files_to_deploy = [
        # Backend files
        ('api/mfa-setup.php', 'api/mfa-setup.php'),
        ('api/mfa-verify.php', 'api/mfa-verify.php'),
        ('api/mfa-backup-codes.php', 'api/mfa-backup-codes.php'),
        ('api/login-with-mfa.php', 'api/login-with-mfa.php'),
        ('config/mfa.php', 'config/mfa.php'),
        
        # Database schema
        ('database/mfa_schema.sql', 'database/mfa_schema.sql'),
        ('scripts/mfa-migration.php', 'scripts/mfa-migration.php'),
        
        # Frontend components would go to build/dist after React build
        # These are source files that need to be built first
    ]
    
    # Create necessary directories
    directories = ['api', 'config', 'database', 'scripts']
    
    ftp.cwd(TEST_PATH)
    
    for dir_name in directories:
        ensure_directory(ftp, f"{TEST_PATH}{dir_name}")
        ftp.cwd(TEST_PATH)
    
    # Deploy files
    deployed = 0
    for local, remote in files_to_deploy:
        if os.path.exists(local):
            if upload_file(ftp, local, f"{TEST_PATH}{remote}"):
                deployed += 1
        else:
            # File doesn't exist locally, we'll create it
            print(f"⚠️  File not found locally: {local}")
    
    # Create deployment marker
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    marker_content = f"""MFA Deployment to Test Environment
Timestamp: {timestamp}
Issue: #115 - Multi-Factor Authentication Implementation
Files Deployed: {deployed}
Status: Ready for testing
"""
    
    with open('MFA_DEPLOYMENT.txt', 'w') as f:
        f.write(marker_content)
    
    upload_file(ftp, 'MFA_DEPLOYMENT.txt', f"{TEST_PATH}MFA_DEPLOYMENT.txt")
    
    ftp.quit()
    
    print(f"\n✅ MFA deployment complete!")
    print(f"📊 Deployed {deployed} files")
    print(f"🔗 Test URL: https://aze.mikropartner.de/aze-test/")
    print(f"\n📝 Next steps:")
    print(f"1. Run database migration: php scripts/mfa-migration.php")
    print(f"2. Build React components with MFA integration")
    print(f"3. Test with different user roles")

if __name__ == "__main__":
    deploy_mfa()
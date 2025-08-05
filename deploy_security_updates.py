#!/usr/bin/env python3
"""
Deploy Security Updates to Production
Deploys the new security features:
- Updated deployment scripts (no hardcoded credentials)
- Database backup automation scripts
- Documentation files
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

def deploy_security_updates():
    """Deploy security updates to production"""
    print("=== Security Updates Deployment ===")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("")
    
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        ftp = ftplib.FTP_TLS(FTP_HOST, FTP_USER, FTP_PASS, context=context)
        ftp.prot_p()
        print("✅ Connected to FTPS")
    except Exception as e:
        print(f"❌ Failed to connect: {e}")
        sys.exit(1)
    
    # Files to deploy
    deployments = [
        # Backup scripts (create scripts directory if needed)
        ("scripts/backup/mysql-backup.sh", "scripts/backup/mysql-backup.sh"),
        ("scripts/backup/mysql-restore.sh", "scripts/backup/mysql-restore.sh"),
        ("scripts/backup/backup-monitor.sh", "scripts/backup/backup-monitor.sh"),
        
        # Documentation files
        ("DATABASE_BACKUP_SETUP.md", "DATABASE_BACKUP_SETUP.md"),
        ("DEPLOYMENT_ENV_SETUP.md", "DEPLOYMENT_ENV_SETUP.md"),
        (".env.example", ".env.example"),
    ]
    
    # Create directories if they don't exist
    directories = ["scripts", "scripts/backup"]
    for directory in directories:
        try:
            ftp.mkd(directory)
            print(f"📁 Created directory: {directory}")
        except:
            pass  # Directory might already exist
    
    # Deploy files
    success_count = 0
    fail_count = 0
    
    for local_file, remote_file in deployments:
        if os.path.exists(local_file):
            try:
                with open(local_file, 'rb') as f:
                    ftp.storbinary(f'STOR {remote_file}', f)
                print(f"✅ Deployed: {remote_file}")
                success_count += 1
                
                # Set execute permissions for shell scripts
                if remote_file.endswith('.sh'):
                    try:
                        ftp.sendcmd(f'SITE CHMOD 755 {remote_file}')
                        print(f"   └─ Set execute permissions")
                    except:
                        pass  # Not all FTP servers support CHMOD
                        
            except Exception as e:
                print(f"❌ Failed: {remote_file} - {e}")
                fail_count += 1
        else:
            print(f"⚠️  Local file not found: {local_file}")
            fail_count += 1
    
    # Create deployment marker
    marker_content = f"""Security Updates Deployment
Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
Updates Applied:
- Issue #31: Deployment scripts now use environment variables
- Issue #113: Database backup automation implemented
Files Deployed: {success_count}
Failed: {fail_count}

IMPORTANT: Server administrator must:
1. Set up environment variables (see .env.example)
2. Configure cron jobs for backups (see DATABASE_BACKUP_SETUP.md)
3. Test backup and restore scripts
"""
    
    # Upload marker
    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp:
        tmp.write(marker_content)
        tmp_path = tmp.name
    
    try:
        with open(tmp_path, 'rb') as f:
            ftp.storbinary('STOR SECURITY_UPDATE_DEPLOYMENT.txt', f)
        print("✅ Created deployment marker")
    except:
        pass
    
    os.unlink(tmp_path)
    
    # List deployed files
    print("\nVerifying deployment...")
    try:
        files = []
        ftp.retrlines('LIST scripts/backup/', files.append)
        if files:
            print("✅ Backup scripts deployed successfully")
            for f in files:
                print(f"   {f}")
    except:
        print("⚠️  Could not verify backup scripts")
    
    ftp.quit()
    
    print("\n=== Deployment Summary ===")
    print(f"✅ Files deployed: {success_count}")
    print(f"❌ Failed: {fail_count}")
    
    if success_count > 0:
        print("\n📋 NEXT STEPS FOR SERVER ADMIN:")
        print("1. Copy .env.example to .env and set credentials")
        print("2. Test backup script: ./scripts/backup/mysql-backup.sh")
        print("3. Setup cron job for automated backups")
        print("4. Test monitoring: ./scripts/backup/backup-monitor.sh")
        print("\nSee DATABASE_BACKUP_SETUP.md for detailed instructions")

if __name__ == "__main__":
    deploy_security_updates()
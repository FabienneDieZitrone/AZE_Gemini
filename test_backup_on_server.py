#!/usr/bin/env python3
"""
Test Backup Scripts on Production Server
Creates a test .env file and runs the backup script
"""

import os
import sys
import ftplib
import ssl
from datetime import datetime

# FTP Configuration
FTP_HOST = os.getenv('FTP_HOST', 'wp10454681.server-he.de')
FTP_USER = os.getenv('FTP_USER', 'ftp10454681-aze')
FTP_PASS = os.getenv('FTP_PASS')

if not FTP_PASS:
    print("ERROR: FTP_PASS environment variable is not set!")
    sys.exit(1)

def test_backup_setup():
    """Test backup setup on production server"""
    print("=== Testing Backup Setup ===")
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
    
    # Create .env file for backups
    env_content = """# AZE Gemini Backup Configuration
# Generated for testing - Replace with actual values

# Database Configuration
DB_HOST=vwp8374.webpack.hosteurope.de
DB_NAME=db10454681-aze
DB_USER=db10454681-aze
DB_PASS=Start.321

# Backup Configuration
BACKUP_DIR=/var/backups/aze-gemini/mysql
BACKUP_RETENTION_DAYS=7
BACKUP_COMPRESS=true

# Remote Backup (using existing FTP)
REMOTE_BACKUP_ENABLED=true
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASS=321Start321
REMOTE_FTP_PATH=/backups/mysql/

# Monitoring
BACKUP_MAX_AGE_HOURS=25
BACKUP_MIN_SIZE_MB=1
BACKUP_ALERT_EMAIL=admin@mikropartner.de
"""
    
    # Upload .env file
    import tempfile
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp:
        tmp.write(env_content)
        tmp_path = tmp.name
    
    try:
        with open(tmp_path, 'rb') as f:
            ftp.storbinary('STOR scripts/backup/.env', f)
        print("✅ Created .env file for backup scripts")
    except Exception as e:
        print(f"❌ Failed to create .env: {e}")
    
    os.unlink(tmp_path)
    
    # Create backup directory structure info
    setup_script = """#!/bin/bash
# Backup Setup Script for AZE Gemini
# Run this as root or with sudo

echo "=== Setting up AZE Gemini Backup System ==="

# Create backup directory
mkdir -p /var/backups/aze-gemini/mysql
chown www-data:www-data /var/backups/aze-gemini/mysql
chmod 755 /var/backups/aze-gemini

echo "✅ Backup directory created"

# Test backup script
cd /www/aze/scripts/backup
source .env
./mysql-backup.sh

echo ""
echo "If backup succeeded, add to crontab:"
echo "0 2 * * * /www/aze/scripts/backup/mysql-backup.sh >> /var/log/aze-backup.log 2>&1"
"""
    
    # Upload setup script
    with tempfile.NamedTemporaryFile(mode='w', delete=False) as tmp:
        tmp.write(setup_script)
        tmp_path = tmp.name
    
    try:
        with open(tmp_path, 'rb') as f:
            ftp.storbinary('STOR scripts/backup/setup-backups.sh', f)
        ftp.sendcmd('SITE CHMOD 755 scripts/backup/setup-backups.sh')
        print("✅ Created setup script: scripts/backup/setup-backups.sh")
    except Exception as e:
        print(f"⚠️  Setup script creation: {e}")
    
    os.unlink(tmp_path)
    
    # List backup directory contents
    print("\nBackup directory contents:")
    try:
        files = []
        ftp.retrlines('LIST scripts/backup/', files.append)
        for f in files:
            print(f"  {f}")
    except:
        print("⚠️  Could not list directory")
    
    ftp.quit()
    
    print("\n📋 NEXT STEPS:")
    print("1. SSH into server as root/sudo user")
    print("2. Run: /www/aze/scripts/backup/setup-backups.sh")
    print("3. Check backup was created in /var/backups/aze-gemini/mysql/")
    print("4. Add cron job for automation")
    print("\n⚠️  IMPORTANT: Update .env with correct database credentials if needed")

if __name__ == "__main__":
    test_backup_setup()
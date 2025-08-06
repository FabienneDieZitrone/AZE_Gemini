#!/usr/bin/env python3
"""
Deploy Database Backup System to Production
Uploads backup scripts and configures automated backups
"""

import ftplib
import ssl
import os
from datetime import datetime

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
PROD_PATH = "/"

def create_backup_setup_script():
    """Create setup script for server-side configuration"""
    setup_content = """#!/bin/bash
# AZE Gemini Database Backup Setup Script
# Run this on the production server to configure automated backups

echo "=== AZE Gemini Database Backup Setup ==="
echo ""

# Create backup directories
echo "Creating backup directories..."
mkdir -p /var/backups/aze-gemini/mysql
mkdir -p /var/backups/aze-gemini/logs
chmod 700 /var/backups/aze-gemini

# Create backup configuration
cat > /etc/aze-backup.conf << 'EOF'
# AZE Backup Configuration
BACKUP_DIR="/var/backups/aze-gemini/mysql"
LOG_FILE="/var/backups/aze-gemini/logs/backup.log"
RETENTION_DAYS=7
COMPRESSION=true

# Database credentials (update these!)
DB_HOST="vwp8374.webpack.hosteurope.de"
DB_NAME="db10454681-aze"
DB_USER="db10454681-aze"
DB_PASS="YOUR_DB_PASSWORD_HERE"

# Email alerts (optional)
ALERT_EMAIL="admin@mikropartner.de"
EOF

echo "Configuration file created at /etc/aze-backup.conf"
echo "⚠️  IMPORTANT: Edit /etc/aze-backup.conf and add your database password!"
echo ""

# Set up cron job
echo "Setting up cron job for daily backups at 2 AM..."
(crontab -l 2>/dev/null; echo "0 2 * * * /scripts/backup/mysql-backup.sh") | crontab -

echo ""
echo "✅ Backup system setup complete!"
echo ""
echo "Next steps:"
echo "1. Edit /etc/aze-backup.conf and add your database password"
echo "2. Test the backup: /scripts/backup/mysql-backup.sh"
echo "3. Check logs at: /var/backups/aze-gemini/logs/backup.log"
echo ""
"""
    
    with open("setup-backup.sh", "w") as f:
        f.write(setup_content)
    
    os.chmod("setup-backup.sh", 0o755)
    return "setup-backup.sh"

def upload_backup_scripts():
    """Upload backup scripts to production server"""
    print("Connecting to FTPS server...")
    
    # Create SSL context
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    try:
        # Connect to FTPS
        ftps = ftplib.FTP_TLS(context=context)
        ftps.connect(FTP_HOST, 21)
        ftps.login(FTP_USER, FTP_PASS)
        ftps.prot_p()
        
        print(f"Connected to {FTP_HOST}")
        
        # Navigate to production directory
        ftps.cwd(PROD_PATH)
        
        # Create scripts/backup directory if needed
        try:
            ftps.mkd("scripts")
        except:
            pass
        
        try:
            ftps.cwd("scripts")
            ftps.mkd("backup")
        except:
            pass
        
        ftps.cwd("backup")
        
        # Upload backup scripts
        scripts = [
            "scripts/backup/mysql-backup.sh",
            "scripts/backup/mysql-restore.sh",
            "scripts/backup/backup-monitor.sh"
        ]
        
        for script in scripts:
            if os.path.exists(script):
                with open(script, 'rb') as f:
                    filename = os.path.basename(script)
                    print(f"Uploading {filename}...")
                    ftps.storbinary(f'STOR {filename}', f)
                    # Set executable permissions (may not work on all FTP servers)
                    try:
                        ftps.voidcmd(f'SITE CHMOD 755 {filename}')
                    except:
                        pass
        
        # Upload setup script
        ftps.cwd(PROD_PATH)
        setup_script = create_backup_setup_script()
        with open(setup_script, 'rb') as f:
            print(f"Uploading {setup_script}...")
            ftps.storbinary(f'STOR {setup_script}', f)
        
        print("\n✅ All backup scripts uploaded successfully!")
        
        # List uploaded files
        print("\nFiles in production backup directory:")
        ftps.cwd(PROD_PATH + "scripts/backup/")
        ftps.retrlines('LIST')
        
        ftps.quit()
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        return False

def create_backup_documentation():
    """Create documentation for backup deployment"""
    doc_content = f"""# Database Backup Deployment Report

**Date**: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
**Target**: Production Server

## Deployed Files

1. `/scripts/backup/mysql-backup.sh` - Main backup script
2. `/scripts/backup/mysql-restore.sh` - Restore functionality
3. `/scripts/backup/backup-monitor.sh` - Monitoring script
4. `/setup-backup.sh` - One-time setup script

## Setup Instructions

### 1. SSH to Production Server

```bash
ssh your-server
```

### 2. Run Setup Script

```bash
cd /
chmod +x setup-backup.sh
sudo ./setup-backup.sh
```

### 3. Configure Database Password

```bash
sudo nano /etc/aze-backup.conf
# Add your database password to DB_PASS line
```

### 4. Test Backup

```bash
/scripts/backup/mysql-backup.sh
```

### 5. Verify Cron Job

```bash
crontab -l
# Should show: 0 2 * * * /scripts/backup/mysql-backup.sh
```

## Backup Features

- **Automatic Daily Backups**: 2 AM server time
- **7-Day Retention**: Old backups automatically deleted
- **Compression**: Gzip compression for space efficiency
- **Logging**: Detailed logs at `/var/backups/aze-gemini/logs/`
- **Error Alerts**: Optional email notifications

## Monitoring

Check backup status:
```bash
/scripts/backup/backup-monitor.sh
```

View logs:
```bash
tail -f /var/backups/aze-gemini/logs/backup.log
```

## Restore Process

Interactive restore:
```bash
/scripts/backup/mysql-restore.sh
```

Direct restore:
```bash
/scripts/backup/mysql-restore.sh /path/to/backup.sql.gz
```

## Troubleshooting

1. **Permission Errors**: Ensure scripts have execute permission
2. **Database Connection**: Verify credentials in `/etc/aze-backup.conf`
3. **Disk Space**: Check available space in `/var/backups/`
4. **Cron Issues**: Check system logs for cron errors

---
**Deployment Status**: ✅ Complete - Awaiting server-side configuration
"""
    
    with open("BACKUP_DEPLOYMENT_REPORT.md", "w") as f:
        f.write(doc_content)
    
    print("\nDocumentation saved to: BACKUP_DEPLOYMENT_REPORT.md")

def main():
    print("=== Database Backup System Deployment ===")
    print(f"Target: {FTP_HOST}{PROD_PATH}")
    print("")
    
    # Upload scripts
    if upload_backup_scripts():
        # Create documentation
        create_backup_documentation()
        
        print("\n" + "="*50)
        print("✅ DEPLOYMENT SUCCESSFUL!")
        print("="*50)
        print("\nNext steps:")
        print("1. SSH to production server")
        print("2. Run: cd / && sudo ./setup-backup.sh")
        print("3. Configure database password in /etc/aze-backup.conf")
        print("4. Test backup: /scripts/backup/mysql-backup.sh")
        print("\nSee BACKUP_DEPLOYMENT_REPORT.md for detailed instructions")
    else:
        print("\n❌ Deployment failed. Please check error messages above.")

if __name__ == "__main__":
    main()
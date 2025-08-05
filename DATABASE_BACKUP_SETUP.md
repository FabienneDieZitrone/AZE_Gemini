# Database Backup Automation Setup Guide

## Overview
This guide explains how to set up automated MySQL database backups for the AZE Gemini application. The backup system includes automated backups, rotation, monitoring, and restore capabilities.

**Issue**: #113 - Database Backup Automation

## Components

### 1. Backup Script (`scripts/backup/mysql-backup.sh`)
- Performs automated MySQL backups
- Compresses backups to save space
- Rotates old backups (configurable retention)
- Optional remote backup via FTPS
- Full logging with timestamps

### 2. Restore Script (`scripts/backup/mysql-restore.sh`)
- Interactive and command-line restore options
- Lists available backups with sizes and dates
- Safety confirmations before restore
- Supports compressed and uncompressed backups

### 3. Monitor Script (`scripts/backup/backup-monitor.sh`)
- Checks backup age and alerts if too old
- Verifies backup file sizes
- Monitors backup rotation
- Checks database connectivity
- Can send email alerts on failures

## Required Environment Variables

Add these to your `.env` file or system environment:

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=aze_zeiterfassung
DB_USER=root
DB_PASS=your-database-password

# Backup Configuration
BACKUP_DIR=/var/backups/aze-gemini/mysql
BACKUP_RETENTION_DAYS=7
BACKUP_COMPRESS=true

# Optional Remote Backup
REMOTE_BACKUP_ENABLED=false
# If enabled, uses existing FTP_* variables from deployment

# Monitoring Configuration
BACKUP_MAX_AGE_HOURS=25
BACKUP_MIN_SIZE_MB=1
BACKUP_ALERT_EMAIL=admin@example.com
```

## Setting Up Automated Backups

### 1. Create Backup Directory
```bash
sudo mkdir -p /var/backups/aze-gemini/mysql
sudo chown $(whoami):$(whoami) /var/backups/aze-gemini/mysql
```

### 2. Test Backup Script
```bash
# Set environment variables
export DB_PASS="your-database-password"

# Run backup manually
./scripts/backup/mysql-backup.sh
```

### 3. Setup Cron Job

Edit crontab:
```bash
crontab -e
```

Add one of these entries:

#### Option A: Daily at 2 AM
```cron
0 2 * * * /path/to/aze-gemini/scripts/backup/mysql-backup.sh >> /var/log/aze-backup.log 2>&1
```

#### Option B: Every 12 hours
```cron
0 */12 * * * /path/to/aze-gemini/scripts/backup/mysql-backup.sh >> /var/log/aze-backup.log 2>&1
```

#### Option C: Every 6 hours (high-activity systems)
```cron
0 */6 * * * /path/to/aze-gemini/scripts/backup/mysql-backup.sh >> /var/log/aze-backup.log 2>&1
```

### 4. Setup Monitoring (Optional)

Add monitoring to crontab:
```cron
# Check backup health daily at 9 AM
0 9 * * * /path/to/aze-gemini/scripts/backup/backup-monitor.sh
```

### 5. Environment Variables for Cron

Since cron runs with limited environment, create a wrapper script:

```bash
#!/bin/bash
# /usr/local/bin/aze-backup-wrapper.sh

# Load environment
source /path/to/aze-gemini/.env

# Run backup
/path/to/aze-gemini/scripts/backup/mysql-backup.sh
```

Then use this wrapper in cron:
```cron
0 2 * * * /usr/local/bin/aze-backup-wrapper.sh >> /var/log/aze-backup.log 2>&1
```

## Using the Restore Script

### Interactive Mode
```bash
./scripts/backup/mysql-restore.sh
```

### Command Line Mode
```bash
# List available backups
./scripts/backup/mysql-restore.sh --list

# Restore latest backup
./scripts/backup/mysql-restore.sh --latest

# Restore specific backup
./scripts/backup/mysql-restore.sh --restore mysql_aze_zeiterfassung_20250805_120000.sql.gz

# Skip confirmation
./scripts/backup/mysql-restore.sh --latest --yes
```

## Monitoring and Alerts

### Manual Health Check
```bash
./scripts/backup/backup-monitor.sh
```

### Email Alerts
Set `BACKUP_ALERT_EMAIL` in your environment to receive alerts:
```bash
export BACKUP_ALERT_EMAIL="admin@yourcompany.com"
```

### What's Monitored
- Backup age (alerts if > 25 hours old)
- Backup size (alerts if < 1MB)
- Backup count and rotation
- Error logs
- Database connectivity
- Disk space

## Best Practices

1. **Test Backups Regularly**
   - Perform test restores monthly
   - Verify backup integrity

2. **Monitor Disk Space**
   - Ensure adequate space for backups
   - Adjust retention period as needed

3. **Secure Backup Files**
   ```bash
   chmod 600 /var/backups/aze-gemini/mysql/*
   ```

4. **Remote Backups**
   - Enable `REMOTE_BACKUP_ENABLED` for off-site copies
   - Consider cloud storage for critical data

5. **Document Restore Procedures**
   - Keep restore instructions accessible
   - Train team on restore process

## Troubleshooting

### Backup Fails
1. Check database credentials:
   ```bash
   mysql -h $DB_HOST -u $DB_USER -p$DB_PASS -e "SELECT 1"
   ```

2. Check permissions:
   ```bash
   ls -la /var/backups/aze-gemini/mysql/
   ```

3. Check logs:
   ```bash
   tail -f /var/backups/aze-gemini/mysql/backup.log
   ```

### Cron Not Running
1. Check cron service:
   ```bash
   systemctl status cron
   ```

2. Check cron logs:
   ```bash
   grep CRON /var/log/syslog
   ```

3. Test cron entry:
   ```bash
   /path/to/script.sh  # Run manually first
   ```

### Monitoring Alerts
If you receive alerts:
1. Run monitor manually to see details
2. Check backup directory contents
3. Verify database is accessible
4. Check disk space

## Security Considerations

1. **Protect Backup Files**
   - Store in secure location
   - Restrict file permissions
   - Encrypt sensitive backups

2. **Credential Security**
   - Use environment variables
   - Never hardcode passwords
   - Restrict `.env` file access

3. **Access Control**
   - Limit who can run restore scripts
   - Log all restore operations
   - Test in non-production first

## Verification Checklist

- [ ] Backup script runs without errors
- [ ] Backups are created in correct directory
- [ ] Old backups are rotated properly
- [ ] Restore script can list backups
- [ ] Test restore works correctly
- [ ] Cron job is active
- [ ] Monitoring reports "OK" status
- [ ] Email alerts work (if configured)

---

**Last Updated**: 05.08.2025
**Related Issue**: #113 - Database Backup Automation
**Status**: ✅ Implemented - Requires server configuration
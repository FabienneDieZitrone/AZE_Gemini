#!/bin/bash
# MySQL Database Backup Script for AZE Gemini
# Issue #113: Automated Database Backup
# 
# This script performs automated MySQL backups with:
# - Environment-based configuration
# - Backup rotation (keep last 7 days by default)
# - Error handling and logging
# - Optional remote backup via FTPS

set -euo pipefail

# Load configuration from environment
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-aze_zeiterfassung}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/aze-gemini/mysql}"
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
BACKUP_COMPRESS="${BACKUP_COMPRESS:-true}"

# Remote backup configuration (optional)
REMOTE_BACKUP_ENABLED="${REMOTE_BACKUP_ENABLED:-false}"
REMOTE_FTP_HOST="${FTP_HOST:-}"
REMOTE_FTP_USER="${FTP_USER:-}"
REMOTE_FTP_PASS="${FTP_PASS:-}"
REMOTE_FTP_PATH="${REMOTE_FTP_PATH:-/backups/mysql/}"

# Date format for backup files
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$BACKUP_DIR/backup.log"

# Security check
if [ -z "$DB_PASS" ]; then
    echo "ERROR: DB_PASS environment variable is not set!"
    echo "Please set the database password before running this script."
    exit 1
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handler
error_exit() {
    log "ERROR: $1"
    exit 1
}

# Start backup process
log "=== MySQL Backup Started ==="
log "Database: $DB_NAME@$DB_HOST"
log "Backup directory: $BACKUP_DIR"

# Create backup filename
if [ "$BACKUP_COMPRESS" = "true" ]; then
    BACKUP_FILE="$BACKUP_DIR/mysql_${DB_NAME}_${DATE}.sql.gz"
else
    BACKUP_FILE="$BACKUP_DIR/mysql_${DB_NAME}_${DATE}.sql"
fi

# Perform database backup
log "Creating database backup..."
if [ "$BACKUP_COMPRESS" = "true" ]; then
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-database \
        --databases "$DB_NAME" 2>/dev/null | gzip > "$BACKUP_FILE" || error_exit "Database backup failed!"
else
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --add-drop-database \
        --databases "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || error_exit "Database backup failed!"
fi

# Verify backup was created
if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log "✓ Backup created successfully: $(basename "$BACKUP_FILE") ($BACKUP_SIZE)"
else
    error_exit "Backup file was not created!"
fi

# Create a symlink to latest backup
ln -sf "$BACKUP_FILE" "$BACKUP_DIR/latest_backup.sql$([ "$BACKUP_COMPRESS" = "true" ] && echo ".gz")"

# Backup rotation - remove old backups
log "Rotating old backups (keeping last $BACKUP_RETENTION_DAYS days)..."
find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f -mtime +$BACKUP_RETENTION_DAYS -delete
REMAINING_BACKUPS=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f | wc -l)
log "✓ Rotation complete. $REMAINING_BACKUPS backups remaining."

# Upload to remote server if enabled
if [ "$REMOTE_BACKUP_ENABLED" = "true" ] && [ -n "$REMOTE_FTP_HOST" ]; then
    log "Uploading backup to remote server..."
    
    # Create Python script for FTPS upload
    cat > /tmp/upload_backup.py << 'EOF'
import sys
import os
import ftplib
import ssl

ftp_host = sys.argv[1]
ftp_user = sys.argv[2]
ftp_pass = sys.argv[3]
ftp_path = sys.argv[4]
local_file = sys.argv[5]

try:
    # Connect via FTPS
    context = ssl.create_default_context()
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
    
    ftp = ftplib.FTP_TLS(ftp_host, ftp_user, ftp_pass, context=context)
    ftp.prot_p()
    
    # Change to backup directory
    try:
        ftp.cwd(ftp_path)
    except:
        # Try to create directory if it doesn't exist
        ftp.mkd(ftp_path)
        ftp.cwd(ftp_path)
    
    # Upload file
    with open(local_file, 'rb') as f:
        ftp.storbinary(f'STOR {os.path.basename(local_file)}', f)
    
    ftp.quit()
    print("OK")
except Exception as e:
    print(f"ERROR: {e}")
    sys.exit(1)
EOF

    # Run upload script
    if python3 /tmp/upload_backup.py "$REMOTE_FTP_HOST" "$REMOTE_FTP_USER" "$REMOTE_FTP_PASS" "$REMOTE_FTP_PATH" "$BACKUP_FILE" 2>&1 | grep -q "OK"; then
        log "✓ Backup uploaded to remote server successfully"
    else
        log "⚠ Failed to upload backup to remote server (non-critical)"
    fi
    
    rm -f /tmp/upload_backup.py
fi

# Create backup summary
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
log ""
log "=== Backup Summary ==="
log "Backup file: $(basename "$BACKUP_FILE")"
log "Backup size: $BACKUP_SIZE"
log "Total backup directory size: $TOTAL_SIZE"
log "Backups retained: $REMAINING_BACKUPS"
log "=== Backup Completed Successfully ===

# Exit successfully
exit 0
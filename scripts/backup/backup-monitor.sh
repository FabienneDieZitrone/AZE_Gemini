#!/bin/bash
# Backup Monitoring Script for AZE Gemini
# Issue #113: Database Backup Automation
# 
# This script monitors backup health and sends alerts
# Features:
# - Check if backups are running on schedule
# - Verify backup integrity
# - Alert on failures or missing backups
# - Generate backup status report

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/aze-gemini/mysql}"
DB_NAME="${DB_NAME:-aze_zeiterfassung}"
MAX_AGE_HOURS="${BACKUP_MAX_AGE_HOURS:-25}" # Alert if no backup in 25 hours
MIN_SIZE_MB="${BACKUP_MIN_SIZE_MB:-1}" # Alert if backup smaller than 1MB
ALERT_EMAIL="${BACKUP_ALERT_EMAIL:-}"
LOG_FILE="$BACKUP_DIR/monitor.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Status tracking
STATUS="OK"
ALERTS=()

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Add alert
add_alert() {
    ALERTS+=("$1")
    STATUS="ALERT"
    log "ALERT: $1"
}

# Check latest backup age
check_backup_age() {
    echo -e "${BLUE}Checking backup age...${NC}"
    
    LATEST_BACKUP=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f 2>/dev/null | sort -r | head -1)
    
    if [ -z "$LATEST_BACKUP" ]; then
        add_alert "No backups found in $BACKUP_DIR"
        return
    fi
    
    # Get file age in hours
    CURRENT_TIME=$(date +%s)
    FILE_TIME=$(stat -c %Y "$LATEST_BACKUP" 2>/dev/null || stat -f %m "$LATEST_BACKUP" 2>/dev/null)
    AGE_HOURS=$(( (CURRENT_TIME - FILE_TIME) / 3600 ))
    
    if [ "$AGE_HOURS" -gt "$MAX_AGE_HOURS" ]; then
        add_alert "Latest backup is $AGE_HOURS hours old (max allowed: $MAX_AGE_HOURS hours)"
    else
        echo -e "${GREEN}✓ Latest backup age: $AGE_HOURS hours${NC}"
    fi
    
    echo "  File: $(basename "$LATEST_BACKUP")"
}

# Check backup size
check_backup_size() {
    echo -e "${BLUE}Checking backup sizes...${NC}"
    
    LATEST_BACKUP=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f 2>/dev/null | sort -r | head -1)
    
    if [ -n "$LATEST_BACKUP" ]; then
        SIZE_BYTES=$(stat -c %s "$LATEST_BACKUP" 2>/dev/null || stat -f %z "$LATEST_BACKUP" 2>/dev/null)
        SIZE_MB=$((SIZE_BYTES / 1024 / 1024))
        
        if [ "$SIZE_MB" -lt "$MIN_SIZE_MB" ]; then
            add_alert "Latest backup is too small: ${SIZE_MB}MB (minimum: ${MIN_SIZE_MB}MB)"
        else
            echo -e "${GREEN}✓ Latest backup size: ${SIZE_MB}MB${NC}"
        fi
    fi
}

# Check backup count and rotation
check_backup_rotation() {
    echo -e "${BLUE}Checking backup rotation...${NC}"
    
    BACKUP_COUNT=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f 2>/dev/null | wc -l)
    
    if [ "$BACKUP_COUNT" -eq 0 ]; then
        add_alert "No backups found"
    elif [ "$BACKUP_COUNT" -gt 30 ]; then
        add_alert "Too many backups ($BACKUP_COUNT) - rotation may not be working"
    else
        echo -e "${GREEN}✓ Backup count: $BACKUP_COUNT${NC}"
    fi
    
    # Check if old backups are being deleted
    OLD_BACKUPS=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f -mtime +7 2>/dev/null | wc -l)
    if [ "$OLD_BACKUPS" -gt 0 ]; then
        echo -e "${YELLOW}⚠ Found $OLD_BACKUPS backups older than 7 days${NC}"
    fi
}

# Check backup log for errors
check_backup_logs() {
    echo -e "${BLUE}Checking backup logs...${NC}"
    
    LOG_FILE="$BACKUP_DIR/backup.log"
    if [ -f "$LOG_FILE" ]; then
        # Check for recent errors
        RECENT_ERRORS=$(grep -i "error\|fail" "$LOG_FILE" 2>/dev/null | tail -5)
        if [ -n "$RECENT_ERRORS" ]; then
            add_alert "Found errors in backup log"
            echo -e "${RED}Recent errors:${NC}"
            echo "$RECENT_ERRORS"
        else
            echo -e "${GREEN}✓ No recent errors in backup log${NC}"
        fi
        
        # Check last backup status
        LAST_STATUS=$(grep "Backup Completed" "$LOG_FILE" | tail -1)
        if [ -n "$LAST_STATUS" ]; then
            echo "  Last successful: $LAST_STATUS"
        fi
    else
        echo -e "${YELLOW}⚠ No backup log found${NC}"
    fi
}

# Test database connectivity
check_database_connectivity() {
    echo -e "${BLUE}Checking database connectivity...${NC}"
    
    if [ -n "${DB_PASS:-}" ]; then
        if mysql -h "${DB_HOST:-localhost}" -u "${DB_USER:-root}" -p"$DB_PASS" -e "SELECT 1" &>/dev/null; then
            echo -e "${GREEN}✓ Database connection OK${NC}"
        else
            add_alert "Cannot connect to database"
        fi
    else
        echo -e "${YELLOW}⚠ DB_PASS not set, skipping connectivity check${NC}"
    fi
}

# Generate status report
generate_report() {
    echo ""
    echo -e "${BLUE}=== Backup Status Report ===${NC}"
    echo "Generated: $(date)"
    echo "Backup Directory: $BACKUP_DIR"
    echo ""
    
    # List recent backups
    echo "Recent Backups:"
    find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f -printf "%T+ %p\n" 2>/dev/null | sort -r | head -5 | while read -r line; do
        FILE=$(echo "$line" | cut -d' ' -f2-)
        SIZE=$(du -h "$FILE" | cut -f1)
        DATE=$(echo "$line" | cut -d' ' -f1 | cut -d'.' -f1)
        echo "  - $(basename "$FILE") ($SIZE) - $DATE"
    done
    
    echo ""
    
    # Disk usage
    if [ -d "$BACKUP_DIR" ]; then
        TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
        echo "Total backup size: $TOTAL_SIZE"
        
        # Check disk space
        DISK_USAGE=$(df -h "$BACKUP_DIR" | tail -1 | awk '{print $5}' | sed 's/%//')
        if [ "$DISK_USAGE" -gt 90 ]; then
            add_alert "Disk usage critical: ${DISK_USAGE}%"
        else
            echo "Disk usage: ${DISK_USAGE}%"
        fi
    fi
}

# Send alert if needed
send_alert() {
    if [ "$STATUS" = "ALERT" ] && [ -n "$ALERT_EMAIL" ]; then
        SUBJECT="[ALERT] AZE Gemini Backup Monitoring"
        BODY="Backup monitoring detected the following issues:\n\n"
        
        for alert in "${ALERTS[@]}"; do
            BODY="${BODY}- ${alert}\n"
        done
        
        BODY="${BODY}\n\nPlease check the backup system immediately."
        
        echo -e "$BODY" | mail -s "$SUBJECT" "$ALERT_EMAIL" 2>/dev/null || \
            log "Failed to send email alert to $ALERT_EMAIL"
    fi
}

# Main monitoring routine
main() {
    log "=== Starting Backup Monitoring ==="
    
    echo -e "${BLUE}AZE Gemini Backup Monitor${NC}"
    echo "==============================="
    echo ""
    
    # Run all checks
    check_backup_age
    echo ""
    
    check_backup_size
    echo ""
    
    check_backup_rotation
    echo ""
    
    check_backup_logs
    echo ""
    
    check_database_connectivity
    echo ""
    
    # Generate report
    generate_report
    
    # Final status
    echo ""
    if [ "$STATUS" = "OK" ]; then
        echo -e "${GREEN}=== Overall Status: OK ===${NC}"
        log "Monitoring completed: Status OK"
    else
        echo -e "${RED}=== Overall Status: ALERT ===${NC}"
        echo ""
        echo "Issues found:"
        for alert in "${ALERTS[@]}"; do
            echo -e "${RED}  • $alert${NC}"
        done
        log "Monitoring completed: ${#ALERTS[@]} alerts"
        
        # Send alert email
        send_alert
    fi
    
    echo ""
    
    # Exit with appropriate code
    [ "$STATUS" = "OK" ] && exit 0 || exit 1
}

# Run main function
main
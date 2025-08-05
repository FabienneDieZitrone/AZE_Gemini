#!/bin/bash
# MySQL Database Restore Script for AZE Gemini
# Issue #113: Database Backup Automation
# 
# This script restores MySQL backups created by mysql-backup.sh
# Features:
# - Interactive and non-interactive modes
# - List available backups
# - Restore specific backup or latest
# - Safety confirmations

set -euo pipefail

# Load configuration from environment
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-aze_zeiterfassung}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/aze-gemini/mysql}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Security check
if [ -z "$DB_PASS" ]; then
    echo -e "${RED}ERROR: DB_PASS environment variable is not set!${NC}"
    echo "Please set the database password before running this script."
    exit 1
fi

# Logging function
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Error handler
error_exit() {
    echo -e "${RED}ERROR: $1${NC}"
    exit 1
}

# List available backups
list_backups() {
    echo -e "${GREEN}Available backups in $BACKUP_DIR:${NC}"
    echo "----------------------------------------"
    
    if [ -d "$BACKUP_DIR" ]; then
        BACKUPS=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f | sort -r)
        if [ -z "$BACKUPS" ]; then
            echo "No backups found."
            return 1
        fi
        
        i=1
        for backup in $BACKUPS; do
            SIZE=$(du -h "$backup" | cut -f1)
            DATE=$(stat -c %y "$backup" | cut -d' ' -f1,2 | cut -d'.' -f1)
            echo "[$i] $(basename "$backup") - $SIZE - $DATE"
            ((i++))
        done
    else
        echo "Backup directory not found: $BACKUP_DIR"
        return 1
    fi
}

# Get backup file based on user input
get_backup_file() {
    local choice=$1
    
    if [ "$choice" = "latest" ]; then
        # Check for latest symlink first
        if [ -L "$BACKUP_DIR/latest_backup.sql" ] || [ -L "$BACKUP_DIR/latest_backup.sql.gz" ]; then
            BACKUP_FILE=$(readlink -f "$BACKUP_DIR/latest_backup.sql*" 2>/dev/null | head -1)
        else
            # Get most recent backup
            BACKUP_FILE=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f | sort -r | head -1)
        fi
    elif [[ "$choice" =~ ^[0-9]+$ ]]; then
        # Numeric choice from list
        BACKUP_FILE=$(find "$BACKUP_DIR" -name "mysql_${DB_NAME}_*.sql*" -type f | sort -r | sed -n "${choice}p")
    else
        # Direct filename
        if [ -f "$BACKUP_DIR/$choice" ]; then
            BACKUP_FILE="$BACKUP_DIR/$choice"
        elif [ -f "$choice" ]; then
            BACKUP_FILE="$choice"
        else
            error_exit "Backup file not found: $choice"
        fi
    fi
    
    if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
        error_exit "Invalid backup selection"
    fi
}

# Restore database from backup
restore_backup() {
    local backup_file=$1
    
    log "Preparing to restore from: $(basename "$backup_file")"
    
    # Check if backup is compressed
    if [[ "$backup_file" == *.gz ]]; then
        log "Decompressing backup..."
        gunzip -c "$backup_file" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" 2>/dev/null || error_exit "Restore failed!"
    else
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" < "$backup_file" 2>/dev/null || error_exit "Restore failed!"
    fi
    
    log "${GREEN}✓ Database restored successfully!${NC}"
}

# Show usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -l, --list         List available backups"
    echo "  -r, --restore FILE Restore specific backup file"
    echo "  -L, --latest       Restore latest backup"
    echo "  -y, --yes          Skip confirmation prompt"
    echo "  -h, --help         Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --list                    # List all backups"
    echo "  $0 --latest                  # Restore latest backup"
    echo "  $0 --restore backup.sql.gz   # Restore specific file"
    echo "  $0                           # Interactive mode"
}

# Main script logic
SKIP_CONFIRM=false
ACTION=""
RESTORE_FILE=""

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -l|--list)
            ACTION="list"
            shift
            ;;
        -r|--restore)
            ACTION="restore"
            RESTORE_FILE="$2"
            shift 2
            ;;
        -L|--latest)
            ACTION="restore"
            RESTORE_FILE="latest"
            shift
            ;;
        -y|--yes)
            SKIP_CONFIRM=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            error_exit "Unknown option: $1"
            ;;
    esac
done

# Execute based on action
if [ "$ACTION" = "list" ]; then
    list_backups
    exit 0
elif [ "$ACTION" = "restore" ]; then
    get_backup_file "$RESTORE_FILE"
    
    echo -e "${YELLOW}WARNING: This will restore the database from backup.${NC}"
    echo -e "${YELLOW}Current database contents will be OVERWRITTEN!${NC}"
    echo ""
    echo "Database: $DB_NAME@$DB_HOST"
    echo "Backup file: $(basename "$BACKUP_FILE")"
    echo ""
    
    if [ "$SKIP_CONFIRM" = false ]; then
        read -p "Are you sure you want to continue? (yes/no): " confirm
        if [ "$confirm" != "yes" ]; then
            echo "Restore cancelled."
            exit 0
        fi
    fi
    
    restore_backup "$BACKUP_FILE"
else
    # Interactive mode
    echo -e "${GREEN}=== MySQL Database Restore Tool ===${NC}"
    echo ""
    
    if ! list_backups; then
        exit 1
    fi
    
    echo ""
    echo "Enter backup number to restore, 'latest' for most recent, or 'q' to quit:"
    read -p "> " choice
    
    if [ "$choice" = "q" ] || [ "$choice" = "quit" ]; then
        echo "Restore cancelled."
        exit 0
    fi
    
    get_backup_file "$choice"
    
    echo ""
    echo -e "${YELLOW}WARNING: This will restore the database from backup.${NC}"
    echo -e "${YELLOW}Current database contents will be OVERWRITTEN!${NC}"
    echo ""
    echo "Database: $DB_NAME@$DB_HOST"
    echo "Backup file: $(basename "$BACKUP_FILE")"
    echo ""
    read -p "Are you sure you want to continue? (yes/no): " confirm
    
    if [ "$confirm" = "yes" ]; then
        restore_backup "$BACKUP_FILE"
    else
        echo "Restore cancelled."
    fi
fi

exit 0
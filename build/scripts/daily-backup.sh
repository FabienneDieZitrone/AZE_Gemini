#!/bin/bash
# Tägliches Backup-Script für AZE_Gemini
# Issue #113: Database Backup Automation

BACKUP_DIR="/backups/aze_gemini"
DB_NAME="aze_zeiterfassung"
DB_USER="root"
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$BACKUP_DIR/backup.log"

# Erstelle Backup-Verzeichnis falls nicht vorhanden
mkdir -p $BACKUP_DIR

# Logging-Funktion
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

log "=== Backup gestartet ==="

# 1. Datenbank-Backup
log "Erstelle Datenbank-Backup..."
mysqldump -u $DB_USER -p$DB_PASSWORD $DB_NAME > "$BACKUP_DIR/db_$DATE.sql"
if [ $? -eq 0 ]; then
    log "✓ Datenbank-Backup erfolgreich: db_$DATE.sql"
else
    log "✗ Datenbank-Backup fehlgeschlagen!"
    exit 1
fi

# 2. Anwendungsdateien-Backup
log "Erstelle Anwendungs-Backup..."
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" /app/AZE_Gemini/build --exclude='node_modules' --exclude='*.log'
if [ $? -eq 0 ]; then
    log "✓ Anwendungs-Backup erfolgreich: app_$DATE.tar.gz"
else
    log "✗ Anwendungs-Backup fehlgeschlagen!"
fi

# 3. Konfigurationsdateien-Backup
log "Erstelle Konfigurations-Backup..."
tar -czf "$BACKUP_DIR/config_$DATE.tar.gz" /app/AZE_Gemini/build/api/*.php /app/AZE_Gemini/build/.env* 2>/dev/null
if [ $? -eq 0 ]; then
    log "✓ Konfigurations-Backup erfolgreich: config_$DATE.tar.gz"
else
    log "✗ Konfigurations-Backup fehlgeschlagen!"
fi

# 4. Alte Backups löschen (älter als 30 Tage)
log "Lösche alte Backups..."
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete

# 5. Backup-Größen prüfen
log "Backup-Größen:"
du -sh $BACKUP_DIR/db_$DATE.sql | tee -a $LOG_FILE
du -sh $BACKUP_DIR/app_$DATE.tar.gz | tee -a $LOG_FILE
du -sh $BACKUP_DIR/config_$DATE.tar.gz | tee -a $LOG_FILE

# 6. Backup auf Remote-Server kopieren (optional)
# rsync -avz $BACKUP_DIR/db_$DATE.sql backup@remote-server:/backups/
# rsync -avz $BACKUP_DIR/app_$DATE.tar.gz backup@remote-server:/backups/

log "=== Backup abgeschlossen ===

# Sende Benachrichtigung (optional)
# echo "AZE_Gemini Backup erfolgreich: $DATE" | mail -s "Backup Success" admin@example.com
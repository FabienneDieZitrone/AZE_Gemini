#!/bin/bash
# Backup Script für stop_time Migration
# Erstellt ein Backup der time_entries Tabelle vor der Migration

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Backup vor stop_time Migration ===${NC}\n"

# Lade Konfiguration aus PHP
DB_HOST=$(php -r "require_once dirname(__DIR__) . '/config.php'; \$config = Config::load(); echo Config::get('db.host');")
DB_USER=$(php -r "require_once dirname(__DIR__) . '/config.php'; \$config = Config::load(); echo Config::get('db.username');")
DB_NAME=$(php -r "require_once dirname(__DIR__) . '/config.php'; \$config = Config::load(); echo Config::get('db.name');")

# Backup-Dateiname mit Timestamp
BACKUP_FILE="backup_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"

echo -e "${YELLOW}Datenbank:${NC} $DB_NAME"
echo -e "${YELLOW}Host:${NC} $DB_HOST"
echo -e "${YELLOW}Backup-Datei:${NC} $BACKUP_FILE\n"

# Passwort-Eingabe
echo -n "Bitte Datenbank-Passwort eingeben: "
read -s DB_PASS
echo

# Erstelle Backup
echo -e "\n${BLUE}Erstelle Backup...${NC}"

mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" time_entries > "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo -e "${GREEN}✓ Backup erfolgreich erstellt!${NC}"
    echo -e "${GREEN}  Datei: $BACKUP_FILE${NC}"
    echo -e "${GREEN}  Größe: $FILE_SIZE${NC}"
    
    # Zeige erste Zeilen zur Verifikation
    echo -e "\n${BLUE}Backup-Verifikation:${NC}"
    head -n 20 "$BACKUP_FILE" | grep -E "(CREATE TABLE|INSERT INTO)" | head -n 5
    
    echo -e "\n${YELLOW}Wichtig:${NC} Bewahren Sie dieses Backup sicher auf!"
    echo -e "${YELLOW}Restore-Befehl:${NC} mysql -h$DB_HOST -u$DB_USER -p $DB_NAME < $BACKUP_FILE"
else
    echo -e "${RED}✗ Backup fehlgeschlagen!${NC}"
    echo -e "${RED}Bitte prüfen Sie Ihre Zugangsdaten.${NC}"
    exit 1
fi

echo -e "\n${GREEN}Backup abgeschlossen. Sie können jetzt die Migration durchführen.${NC}\n"
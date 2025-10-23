#!/bin/bash
#
# Backup vor Onboarding-Feature-Implementierung
# Erstellt ein vollständiges Datenbank-Backup
#

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}=== Onboarding-Feature Backup ===${NC}\n"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="backup_onboarding_${TIMESTAMP}.sql"

echo -e "${YELLOW}Erstelle Backup vor Onboarding-Feature-Implementierung...${NC}"
echo -e "${YELLOW}Zeitstempel:${NC} $(date '+%Y-%m-%d %H:%M:%S')\n"

# Production URL
PROD_URL="${PROD_URL:-https://zeiterfassung.aze-gemini.de}"
BACKUP_API="${PROD_URL}/api/backup-database.php"

echo -e "${YELLOW}API Endpoint:${NC} $BACKUP_API\n"

# Versuche Backup via API
echo -e "${GREEN}Versuche automatisches Backup via API...${NC}\n"

if command -v curl &> /dev/null; then
    RESPONSE=$(curl -s "$BACKUP_API" 2>&1 || echo '{"success":false,"error":"API nicht erreichbar"}')

    # Prüfe ob Response JSON ist
    if echo "$RESPONSE" | jq . &>/dev/null 2>&1; then
        SUCCESS=$(echo "$RESPONSE" | jq -r '.success' 2>/dev/null || echo "false")

        if [ "$SUCCESS" = "true" ]; then
            echo -e "${GREEN}✓ Backup erfolgreich erstellt!${NC}\n"
            echo "$RESPONSE" | jq . 2>/dev/null || echo "$RESPONSE"

            # Speichere auch lokal zur Sicherheit
            echo "$RESPONSE" > "backup_info_${TIMESTAMP}.json"
            echo -e "\n${GREEN}Backup-Info gespeichert in: backup_info_${TIMESTAMP}.json${NC}"
        else
            echo -e "${RED}✗ API Backup fehlgeschlagen${NC}"
            ERROR=$(echo "$RESPONSE" | jq -r '.error // .message' 2>/dev/null || echo "Unbekannter Fehler")
            echo -e "${RED}Fehler: $ERROR${NC}\n"
        fi
    else
        echo -e "${RED}✗ API-Antwort ist kein valides JSON${NC}"
        echo -e "${YELLOW}Response:${NC} $RESPONSE\n"
    fi
else
    echo -e "${YELLOW}curl nicht verfügbar${NC}\n"
fi

# Alternative Backup-Optionen anzeigen
echo -e "\n${YELLOW}=== Alternative Backup-Methoden ===${NC}\n"

cat << 'EOF'
Falls das API-Backup nicht funktioniert hat, nutze bitte eine dieser Methoden:

1. Via SSH auf dem Server:
   ssh user@server "cd /pfad/zum/projekt && php api/backup-database.php > backup.json"

2. Via phpMyAdmin:
   - Einloggen in phpMyAdmin
   - Datenbank auswählen
   - "Exportieren" → SQL-Format → "OK"

3. Via MySQL direkt:
   mysqldump -h HOST -u USER -p DBNAME > backup_onboarding_TIMESTAMP.sql

4. Via FTP:
   - Einloggen via FTP
   - Backup-Datei aus /backups/ herunterladen
   - Prüfen dass die Datei aktuell ist (Zeitstempel)
EOF

echo -e "\n${GREEN}=== Wichtig ===${NC}"
echo -e "Stelle sicher, dass du ein Backup hast, bevor wir fortfahren!"
echo -e "Das Onboarding-Feature wird die Datenbank-Struktur ändern.\n"

# Warte auf Bestätigung
echo -e "${YELLOW}Drücke ENTER wenn das Backup erfolgreich war und wir fortfahren können...${NC}"
read -r

echo -e "${GREEN}✓ Backup bestätigt. Bereit für Implementierung!${NC}\n"

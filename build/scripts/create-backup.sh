#!/bin/bash
#
# Backup-Script für AZE Gemini Datenbank
# Erstellt ein SQL-Backup über das Backend-API
#

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}=== AZE Gemini Database Backup ===${NC}\n"

# Production URL (anpassen falls nötig)
PROD_URL="${PROD_URL:-https://zeiterfassung.aze-gemini.de}"
BACKUP_API="${PROD_URL}/api/backup-database.php"

echo -e "${YELLOW}API Endpoint:${NC} $BACKUP_API"
echo -e "${YELLOW}Zeitstempel:${NC} $(date '+%Y-%m-d %H:%M:%S')\n"

# Option 1: Via curl (wenn API öffentlich erreichbar)
echo -e "${GREEN}Option 1: Via API (curl)${NC}"
echo "curl -s \"$BACKUP_API\" | jq ."
echo

# Option 2: Via SSH auf Server
echo -e "${GREEN}Option 2: Via SSH auf Produktionsserver${NC}"
echo "ssh user@server \"cd /pfad/zum/projekt && php api/backup-database.php\""
echo

# Option 3: Via mysqldump direkt
echo -e "${GREEN}Option 3: Via mysqldump (direkte Datenbank-Verbindung)${NC}"
cat << 'EOF'
mysqldump \
  -h your-db-host \
  -u your-db-user \
  -p \
  your-db-name \
  > backup_$(date +%Y%m%d_%H%M%S).sql
EOF
echo

# Versuche Option 1 automatisch
echo -e "\n${YELLOW}Versuche automatisches Backup via API...${NC}\n"

if command -v curl &> /dev/null; then
    RESPONSE=$(curl -s -w "\n%{http_code}" "$BACKUP_API" 2>/dev/null || echo "000")
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)

    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓ Backup erfolgreich erstellt!${NC}\n"
        echo "$BODY" | jq . 2>/dev/null || echo "$BODY"
    else
        echo -e "${RED}✗ API nicht erreichbar (HTTP $HTTP_CODE)${NC}"
        echo -e "${YELLOW}Bitte verwenden Sie eine der oben genannten Optionen manuell.${NC}"
    fi
else
    echo -e "${YELLOW}curl nicht gefunden. Bitte installieren Sie curl oder verwenden Sie eine der oben genannten Optionen.${NC}"
fi

echo -e "\n${GREEN}Backup-Prozess abgeschlossen.${NC}"

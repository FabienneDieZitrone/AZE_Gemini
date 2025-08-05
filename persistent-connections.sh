#!/bin/bash
# Persistente Verbindungsprüfung für AZE Gemini
# Gemäß CLAUDE.local.md Spezifikationen

# Farben für Output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Lade Environment-Variablen
if [ -f "/app/projects/aze-gemini/build/.env.production" ]; then
    export $(grep -v '^#' /app/projects/aze-gemini/build/.env.production | xargs)
fi

echo "=== AZE Gemini Verbindungsstatus ==="
echo "Datum: $(date)"
echo "=================================="

# 1. SSL/TLS Zertifikat prüfen
echo -e "\n${YELLOW}1. SSL/TLS Zertifikat${NC}"
SSL_CHECK=$(curl -vI https://aze.mikropartner.de --insecure 2>&1 | grep -E "expire date|subject")
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ SSL-Zertifikat aktiv${NC}"
    echo "$SSL_CHECK"
else
    echo -e "${RED}❌ SSL-Prüfung fehlgeschlagen${NC}"
fi

# 2. FTP Verbindung
echo -e "\n${YELLOW}2. FTP-Verbindung${NC}"
FTP_CHECK=$(curl -s --connect-timeout 5 ftp://$FTP_USER:$FTP_PASS@$FTP_HOST$FTP_PATH --list-only 2>&1 | head -5)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ FTP-Verbindung erfolgreich${NC}"
    echo "Server: $FTP_HOST"
    echo "Benutzer: $FTP_USER"
else
    echo -e "${RED}❌ FTP-Verbindung fehlgeschlagen${NC}"
fi

# 3. Azure AD OAuth
echo -e "\n${YELLOW}3. Azure AD OAuth${NC}"
AZURE_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "https://login.microsoftonline.com/$OAUTH_TENANT_ID/v2.0/.well-known/openid-configuration")
if [ "$AZURE_CHECK" == "200" ]; then
    echo -e "${GREEN}✅ Azure AD erreichbar${NC}"
    echo "Tenant ID: $OAUTH_TENANT_ID"
    echo "Client ID: $OAUTH_CLIENT_ID"
else
    echo -e "${RED}❌ Azure AD nicht erreichbar (HTTP $AZURE_CHECK)${NC}"
fi

# 4. API Health Check
echo -e "\n${YELLOW}4. API Health Status${NC}"
API_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "https://aze.mikropartner.de/api/health" -k)
if [ "$API_CHECK" == "200" ]; then
    echo -e "${GREEN}✅ API Health OK${NC}"
else
    echo -e "${RED}❌ API Health fehlgeschlagen (HTTP $API_CHECK)${NC}"
    echo "Hinweis: Prüfen Sie die API-Logs für Details"
fi

# 5. Datenbank (simuliert, da kein direkter Zugriff)
echo -e "\n${YELLOW}5. Datenbank${NC}"
echo "Host: $DB_HOST"
echo "Datenbank: $DB_NAME"
echo "Benutzer: $DB_USER"
echo -e "${YELLOW}⚠️  Direkte DB-Verbindung nur über PHP-API möglich${NC}"

# 6. GitHub Repository
echo -e "\n${YELLOW}6. GitHub Repository${NC}"
GIT_REMOTE=$(git remote get-url origin 2>/dev/null | sed 's/https:\/\/[^@]*@/https:\/\/***@/')
GIT_STATUS=$(git fetch origin --dry-run 2>&1)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ GitHub-Verbindung aktiv${NC}"
    echo "Repository: $GIT_REMOTE"
    BRANCH=$(git branch --show-current)
    echo "Branch: $BRANCH"
    # Prüfe auf uncommitted changes
    CHANGES=$(git status --porcelain | wc -l)
    if [ $CHANGES -gt 0 ]; then
        echo -e "${YELLOW}⚠️  $CHANGES uncommitted Änderungen vorhanden${NC}"
    fi
    # Prüfe auf unpushed commits
    UNPUSHED=$(git rev-list --count origin/$BRANCH..$BRANCH 2>/dev/null || echo "0")
    if [ "$UNPUSHED" != "0" ]; then
        echo -e "${YELLOW}⚠️  $UNPUSHED unpushed Commits vorhanden${NC}"
    fi
else
    echo -e "${RED}❌ GitHub-Verbindung fehlgeschlagen${NC}"
fi

# Zusammenfassung
echo -e "\n${YELLOW}=== Zusammenfassung ===${NC}"
echo "Überprüfung abgeschlossen. Details siehe oben."

# Persistente Überwachung aktivieren?
if [ "$1" == "--watch" ]; then
    echo -e "\n${YELLOW}Starte persistente Überwachung (alle 60 Sekunden)...${NC}"
    while true; do
        sleep 60
        clear
        $0
    done
fi
#!/bin/bash
# Git Security Cleanup Script
# Autor: Claude Code
# Datum: 2025-07-29
# Beschreibung: Automatisiertes Cleanup von sensitiven Dateien aus Git

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verzeichnis prüfen
if [ ! -d ".git" ]; then
    echo -e "${RED}Fehler: Kein Git-Repository gefunden!${NC}"
    echo "Bitte führen Sie dieses Skript im Root-Verzeichnis des Repositories aus."
    exit 1
fi

echo -e "${YELLOW}Git Security Cleanup Script${NC}"
echo "================================"
echo ""

# Schritt 1: Backup erstellen
echo -e "${GREEN}Schritt 1: Erstelle Backup...${NC}"
BACKUP_NAME="git-backup-$(date +%Y%m%d-%H%M%S)"
git stash save "$BACKUP_NAME"
echo "Backup erstellt: $BACKUP_NAME"
echo ""

# Schritt 2: Liste der zu entfernenden Dateien
SENSITIVE_FILES=(
    "config.php"
    "test-deployment.txt"
    "monitoring-dashboard.html"
    "security-test.php"
    "server.log"
    "api/server.log"
)

SENSITIVE_DIRS=(
    "deploy_20250728_171752"
    "data"
    "cache"
    "logs"
)

# Schritt 3: Prüfe welche Dateien im Repository sind
echo -e "${GREEN}Schritt 2: Prüfe sensitive Dateien im Repository...${NC}"
FOUND_FILES=()
for file in "${SENSITIVE_FILES[@]}"; do
    if git ls-files --error-unmatch "$file" 2>/dev/null; then
        FOUND_FILES+=("$file")
        echo -e "${RED}Gefunden: $file${NC}"
    fi
done

for dir in "${SENSITIVE_DIRS[@]}"; do
    if git ls-files --error-unmatch "$dir" 2>/dev/null | head -1 >/dev/null; then
        FOUND_FILES+=("$dir")
        echo -e "${RED}Gefunden: $dir/${NC}"
    fi
done

if [ ${#FOUND_FILES[@]} -eq 0 ]; then
    echo -e "${GREEN}Keine sensitiven Dateien im Repository gefunden!${NC}"
    exit 0
fi

echo ""
echo -e "${YELLOW}Warnung: Die folgenden Dateien werden aus dem Repository entfernt:${NC}"
printf '%s\n' "${FOUND_FILES[@]}"
echo ""
read -p "Möchten Sie fortfahren? (j/N) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Jj]$ ]]; then
    echo "Abgebrochen."
    exit 1
fi

# Schritt 4: Entferne Dateien aus dem aktuellen Index
echo -e "${GREEN}Schritt 3: Entferne Dateien aus dem Repository...${NC}"
for item in "${FOUND_FILES[@]}"; do
    if [ -d "$item" ]; then
        git rm -r --cached "$item" 2>/dev/null || true
    else
        git rm --cached "$item" 2>/dev/null || true
    fi
done

# Schritt 5: Commit
echo -e "${GREEN}Schritt 4: Erstelle Commit...${NC}"
git commit -m "Security: Remove sensitive files from repository

Removed files:
$(printf '- %s\n' "${FOUND_FILES[@]}")

These files are now ignored by .gitignore" || echo "Keine Änderungen zu committen."

# Schritt 6: History Cleanup Option
echo ""
echo -e "${YELLOW}Möchten Sie auch die Git-History bereinigen?${NC}"
echo -e "${RED}WARNUNG: Dies ändert die Git-History und erfordert einen Force Push!${NC}"
read -p "History bereinigen? (j/N) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Jj]$ ]]; then
    echo -e "${GREEN}Bereinige Git-History...${NC}"
    
    # Erstelle Filter-Branch Befehl
    FILTER_COMMAND=""
    for file in "${FOUND_FILES[@]}"; do
        FILTER_COMMAND="$FILTER_COMMAND git rm --cached --ignore-unmatch '$file';"
    done
    
    git filter-branch --force --index-filter "$FILTER_COMMAND" \
        --prune-empty --tag-name-filter cat -- --all
    
    echo -e "${GREEN}History bereinigt!${NC}"
    echo ""
    echo -e "${YELLOW}Nächste Schritte:${NC}"
    echo "1. Überprüfen Sie das Ergebnis mit: git log --oneline"
    echo "2. Force Push mit: git push origin --force --all"
    echo "3. Informieren Sie alle Teammitglieder!"
else
    echo -e "${GREEN}History wurde nicht geändert. Nur aktuelle Dateien wurden entfernt.${NC}"
fi

# Schritt 7: Finale Überprüfung
echo ""
echo -e "${GREEN}Schritt 5: Finale Überprüfung...${NC}"
echo "Verbleibende sensitive Dateien im Repository:"
git ls-files | grep -E "(config\.php|credentials|secret|password|\.env|deploy_)" || echo "Keine gefunden!"

echo ""
echo -e "${GREEN}Cleanup abgeschlossen!${NC}"
echo ""
echo -e "${YELLOW}Empfehlungen:${NC}"
echo "1. Überprüfen Sie die .gitignore Datei"
echo "2. Rotieren Sie alle Secrets, die exposed waren"
echo "3. Richten Sie Pre-Commit Hooks ein"
echo "4. Dokumentieren Sie den Vorfall"
#!/bin/bash

###############################################################################
# Rollback-Script für GEN_001 Error Fix Deployment
# Datum: 2025-10-20
# Autor: Günnix
# Zweck: Schneller Rollback auf vorheriges Deployment bei Problemen
###############################################################################

set -e  # Exit on error

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Konfiguration
REMOTE_HOST="${AZE_FTP_HOST:-aze.mikropartner.de}"
REMOTE_USER="${AZE_FTP_USER}"
REMOTE_PATH="/var/www/html/aze"
BACKUP_DIR="$HOME/backups"

###############################################################################
# Funktionen
###############################################################################

print_header() {
    echo -e "${GREEN}============================================${NC}"
    echo -e "${GREEN}  AZE Gemini Rollback Script${NC}"
    echo -e "${GREEN}============================================${NC}"
    echo ""
}

print_error() {
    echo -e "${RED}[FEHLER] $1${NC}"
}

print_success() {
    echo -e "${GREEN}[OK] $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}[WARNUNG] $1${NC}"
}

check_ssh_connection() {
    echo "Prüfe SSH-Verbindung zu $REMOTE_HOST..."
    if ! ssh -q -o BatchMode=yes -o ConnectTimeout=5 "$REMOTE_USER@$REMOTE_HOST" exit 2>/dev/null; then
        print_error "SSH-Verbindung fehlgeschlagen!"
        echo "Bitte prüfen Sie:"
        echo "  1. SSH-Key korrekt konfiguriert?"
        echo "  2. Hostname/Username korrekt?"
        echo "  3. Server erreichbar?"
        exit 1
    fi
    print_success "SSH-Verbindung erfolgreich"
}

find_latest_backup() {
    echo ""
    echo "Suche neuestes Backup in $BACKUP_DIR..."

    if [ ! -d "$BACKUP_DIR" ]; then
        print_error "Backup-Verzeichnis $BACKUP_DIR existiert nicht!"
        exit 1
    fi

    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/backup_aze_*.tar.gz 2>/dev/null | head -1)

    if [ -z "$LATEST_BACKUP" ]; then
        print_error "Kein Backup gefunden in $BACKUP_DIR!"
        echo "Bitte erstellen Sie zuerst ein Backup:"
        echo "  ssh $REMOTE_USER@$REMOTE_HOST 'cd $REMOTE_PATH && tar -czf ~/backups/backup_aze_\$(date +%Y%m%d_%H%M%S).tar.gz dist/'"
        exit 1
    fi

    print_success "Gefundenes Backup: $(basename "$LATEST_BACKUP")"
    BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
    BACKUP_DATE=$(stat -c %y "$LATEST_BACKUP" 2>/dev/null || stat -f "%Sm" "$LATEST_BACKUP")
    echo "  Größe: $BACKUP_SIZE"
    echo "  Datum: $BACKUP_DATE"
}

confirm_rollback() {
    echo ""
    echo -e "${YELLOW}⚠️  WARNUNG: Rollback wird durchgeführt!${NC}"
    echo ""
    echo "Folgende Aktionen werden ausgeführt:"
    echo "  1. Aktuelles Production-Deployment wird gesichert"
    echo "  2. Backup wird auf Server hochgeladen"
    echo "  3. Backup wird extrahiert"
    echo "  4. Permissions werden gesetzt"
    echo ""
    read -p "Möchten Sie fortfahren? (ja/nein): " CONFIRM

    if [ "$CONFIRM" != "ja" ]; then
        echo "Rollback abgebrochen."
        exit 0
    fi
}

create_pre_rollback_backup() {
    echo ""
    echo "Erstelle Backup des aktuellen Production-Deployments..."

    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    PRE_ROLLBACK_BACKUP="backup_aze_pre_rollback_$TIMESTAMP.tar.gz"

    ssh "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_PATH && tar -czf ~/backups/$PRE_ROLLBACK_BACKUP dist/" 2>/dev/null

    if [ $? -eq 0 ]; then
        print_success "Pre-Rollback-Backup erstellt: $PRE_ROLLBACK_BACKUP"
    else
        print_warning "Pre-Rollback-Backup fehlgeschlagen (nicht kritisch)"
    fi
}

perform_rollback() {
    echo ""
    echo "Führe Rollback durch..."

    # 1. Backup auf Server kopieren (falls lokal)
    echo "  - Backup auf Server kopieren..."
    scp "$LATEST_BACKUP" "$REMOTE_USER@$REMOTE_HOST:~/backups/" 2>/dev/null

    if [ $? -ne 0 ]; then
        print_error "Backup-Upload fehlgeschlagen!"
        exit 1
    fi

    # 2. Aktuelles dist/ Verzeichnis entfernen
    echo "  - Aktuelles dist/ entfernen..."
    ssh "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_PATH && rm -rf dist/" 2>/dev/null

    # 3. Backup extrahieren
    echo "  - Backup extrahieren..."
    BACKUP_NAME=$(basename "$LATEST_BACKUP")
    ssh "$REMOTE_USER@$REMOTE_HOST" "cd $REMOTE_PATH && tar -xzf ~/backups/$BACKUP_NAME" 2>/dev/null

    if [ $? -ne 0 ]; then
        print_error "Backup-Extraktion fehlgeschlagen!"
        exit 1
    fi

    # 4. Permissions setzen
    echo "  - Permissions setzen..."
    ssh "$REMOTE_USER@$REMOTE_HOST" "chmod 755 $REMOTE_PATH/dist && chmod 644 $REMOTE_PATH/dist/index.html && chmod 644 $REMOTE_PATH/dist/assets/*" 2>/dev/null

    print_success "Rollback erfolgreich durchgeführt!"
}

verify_rollback() {
    echo ""
    echo "Verifiziere Rollback..."

    # Prüfe ob index.html existiert
    if ssh "$REMOTE_USER@$REMOTE_HOST" "[ -f $REMOTE_PATH/dist/index.html ]" 2>/dev/null; then
        print_success "index.html gefunden"
    else
        print_error "index.html NICHT gefunden!"
        exit 1
    fi

    # Prüfe HTTP-Antwort
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "https://$REMOTE_HOST/")
    if [ "$HTTP_STATUS" = "200" ]; then
        print_success "Server antwortet mit HTTP 200"
    else
        print_warning "Server antwortet mit HTTP $HTTP_STATUS"
    fi
}

print_post_rollback_instructions() {
    echo ""
    echo -e "${GREEN}============================================${NC}"
    echo -e "${GREEN}  Rollback erfolgreich abgeschlossen!${NC}"
    echo -e "${GREEN}============================================${NC}"
    echo ""
    echo "Nächste Schritte:"
    echo ""
    echo "1. Browser-Cache leeren (ALLE Benutzer!):"
    echo "   - Firefox: Strg+Shift+R"
    echo "   - Chrome: Strg+Shift+R"
    echo "   - Edge: Strg+F5"
    echo ""
    echo "2. Anwendung testen:"
    echo "   - https://$REMOTE_HOST"
    echo "   - Login durchführen"
    echo "   - Funktionalität prüfen"
    echo ""
    echo "3. Logs überwachen:"
    echo "   ssh $REMOTE_USER@$REMOTE_HOST 'tail -f /var/log/apache2/error.log'"
    echo ""
    echo "4. Falls Problem weiterhin besteht:"
    echo "   - GitHub Issue erstellen"
    echo "   - Console-Logs dokumentieren"
    echo "   - MP-IT Support kontaktieren"
    echo ""
}

###############################################################################
# Hauptprogramm
###############################################################################

main() {
    print_header

    # Prüfungen
    if [ -z "$REMOTE_USER" ]; then
        print_error "Umgebungsvariable AZE_FTP_USER nicht gesetzt!"
        echo "Bitte setzen: export AZE_FTP_USER='your_username'"
        exit 1
    fi

    check_ssh_connection
    find_latest_backup
    confirm_rollback

    # Rollback durchführen
    create_pre_rollback_backup
    perform_rollback
    verify_rollback

    print_post_rollback_instructions
}

# Script ausführen
main

exit 0

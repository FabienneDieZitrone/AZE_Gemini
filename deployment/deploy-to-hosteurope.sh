#!/bin/bash
# Deployment-Script für HostEurope
# Automatisches Upload der Web-App via SFTP

set -e  # Exit on error

# Konfiguration (Korrigiert basierend auf HostEurope-Recherche)
SFTP_HOST="ssh.server-he.de"
SFTP_USER="wp10454681"
LOCAL_BUILD_DIR="/app/build"
REMOTE_WEB_DIR="/htdocs"  # Typisch für HostEurope

echo "=== AZE_Gemini Deployment zu HostEurope ==="
echo "Ziel: $SFTP_USER@$SFTP_HOST:$REMOTE_WEB_DIR"
echo

# Prüfe ob Build-Verzeichnis existiert
if [ ! -d "$LOCAL_BUILD_DIR" ]; then
    echo "❌ Build-Verzeichnis nicht gefunden: $LOCAL_BUILD_DIR"
    exit 1
fi

# Web-App bauen
echo "📦 Baue React-Anwendung..."
cd $LOCAL_BUILD_DIR

if [ -f "package.json" ]; then
    echo "Installing dependencies..."
    npm install
    
    echo "Building production version..."
    npm run build
    
    if [ ! -d "dist" ]; then
        echo "❌ Build fehlgeschlagen - dist/ Ordner nicht gefunden"
        exit 1
    fi
    
    echo "✅ React-Build erfolgreich"
else
    echo "⚠️  Kein package.json gefunden - überspringe npm build"
fi

# SFTP-Upload vorbereiten
echo
echo "🚀 Starte SFTP-Upload..."

# SFTP-Batch-Commands erstellen
cat > /tmp/sftp_commands.txt << EOF
# Wechsle in Web-Verzeichnis
cd $REMOTE_WEB_DIR

# Erstelle Backup-Verzeichnis (falls nicht existiert)
-mkdir backup_$(date +%Y%m%d_%H%M%S)

# Upload React Build (falls dist/ existiert)
-lcd $LOCAL_BUILD_DIR/dist
-mput -r *

# Zurück zum Build-Verzeichnis für PHP Files
lcd $LOCAL_BUILD_DIR

# Upload PHP API Files
-mkdir api
cd api
lcd api
mput *.php

# Upload Konfigurationsdateien (ohne .env!)
cd ..
put config.php
put .env.example
put schema.sql
put README.md

# Upload weitere wichtige Dateien
-put package.json
-put vite.config.ts
-put tsconfig.json

quit
EOF

echo "SFTP-Commands vorbereitet. Starte Upload..."
echo "⚠️  Sie müssen das SFTP-Passwort eingeben!"
echo

# SFTP-Upload durchführen
if sftp -b /tmp/sftp_commands.txt $SFTP_USER@$SFTP_HOST; then
    echo "✅ Upload erfolgreich abgeschlossen!"
    echo
    echo "🌐 Web-App sollte jetzt erreichbar sein unter:"
    echo "   https://wp10454681.server-he.de/"
    echo
    echo "📋 Nächste Schritte:"
    echo "   1. .env Datei auf Server erstellen (mit Produktions-DB-Daten)"
    echo "   2. MySQL-Datenbank schema.sql importieren"
    echo "   3. PHP-Konfiguration prüfen (mysqli Extension)"
    echo "   4. Azure AD OAuth2 Redirect-URLs aktualisieren"
else
    echo "❌ Upload fehlgeschlagen!"
    exit 1
fi

# Cleanup
rm -f /tmp/sftp_commands.txt

echo
echo "=== Deployment abgeschlossen ==="
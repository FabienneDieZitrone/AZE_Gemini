#!/bin/bash
# Live-Server Backup Skript
# Erstellt ein vollständiges Backup des aktuellen Live-Servers

set -e

BACKUP_DIR="/app/build/backups/live-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "🔄 LIVE-SERVER BACKUP"
echo "====================="
echo "Backup-Verzeichnis: $BACKUP_DIR"
echo ""

# Lade Environment-Variablen
if [ ! -f ".env.production" ]; then
    echo "❌ Fehler: .env.production nicht gefunden!"
    exit 1
fi

source .env.production

echo "📦 Downloading Live-Server Dateien..."
echo ""

# Funktion zum Download einzelner Dateien
download_file() {
    local remote_path="$1"
    local local_path="$2"

    mkdir -p "$(dirname "$local_path")"
    curl -sk --ftp-ssl --user "${FTP_USER}:${FTP_PASS}" \
        "ftp://${FTP_HOST}:21/${remote_path}" \
        -o "$local_path" 2>/dev/null || echo "  ⚠️  Fehler bei: $remote_path"
}

# Download aller wichtigen Dateien
echo "📄 Downloading HTML/PHP files..."
download_file "index.html" "$BACKUP_DIR/index.html"
download_file "index.php" "$BACKUP_DIR/index.php"
download_file ".htaccess" "$BACKUP_DIR/.htaccess"

echo "📄 Downloading Favicons..."
download_file "favicon.svg" "$BACKUP_DIR/favicon.svg"
download_file "favicon.png" "$BACKUP_DIR/favicon.png"

echo "📄 Downloading CSS files..."
mkdir -p "$BACKUP_DIR/assets"
for css in index-*.css; do
    if [ -f "dist/assets/$css" ]; then
        download_file "assets/$css" "$BACKUP_DIR/assets/$css"
    fi
done

echo "📄 Downloading JS files..."
for js in index-*.js purify.es-*.js html2canvas.esm-*.js index.es-*.js; do
    if [ -f "dist/assets/$js" ]; then
        download_file "assets/$js" "$BACKUP_DIR/assets/$js"
    fi
done

echo "📄 Downloading Backend API files..."
mkdir -p "$BACKUP_DIR/api"
for api_file in approvals.php time-entries.php users.php health.php; do
    download_file "api/$api_file" "$BACKUP_DIR/api/$api_file"
done

echo ""
echo "✅ Live-Backup erstellt!"
echo "📂 Gespeichert in: $BACKUP_DIR"
echo ""

# Zeige Backup-Größe
if [ -d "$BACKUP_DIR" ]; then
    BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
    echo "💾 Backup-Größe: $BACKUP_SIZE"
fi

echo ""
echo "📋 Backup-Inhalt:"
ls -lah "$BACKUP_DIR" 2>/dev/null || echo "Verzeichnis leer"
echo ""

#!/bin/bash
# Deployment-Skript für Host-System
# Führe dieses Skript auf dem HOST aus, nicht im Container!

set -e

echo "🚀 AZE Deployment - Frontend & Backend"
echo "========================================"
echo ""

# Prüfe ob .env.production existiert
if [ ! -f ".env.production" ]; then
    echo "❌ Fehler: .env.production nicht gefunden!"
    exit 1
fi

# Lade Environment-Variablen
source .env.production

echo "📦 Deploying Frontend (dist/)..."

# Deploy index.html
curl -k -T "dist/index.html" "ftp://${FTP_HOST}:21/index.html" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
echo "✅ index.html deployed"

# Deploy CSS
for css_file in dist/assets/index-*.css; do
    filename=$(basename "$css_file")
    curl -k -T "$css_file" "ftp://${FTP_HOST}:21/assets/${filename}" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
    echo "✅ $filename deployed"
done

# Deploy JavaScript
for js_file in dist/assets/index-*.js; do
    filename=$(basename "$js_file")
    curl -k -T "$js_file" "ftp://${FTP_HOST}:21/assets/${filename}" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
    echo "✅ $filename deployed"
done

# Deploy Favicons
curl -k -T "dist/favicon.svg" "ftp://${FTP_HOST}:21/favicon.svg" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
echo "✅ favicon.svg deployed"

curl -k -T "dist/favicon.png" "ftp://${FTP_HOST}:21/favicon.png" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
echo "✅ favicon.png deployed"

echo ""
echo "📦 Deploying Backend (api/approvals.php)..."

# Deploy Backend-Fix für stop_time Validierung
curl -k -T "api/approvals.php" "ftp://${FTP_HOST}:21/api/approvals.php" --user "${FTP_USER}:${FTP_PASS}" --ftp-ssl 2>&1 | grep -v "^  %"
echo "✅ Backend deployed"

echo ""
echo "🧹 Clearing OPcache..."

# Clear OPcache
curl -s "https://aze.mikropartner.de/api/clear-opcache.php" > /dev/null
echo "✅ OPcache cleared"

echo ""
echo "✅ Deployment abgeschlossen!"
echo ""
echo "📊 Verifikation:"
echo "   Frontend: https://aze.mikropartner.de"
echo "   Backend:  https://aze.mikropartner.de/api/health.php"
echo ""

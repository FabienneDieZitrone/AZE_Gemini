#!/bin/bash
# Test ob auth-callback.php auf dem FTP-Server existiert

source .env.production

echo "Prüfe ob /api/auth-callback.php existiert..."

curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/api/auth-callback.php" -I 2>&1 | grep -i "213\|250\|size"

echo ""
echo "Liste /api/ Verzeichnis:"
curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/api/" --list-only 2>&1 | grep -E "(auth-|login|callback)"

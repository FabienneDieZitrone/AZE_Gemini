#!/bin/bash
# Test FTP-Verbindung zum Produktivserver

echo "=== Teste FTP-Verbindung ==="

FTP_HOST="wp10454681.server-he.de"
FTP_USER="ftp10454681-aze"
FTP_PASS="321Start321"
FTP_PATH="/www/aze/"

echo "Host: $FTP_HOST"
echo "User: $FTP_USER"
echo "Path: $FTP_PATH"

# Teste mit curl (FTP über curl)
echo -e "\nTeste FTP-Verbindung mit curl..."
curl -v --ftp-ssl ftp://$FTP_USER:$FTP_PASS@$FTP_HOST$FTP_PATH --list-only 2>&1 | head -20

# Alternative: Teste als HTTPS
echo -e "\nTeste HTTPS-Verbindung zu aze.mikropartner.de..."
curl -I https://aze.mikropartner.de/api/health -k 2>&1 | head -10
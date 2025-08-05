#!/bin/bash
# Test FTP-Verbindung zum Produktivserver

echo "=== Teste FTP-Verbindung ==="

# Load FTP configuration from environment variables
FTP_HOST="${FTP_HOST:-wp10454681.server-he.de}"
FTP_USER="${FTP_USER:-ftp10454681-aze}"
FTP_PASS="${FTP_PASS}"
FTP_PATH="${FTP_PATH:-/www/aze/}"

# Security check: Ensure password is not hardcoded
if [ -z "$FTP_PASS" ]; then
    echo "ERROR: FTP_PASS environment variable is not set!"
    echo "Please set the FTP_PASS environment variable before running this script."
    echo "Example: export FTP_PASS='your-password-here'"
    exit 1
fi

echo "Host: $FTP_HOST"
echo "User: $FTP_USER"
echo "Path: $FTP_PATH"

# Teste mit curl (FTP über curl)
echo -e "\nTeste FTP-Verbindung mit curl..."
curl -v --ftp-ssl ftp://$FTP_USER:$FTP_PASS@$FTP_HOST$FTP_PATH --list-only 2>&1 | head -20

# Alternative: Teste als HTTPS
echo -e "\nTeste HTTPS-Verbindung zu aze.mikropartner.de..."
curl -I https://aze.mikropartner.de/api/health -k 2>&1 | head -10
#!/bin/bash
# Test FTP connection

cd "$(dirname "$0")"
export $(grep -v '^#' .env.production | xargs)

echo "Testing FTP connection..."
echo "Host: $FTP_HOST"
echo "User: $FTP_USER"
echo "Path: $FTP_PATH"

# Test with curl
echo -e "\nTesting with curl..."
curl -v --ftp-ssl --user "${FTP_USER}:${FTP_PASS}" "ftp://${FTP_HOST}/" 2>&1 | grep -E "230|530|Connected"

# Test without SSL
echo -e "\nTesting without SSL..."
curl -v --user "${FTP_USER}:${FTP_PASS}" "ftp://${FTP_HOST}/" 2>&1 | grep -E "230|530|Connected"

# Test with lftp
echo -e "\nTesting with lftp..."
lftp -c "set ftp:ssl-allow no; open ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}; ls; quit" 2>&1 | head -10
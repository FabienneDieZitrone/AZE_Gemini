#!/bin/bash
# Check which files are actually on the server

source .env.production

echo "=== SERVER FILE CHECK ==="
echo ""
echo "Checking index.html on server..."
lftp -u "$FTP_USER:$FTP_PASS" "ftps://$FTP_HOST" <<EOF
set ssl:verify-certificate no
cd $FTP_BASE_PATH
ls -la dist/index.html
cat dist/index.html
ls -la dist/assets/
bye
EOF

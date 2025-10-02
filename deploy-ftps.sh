#!/bin/bash
# FTPS Deployment Script for AZE Gemini

echo "=== AZE Gemini FTPS Deployment Script ==="
echo "This script prepares the deployment for FTPS upload"

# Configuration from .env.deployment
FTPS_HOST="wp10454681.server-he.de"
FTPS_USER="ftp10454681-aze3"
REMOTE_PATH="${FTP_BASE_DIR:-/www/it/aze}"
ARCHIVE="aze-deployment.tar.gz"

# Check if archive exists
if [ ! -f "$ARCHIVE" ]; then
    echo "Error: Deployment archive not found. Creating it now..."
    cd build
    tar -czf ../$ARCHIVE .
    cd ..
fi

echo ""
echo "Deployment archive ready: $ARCHIVE"
echo "Size: $(ls -lh $ARCHIVE | awk '{print $5}')"
echo ""
echo "=== FTPS Upload Instructions ==="
echo ""
echo "Since automated FTPS requires credentials, please use one of these methods:"
echo ""
echo "Method 1 - Using curl with FTPS:"
echo "curl --ftp-ssl -T $ARCHIVE -u $FTPS_USER ftp://$FTPS_HOST/tmp/"
echo ""
echo "Method 2 - Using lftp:"
echo "lftp -u $FTPS_USER ftps://$FTPS_HOST -e \"put $ARCHIVE -o /tmp/; bye\""
echo ""
echo "Method 3 - Using FileZilla or similar FTP client:"
echo "1. Connect to: $FTPS_HOST"
echo "2. Username: $FTPS_USER"
echo "3. Use explicit FTPS (port 21)"
echo "4. Upload $ARCHIVE to /tmp/"
echo ""
echo "=== After Upload ==="
echo "SSH into the server and run:"
echo "cd $REMOTE_PATH"
echo "tar -xzf /tmp/$ARCHIVE"
echo "rm /tmp/$ARCHIVE"
echo ""
echo "Then verify deployment:"
echo "curl -s https://aze.mikropartner.de/api/health.php | jq ."

#!/bin/bash

# AZE Gemini - Test Deployment Script
# Datum: 05.08.2025
# Beschreibung: Deployment der Sicherheits-Updates in die Testumgebung

echo "🚀 Starting test deployment for AZE Gemini security updates..."

# Konfiguration aus .env.deployment laden
source .env.deployment

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test-Verzeichnis auf dem Server (anpassen!)
TEST_DIR="test"  # Subdirectory für Testumgebung

echo -e "${YELLOW}Deploying to test environment: $FTP_HOST/$TEST_DIR${NC}"

# Erstelle temporäres Verzeichnis für Deployment
TEMP_DIR=$(mktemp -d)
echo "Created temp directory: $TEMP_DIR"

# Kopiere nur die aktualisierten API-Dateien
echo "Copying updated API files..."
mkdir -p $TEMP_DIR/api
cp -r api/*.php $TEMP_DIR/api/

# Spezielle Behandlung für neue auth-middleware.php
cp api/auth-middleware.php $TEMP_DIR/api/

# Erstelle .env.example für Testumgebung
cp .env.example $TEMP_DIR/

# Erstelle Deployment-Info
cat > $TEMP_DIR/DEPLOYMENT_INFO.txt << EOF
AZE Gemini Security Update - Test Deployment
Date: $(date)
Version: Security Patch 1.0
Changes:
- New auth-middleware.php for RBAC
- Updated all API endpoints with authorize_request()
- Fixed authorization vulnerabilities
EOF

# FTP Upload mit lftp
echo -e "${YELLOW}Uploading to test environment...${NC}"

lftp -c "
set ssl:verify-certificate no
open ftp://$FTP_USER:$FTP_PASS@$FTP_HOST
mirror -R --only-newer --verbose $TEMP_DIR/api $TEST_DIR/api
put $TEMP_DIR/.env.example -o $TEST_DIR/.env.example
put $TEMP_DIR/DEPLOYMENT_INFO.txt -o $TEST_DIR/DEPLOYMENT_INFO.txt
bye
"

# Cleanup
rm -rf $TEMP_DIR

echo -e "${GREEN}✅ Test deployment completed!${NC}"
echo ""
echo "Next steps:"
echo "1. Access test environment at: https://aze.mikropartner.de/$TEST_DIR/"
echo "2. Test all API endpoints with different user roles"
echo "3. Verify authorization is working correctly"
echo "4. Check error logs for any issues"
echo ""
echo -e "${YELLOW}⚠️  Remember to configure .env in test environment!${NC}"
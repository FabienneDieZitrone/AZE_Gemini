#!/bin/bash
#
# Deploy PHP Backend Features to Production
# 
# This script deploys only the PHP backend improvements
# without requiring a frontend build
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}PHP Backend Deployment Script${NC}"
echo "=============================="
echo

# List of PHP files to deploy
PHP_FILES=(
    "api/error-handler.php"
    "api/structured-logger.php"
    "api/security-headers.php"
    "api/health.php"
    "api/login.php"
    "api/validation.php"
)

echo "Files to deploy:"
for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "  ${GREEN}✓${NC} $file"
    else
        echo -e "  ${RED}✗${NC} $file (missing)"
    fi
done
echo

# Check if all files exist
MISSING=0
for file in "${PHP_FILES[@]}"; do
    if [ ! -f "$file" ]; then
        MISSING=1
    fi
done

if [ $MISSING -eq 1 ]; then
    echo -e "${RED}Error: Some files are missing!${NC}"
    exit 1
fi

# Create logs directory if needed
echo "Creating logs directory structure..."
mkdir -p logs
chmod 755 logs

# Create deployment package
echo "Creating deployment package..."
DEPLOY_DIR="deploy_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DEPLOY_DIR/api"
mkdir -p "$DEPLOY_DIR/logs"

# Copy files
for file in "${PHP_FILES[@]}"; do
    cp "$file" "$DEPLOY_DIR/$file"
done

# Create deployment instructions
cat > "$DEPLOY_DIR/DEPLOYMENT_INSTRUCTIONS.txt" << EOF
AZE_Gemini PHP Backend Deployment Instructions
==============================================

1. Connect to FTP server:
   Host: wp10454681.server-he.de
   User: 10454681-ftpaze
   Protocol: FTP with TLS

2. Navigate to: /aze/

3. Upload the following files:
   - api/error-handler.php
   - api/structured-logger.php
   - api/security-headers.php
   - api/health.php
   - api/login.php (overwrites existing)
   - api/validation.php (if changed)

4. Create/verify logs directory:
   - Ensure /aze/logs/ directory exists
   - Set permissions to 755

5. Test the deployment:
   - Visit https://aze.mikropartner.de/api/health.php
   - Should return JSON with health status

6. Monitor logs:
   - Check /aze/logs/app-$(date +%Y-%m-%d).log for any errors

Features Added:
- Centralized error handling
- Structured logging with rotation
- Security headers on all endpoints
- Health check endpoint
- Improved input validation

Security Improvements:
- CORS properly configured
- Security headers (CSP, HSTS, etc.)
- Rate limiting
- Better error messages (no stack traces in production)
EOF

echo -e "${GREEN}✓ Deployment package created: $DEPLOY_DIR/${NC}"
echo
echo "Next steps:"
echo "1. Use FTP client to upload files from $DEPLOY_DIR"
echo "2. Or use automated deployment if credentials are available"
echo
echo -e "${YELLOW}Note: Frontend changes are NOT included in this deployment${NC}"
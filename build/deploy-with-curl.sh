#!/bin/bash
#
# Deploy AZE_Gemini using curl FTP
#

set -euo pipefail

FTP_HOST="wp10454681.server-he.de"
FTP_USER="ftp10454681-aze3"
FTP_PASS="Start321"
FTP_BASE="ftp://${FTP_HOST}/aze"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}AZE_Gemini FTP Deployment${NC}"
echo "=========================="
echo

# Function to upload file
upload_file() {
    local local_file="$1"
    local remote_path="$2"
    
    echo -n "Uploading ${local_file} -> ${remote_path}... "
    
    if curl -s -S --ftp-create-dirs \
        --ftp-ssl \
        --user "${FTP_USER}:${FTP_PASS}" \
        -T "${local_file}" \
        "${FTP_BASE}${remote_path}"; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

# 1. Upload fix-permissions.php
echo -e "${YELLOW}Step 1: Uploading fix-permissions.php${NC}"
upload_file "fix-permissions.php" "/fix-permissions.php"

# 2. Upload API files
echo -e "\n${YELLOW}Step 2: Uploading API files${NC}"

# Core API files
API_FILES=(
    "api/error-handler.php"
    "api/structured-logger.php"
    "api/security-headers.php"
    "api/health.php"
    "api/monitoring.php"
)

for file in "${API_FILES[@]}"; do
    if [ -f "$file" ]; then
        upload_file "$file" "/${file}"
    fi
done

# Upload from deployment directory
DEPLOY_DIR=$(ls -d deploy_* 2>/dev/null | sort | tail -1)
if [ -n "$DEPLOY_DIR" ] && [ -d "$DEPLOY_DIR/api" ]; then
    echo -e "\nUsing deployment directory: $DEPLOY_DIR"
    for file in "$DEPLOY_DIR"/api/*.php; do
        if [ -f "$file" ]; then
            filename=$(basename "$file")
            upload_file "$file" "/api/$filename"
        fi
    done
fi

# 3. Upload monitoring dashboard
echo -e "\n${YELLOW}Step 3: Uploading monitoring dashboard${NC}"
if [ -f "monitoring-dashboard.html" ]; then
    upload_file "monitoring-dashboard.html" "/monitoring-dashboard.html"
fi

# 4. Create .htaccess for security
echo -e "\n${YELLOW}Step 4: Creating security files${NC}"
cat > .htaccess << 'EOF'
# Security settings for AZE directories
Options -Indexes
Options -FollowSymLinks

# Protect sensitive directories
<FilesMatch "\.(env|log|sql|ini|conf|cfg)$">
    Require all denied
</FilesMatch>

# Protect directories
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(logs|data|cache)/ - [F,L]
</IfModule>
EOF

upload_file ".htaccess" "/.htaccess"

# 5. Create directory structure files
echo -e "\n${YELLOW}Step 5: Creating directory markers${NC}"
echo "# Logs directory" > logs_marker.txt
echo "# Data directory" > data_marker.txt
echo "# Cache directory" > cache_marker.txt

upload_file "logs_marker.txt" "/logs/.gitkeep"
upload_file "data_marker.txt" "/data/.gitkeep"
upload_file "cache_marker.txt" "/cache/.gitkeep"
upload_file "cache_marker.txt" "/cache/rate-limit/.gitkeep"

# Cleanup
rm -f logs_marker.txt data_marker.txt cache_marker.txt

echo -e "\n${GREEN}✅ Deployment completed!${NC}"
echo
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Run fix-permissions.php:"
echo "   curl -k https://aze.mikropartner.de/fix-permissions.php"
echo
echo "2. Delete fix-permissions.php:"
echo "   (Use FTP client to delete the file)"
echo
echo "3. Test health check:"
echo "   curl -k https://aze.mikropartner.de/api/health.php"
echo
echo "4. Test monitoring (requires admin login):"
echo "   https://aze.mikropartner.de/monitoring-dashboard.html"
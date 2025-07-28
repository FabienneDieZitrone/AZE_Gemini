#!/bin/bash
#
# Deploy remaining files to ensure full sync
#

set -euo pipefail

FTP_HOST="wp10454681.server-he.de"
FTP_USER="ftp10454681-aze3"
FTP_PASS="Start321"
FTP_BASE="ftp://${FTP_HOST}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Deploying remaining files${NC}"
echo "========================="
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

# Update all PHP files that might need error handling
echo -e "${YELLOW}Updating PHP APIs...${NC}"

# APIs that need to be re-uploaded
API_FILES=(
    "api/time-entries.php"
    "api/timer-control.php"
    "api/users.php"
    "api/approvals.php"
    "api/masterdata.php"
    "api/history.php"
    "api/auth-status.php"
    "api/auth-logout.php"
    "api/settings.php"
    "api/logs.php"
)

for file in "${API_FILES[@]}"; do
    if [ -f "$file" ]; then
        upload_file "$file" "/${file}"
    fi
done

# Upload config file
echo -e "\n${YELLOW}Uploading config...${NC}"
if [ -f "config.php" ]; then
    upload_file "config.php" "/config.php"
fi

# Upload .htaccess (already done but making sure)
echo -e "\n${YELLOW}Ensuring .htaccess...${NC}"
upload_file ".htaccess" "/.htaccess"

echo -e "\n${GREEN}✅ Deployment sync completed!${NC}"
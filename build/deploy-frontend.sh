#!/bin/bash
#
# Deploy Frontend Build to Production
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

echo -e "${GREEN}Frontend Deployment${NC}"
echo "==================="
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

# Check if dist directory exists
if [ ! -d "dist" ]; then
    echo -e "${RED}Error: dist directory not found!${NC}"
    echo "Run 'npm run build' first"
    exit 1
fi

# Upload index.html
echo -e "${YELLOW}Uploading index.html...${NC}"
upload_file "dist/index.html" "/index.html"

# Upload assets
echo -e "\n${YELLOW}Uploading assets...${NC}"
for file in dist/assets/*; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        upload_file "$file" "/assets/$filename"
    fi
done

echo -e "\n${GREEN}✅ Frontend deployment completed!${NC}"
echo
echo "Test the live app at: https://aze.mikropartner.de"
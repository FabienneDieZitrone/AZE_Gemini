#!/bin/bash
#
# Secure Deployment Script for AZE_Gemini
# This script uses environment variables for credentials
#

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Secure AZE_Gemini Deployment${NC}"
echo "============================"
echo

# Check if .env.local exists
if [ -f .env.local ]; then
    echo "Loading credentials from .env.local..."
    export $(grep -v '^#' .env.local | xargs)
fi

# Validate required environment variables
MISSING_VARS=()

if [ -z "${FTP_HOST:-}" ]; then
    MISSING_VARS+=("FTP_HOST")
fi

if [ -z "${FTP_USER:-}" ]; then
    MISSING_VARS+=("FTP_USER")
fi

if [ -z "${FTP_PASSWORD:-}" ]; then
    MISSING_VARS+=("FTP_PASSWORD")
fi

if [ ${#MISSING_VARS[@]} -ne 0 ]; then
    echo -e "${RED}Error: Missing required environment variables:${NC}"
    printf '%s\n' "${MISSING_VARS[@]}"
    echo
    echo "Please set these variables:"
    echo "1. Copy .env.example to .env.local"
    echo "2. Add your credentials to .env.local"
    echo "3. Run this script again"
    echo
    echo "Or set them directly:"
    echo "export FTP_USER='your-username'"
    echo "export FTP_PASSWORD='your-password'"
    exit 1
fi

echo -e "${GREEN}✓ Credentials loaded${NC}"
echo "Host: $FTP_HOST"
echo "User: $FTP_USER"
echo "Pass: ****" # Never display password
echo

# Function to upload file
upload_file() {
    local local_file="$1"
    local remote_path="$2"
    
    echo -n "Uploading ${local_file}... "
    
    if curl -s -S --ftp-create-dirs \
        --ftp-ssl \
        --user "${FTP_USER}:${FTP_PASSWORD}" \
        -T "${local_file}" \
        "ftp://${FTP_HOST}${remote_path}"; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        return 1
    fi
}

# Deploy based on argument
case "${1:-all}" in
    frontend)
        echo -e "${YELLOW}Deploying frontend...${NC}"
        if [ -d "dist" ]; then
            upload_file "dist/index.html" "/index.html"
            for file in dist/assets/*; do
                if [ -f "$file" ]; then
                    filename=$(basename "$file")
                    upload_file "$file" "/assets/$filename"
                fi
            done
        else
            echo -e "${RED}Error: dist/ directory not found. Run 'npm run build' first${NC}"
            exit 1
        fi
        ;;
    
    backend)
        echo -e "${YELLOW}Deploying backend...${NC}"
        for file in api/*.php; do
            if [ -f "$file" ]; then
                upload_file "$file" "/api/$(basename "$file")"
            fi
        done
        ;;
    
    all)
        echo -e "${YELLOW}Deploying all...${NC}"
        $0 backend
        $0 frontend
        ;;
    
    *)
        echo "Usage: $0 [frontend|backend|all]"
        exit 1
        ;;
esac

echo -e "\n${GREEN}✅ Deployment completed!${NC}"
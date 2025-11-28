#!/bin/bash
#
# Secure Deployment Script for AZE_Gemini
# This script uses environment variables for credentials
#

set -euo pipefail

# Change to script directory
cd "$(dirname "$0")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Secure AZE_Gemini Deployment${NC}"
echo "============================"
echo

# Load .env.production only if not provided via environment
if [ -z "${FTP_HOST:-}" ] || [ -z "${FTP_USER:-}" ] || [ -z "${FTP_PASS:-}" ] || [ -z "${FTP_PATH:-}" -a -z "${FTP_BASE_PATH:-}" ]; then
  if [ -f .env.production ]; then
      echo "Loading credentials from .env.production..."
      export $(grep -v '^#' .env.production | xargs)
  else
      echo -e "${RED}Error: .env.production not found and required variables not set${NC}"
      echo "Please create .env.production with deployment credentials or export FTP_HOST/FTP_USER/FTP_PASS."
      exit 1
  fi
fi

# Validate required environment variables
MISSING_VARS=()

if [ -z "${FTP_HOST:-}" ]; then
    MISSING_VARS+=("FTP_HOST")
fi

if [ -z "${FTP_USER:-}" ]; then
    MISSING_VARS+=("FTP_USER")
fi

if [ -z "${FTP_PASS:-}" ]; then
    MISSING_VARS+=("FTP_PASS")
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

# Support legacy var name FTP_PATH as fallback
FTP_BASE_PATH_RAW="${FTP_BASE_PATH:-${FTP_PATH:-/}}"
FTP_BASE_PATH="$FTP_BASE_PATH_RAW"
# Normalize base path: leading slash, no trailing slash (except root)
if [[ "$FTP_BASE_PATH" != /* ]]; then
  FTP_BASE_PATH="/$FTP_BASE_PATH"
fi
if [[ "$FTP_BASE_PATH" != "/" ]]; then
  FTP_BASE_PATH="${FTP_BASE_PATH%/}"
fi

echo -e "${GREEN}✓ Credentials loaded${NC}"
echo "Host    : $FTP_HOST"
echo "User    : $FTP_USER"
echo "BasePath: $FTP_BASE_PATH"
echo "Pass    : ****" # Never display password
echo

# Function to upload file
upload_file() {
    local local_file="$1"
    local remote_path="$2"
    
    echo -n "Uploading ${local_file}... "
    if [ "$DRY" = "1" ]; then
        echo "[dry-run] -> ftp://${FTP_HOST}${FTP_BASE_PATH}${remote_path}"
        return 0
    fi
    
    # Use explicit FTP over TLS - HostEurope typically requires SSL/TLS
    if curl -s -S --ftp-create-dirs \
        --ftp-ssl \
        --insecure \
        --user "${FTP_USER}:${FTP_PASS}" \
        -T "${local_file}" \
        "ftp://${FTP_HOST}${FTP_BASE_PATH}${remote_path}"; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        echo "  Debug: Upload failed. Trying with verbose..."
        
        # Try with verbose for debugging
        curl -v --ftp-create-dirs \
            --ftp-ssl \
            --insecure \
            --user "${FTP_USER}:${FTP_PASS}" \
            -T "${local_file}" \
            "ftp://${FTP_HOST}${FTP_BASE_PATH}${remote_path}" 2>&1 | grep -E "230|550|530|226|Connected|cwd|cwd-success"
        return 1
    fi
}

# Deploy based on argument
DRY=${DEPLOY_DRY_RUN:-0}
MODE="${1:-all}"

case "$MODE" in
    verify)
        echo -e "${YELLOW}Verifying FTP connectivity and base path...${NC}"
        echo "Using: ftp://$FTP_HOST$FTP_BASE_PATH"
        echo "Listing base directory (no upload, read-only)..."
        # Try explicit FTPS
        if curl -s -S --ftp-ssl --insecure --user "${FTP_USER}:${FTP_PASS}" \
          "ftp://${FTP_HOST}${FTP_BASE_PATH}/" --list-only >/dev/null; then
          echo -e "${GREEN}✓ Base path accessible${NC}"
          exit 0
        else
          echo -e "${RED}✗ Base path not accessible${NC}"
          exit 2
        fi
        ;;
esac

case "$MODE" in
    frontend)
        echo -e "${YELLOW}Deploying frontend...${NC}"
        if [ -d "dist" ]; then
            # Ensure index.php is deployed (smart SPA bootstrap)
            if [ -f "index.php" ]; then
              upload_file "index.php" "/index.php"
            fi
            # Deploy Vite output under /dist and /assets (dual for safety)
            if [ "$DRY" = "1" ]; then
              echo "[dry-run] Would upload dist/index.html -> $FTP_BASE_PATH/dist/index.html"
            else
              upload_file "dist/index.html" "/dist/index.html"
            fi
            # Upload static assets (PNG, SVG) from dist root
            for file in dist/*.png dist/*.svg; do
                if [ -f "$file" ]; then
                    filename=$(basename "$file")
                    if [ "$DRY" = "1" ]; then
                      echo "[dry-run] Would upload $file -> $FTP_BASE_PATH/$filename"
                    else
                      upload_file "$file" "/$filename" || true
                    fi
                fi
            done
            # Upload JS/CSS assets
            for file in dist/assets/*; do
                if [ -f "$file" ]; then
                    filename=$(basename "$file")
                    # Primary assets path expected by index.php is /assets if present
                    if [ "$DRY" = "1" ]; then
                      echo "[dry-run] Would upload $file -> $FTP_BASE_PATH/assets/$filename"
                    else
                      upload_file "$file" "/assets/$filename" || true
                    fi
                    # Also place under /dist/assets to satisfy direct dist references if any
                    if [ "$DRY" = "1" ]; then
                      echo "[dry-run] Would upload $file -> $FTP_BASE_PATH/dist/assets/$filename"
                    else
                      upload_file "$file" "/dist/assets/$filename"
                    fi
                fi
            done
        else
            echo -e "${RED}Error: dist/ directory not found. Run 'npm run build' first${NC}"
            exit 1
        fi
        ;;
    
    backend)
        echo -e "${YELLOW}Deploying backend...${NC}"
        # Upload PHP files
        for file in api/*.php; do
            if [ -f "$file" ]; then
                if [ "$DRY" = "1" ]; then
                  echo "[dry-run] Would upload $file -> $FTP_BASE_PATH/api/$(basename "$file")"
                else
                  upload_file "$file" "/api/$(basename "$file")"
                fi
            fi
        done
        # Upload .env file to api directory
        if [ -f ".env.production" ]; then
            if [ "$DRY" = "1" ]; then
              echo "[dry-run] Would upload .env.production -> $FTP_BASE_PATH/api/.env"
            else
              upload_file ".env.production" "/api/.env"
            fi
        fi
        ;;
    
    all)
        echo -e "${YELLOW}Deploying all...${NC}"
        bash "$0" backend
        bash "$0" frontend
        ;;
    
    *)
        echo "Usage: $0 [frontend|backend|all]"
        exit 1
        ;;
esac

echo -e "\n${GREEN}✅ Deployment completed!${NC}"

#!/bin/bash
#
# Deployment Validation Script
# Verifies that deployment was successful and all paths are correct
#
# Usage: bash scripts/validate-deployment.sh

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Change to build directory
cd "$(dirname "$0")/.."

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║  AZE Gemini Deployment Validation         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo

# Load credentials
if [ -f .env.production ]; then
    echo -e "${GREEN}✓ Loading credentials from .env.production${NC}"
    export $(grep -v '^#' .env.production | xargs)
else
    echo -e "${RED}✗ .env.production not found${NC}"
    exit 1
fi

# Validation counters
PASSED=0
FAILED=0
WARNINGS=0

# Test function
test_check() {
    local name="$1"
    local status="$2"
    local message="${3:-}"

    if [ "$status" = "pass" ]; then
        echo -e "${GREEN}✓${NC} $name"
        ((PASSED++))
    elif [ "$status" = "fail" ]; then
        echo -e "${RED}✗${NC} $name"
        [ -n "$message" ] && echo -e "  ${RED}→${NC} $message"
        ((FAILED++))
    elif [ "$status" = "warn" ]; then
        echo -e "${YELLOW}⚠${NC} $name"
        [ -n "$message" ] && echo -e "  ${YELLOW}→${NC} $message"
        ((WARNINGS++))
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📦 1. LOCAL BUILD VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check dist directory exists
if [ -d "dist" ]; then
    test_check "dist/ directory exists" "pass"
else
    test_check "dist/ directory exists" "fail" "Run 'npm run build' first"
    exit 1
fi

# Check dist/assets exists
if [ -d "dist/assets" ]; then
    test_check "dist/assets/ directory exists" "pass"
else
    test_check "dist/assets/ directory exists" "fail"
fi

# Check for JavaScript bundle
if ls dist/assets/index-*.js >/dev/null 2>&1; then
    test_check "JavaScript bundle exists" "pass"
    JS_FILE=$(ls dist/assets/index-*.js | head -1)
    JS_SIZE=$(du -h "$JS_FILE" | cut -f1)
    echo -e "  ${BLUE}→${NC} $JS_FILE ($JS_SIZE)"
else
    test_check "JavaScript bundle exists" "fail"
fi

# Check for CSS bundle
if ls dist/assets/index-*.css >/dev/null 2>&1; then
    test_check "CSS bundle exists" "pass"
    CSS_FILE=$(ls dist/assets/index-*.css | head -1)
    CSS_SIZE=$(du -h "$CSS_FILE" | cut -f1)
    echo -e "  ${BLUE}→${NC} $CSS_FILE ($CSS_SIZE)"
else
    test_check "CSS bundle exists" "fail"
fi

# Check index.php exists
if [ -f "index.php" ]; then
    test_check "index.php exists" "pass"
else
    test_check "index.php exists" "fail"
fi

# Check .htaccess exists
if [ -f ".htaccess" ]; then
    test_check ".htaccess exists" "pass"
else
    test_check ".htaccess exists" "warn" "Missing .htaccess - DirectoryIndex may not work"
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔐 2. CREDENTIALS VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check FTP credentials
if [ -n "${FTP_HOST:-}" ]; then
    test_check "FTP_HOST configured" "pass"
else
    test_check "FTP_HOST configured" "fail"
fi

if [ -n "${FTP_USER:-}" ]; then
    test_check "FTP_USER configured" "pass"
else
    test_check "FTP_USER configured" "fail"
fi

if [ -n "${FTP_PASS:-}" ]; then
    test_check "FTP_PASS configured" "pass"
else
    test_check "FTP_PASS configured" "fail"
fi

# Check FTP_PATH
FTP_PATH="${FTP_PATH:-/}"
if [ "$FTP_PATH" = "/" ]; then
    test_check "FTP_PATH configured correctly" "pass"
    echo -e "  ${BLUE}→${NC} FTP_PATH=/ (correct for HostEurope)"
else
    test_check "FTP_PATH configured" "warn" "FTP_PATH=$FTP_PATH (should be / for HostEurope)"
fi

# Check OAuth credentials
if [ -n "${OAUTH_CLIENT_ID:-}" ] && [ -n "${OAUTH_CLIENT_SECRET:-}" ]; then
    test_check "OAuth credentials configured" "pass"
else
    test_check "OAuth credentials configured" "fail"
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 3. FTP CONNECTIVITY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Test FTP connection
echo -n "Testing FTP connection... "
if curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/" --list-only >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
    test_check "FTP connection successful" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "FTP connection successful" "fail" "Cannot connect to FTP server"
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📂 4. REMOTE FILE VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if index.php exists on server
echo -n "Checking index.php on server... "
if curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/index.php" -I 2>&1 | grep -q "213\|250"; then
    echo -e "${GREEN}✓${NC}"
    test_check "index.php deployed" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "index.php deployed" "fail"
fi

# Check if assets directory exists
echo -n "Checking /assets/ directory on server... "
if curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/assets/" --list-only >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
    test_check "/assets/ directory exists on server" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "/assets/ directory exists on server" "fail"
fi

# Check if api directory exists
echo -n "Checking /api/ directory on server... "
if curl -s -S --ftp-ssl --insecure --max-time 10 \
    --user "${FTP_USER}:${FTP_PASS}" \
    "ftp://${FTP_HOST}/api/" --list-only >/dev/null 2>&1; then
    echo -e "${GREEN}✓${NC}"
    test_check "/api/ directory exists on server" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "/api/ directory exists on server" "fail"
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌍 5. HTTP ENDPOINT VALIDATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

DOMAIN="https://aze.mikropartner.de"

# Test main page
echo -n "Testing ${DOMAIN}/ ... "
if curl -s -S --max-time 10 -I "$DOMAIN/" | grep -q "200\|302"; then
    echo -e "${GREEN}✓${NC}"
    test_check "Main page accessible" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "Main page accessible" "fail"
fi

# Extract asset hash from index.php
if [ -f "index.php" ]; then
    ASSET_JS=$(grep -oP 'index-[A-Za-z0-9_-]+\.js' index.php | head -1)
    if [ -n "$ASSET_JS" ]; then
        echo -n "Testing ${DOMAIN}/assets/${ASSET_JS} ... "
        if curl -s -S --max-time 10 -I "$DOMAIN/assets/$ASSET_JS" | grep -q "200"; then
            echo -e "${GREEN}✓${NC}"
            test_check "JavaScript bundle accessible via HTTP" "pass"
        else
            echo -e "${RED}✗${NC}"
            test_check "JavaScript bundle accessible via HTTP" "fail"
        fi
    fi

    ASSET_CSS=$(grep -oP 'index-[A-Za-z0-9_-]+\.css' index.php | head -1)
    if [ -n "$ASSET_CSS" ]; then
        echo -n "Testing ${DOMAIN}/assets/${ASSET_CSS} ... "
        if curl -s -S --max-time 10 -I "$DOMAIN/assets/$ASSET_CSS" | grep -q "200"; then
            echo -e "${GREEN}✓${NC}"
            test_check "CSS bundle accessible via HTTP" "pass"
        else
            echo -e "${RED}✗${NC}"
            test_check "CSS bundle accessible via HTTP" "fail"
        fi
    fi
fi

# Test auth-status API
echo -n "Testing ${DOMAIN}/api/auth-status.php ... "
if curl -s -S --max-time 10 -I "$DOMAIN/api/auth-status.php" | grep -q "200"; then
    echo -e "${GREEN}✓${NC}"
    test_check "auth-status.php accessible" "pass"
else
    echo -e "${RED}✗${NC}"
    test_check "auth-status.php accessible" "fail"
fi

echo
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 VALIDATION SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TOTAL=$((PASSED + FAILED + WARNINGS))
echo
echo -e "${GREEN}Passed:  ${PASSED}/${TOTAL}${NC}"
echo -e "${YELLOW}Warnings: ${WARNINGS}/${TOTAL}${NC}"
echo -e "${RED}Failed:  ${FAILED}/${TOTAL}${NC}"
echo

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ DEPLOYMENT VALIDATION SUCCESSFUL!${NC}"
    echo
    echo "Next steps:"
    echo "1. Test OAuth flow: https://aze.mikropartner.de/"
    echo "2. Login with Azure AD"
    echo "3. Verify dashboard loads"
    exit 0
else
    echo -e "${RED}❌ DEPLOYMENT VALIDATION FAILED!${NC}"
    echo
    echo "Action required:"
    echo "1. Fix the failed checks above"
    echo "2. Re-run deployment: bash deploy-secure.sh all"
    echo "3. Run validation again: bash scripts/validate-deployment.sh"
    exit 1
fi

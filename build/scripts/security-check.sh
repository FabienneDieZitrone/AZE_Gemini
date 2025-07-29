#!/bin/bash
# Security validation script

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "Security Check for AZE Deployment"
echo "================================="
echo ""

ISSUES=0

# Check for exposed credentials in tracked files
echo -n "Checking for exposed credentials in Git... "
if git grep -i "ftp10454681\|password.*=" 2>/dev/null; then
    echo -e "${RED}FAILED${NC}"
    echo "Found exposed credentials in tracked files!"
    ISSUES=$((ISSUES + 1))
else
    echo -e "${GREEN}PASSED${NC}"
fi

# Check .gitignore
echo -n "Checking .gitignore configuration... "
REQUIRED_IGNORES=(".env.local" "credentials.json" ".credentials/")
MISSING_IGNORES=()
for ignore in "${REQUIRED_IGNORES[@]}"; do
    if ! grep -q "$ignore" .gitignore 2>/dev/null; then
        MISSING_IGNORES+=("$ignore")
    fi
done

if [ ${#MISSING_IGNORES[@]} -eq 0 ]; then
    echo -e "${GREEN}PASSED${NC}"
else
    echo -e "${RED}FAILED${NC}"
    echo "Missing ignores: ${MISSING_IGNORES[*]}"
    ISSUES=$((ISSUES + 1))
fi

# Check for pre-commit hook
echo -n "Checking Git pre-commit hook... "
if [ -x ../.git/hooks/pre-commit ]; then
    echo -e "${GREEN}PASSED${NC}"
else
    echo -e "${YELLOW}WARNING${NC}"
    echo "Pre-commit hook not found or not executable"
fi

# Check for sensitive files
echo -n "Checking for sensitive files... "
SENSITIVE_FILES=($(find . -name "*.py" -o -name "*ftp*" -o -name "*credential*" -o -name "*secret*" 2>/dev/null | grep -v ".git" | grep -v "node_modules" || true))
if [ ${#SENSITIVE_FILES[@]} -gt 0 ]; then
    echo -e "${YELLOW}WARNING${NC}"
    echo "Found potentially sensitive files:"
    printf '%s\n' "${SENSITIVE_FILES[@]}"
else
    echo -e "${GREEN}PASSED${NC}"
fi

# Check environment setup
echo -n "Checking environment configuration... "
if [ -f .env.example ]; then
    echo -e "${GREEN}PASSED${NC}"
else
    echo -e "${RED}FAILED${NC}"
    echo ".env.example not found"
    ISSUES=$((ISSUES + 1))
fi

# Summary
echo ""
echo "================================="
if [ $ISSUES -eq 0 ]; then
    echo -e "${GREEN}All security checks passed!${NC}"
else
    echo -e "${RED}Found $ISSUES security issues that need attention.${NC}"
fi
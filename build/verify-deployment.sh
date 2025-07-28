#!/bin/bash
#
# Comprehensive Deployment Verification
#

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_URL="https://aze.mikropartner.de"
ERRORS=0

echo -e "${GREEN}AZE_Gemini Deployment Verification${NC}"
echo "=================================="
echo

# Function to test endpoint
test_endpoint() {
    local url="$1"
    local expected_code="$2"
    local description="$3"
    
    echo -n "Testing $description... "
    response=$(curl -k -s -o /dev/null -w "%{http_code}" "$url")
    
    if [ "$response" == "$expected_code" ]; then
        echo -e "${GREEN}✓${NC} ($response)"
    else
        echo -e "${RED}✗${NC} (Expected: $expected_code, Got: $response)"
        ((ERRORS++))
    fi
}

# 1. Frontend Tests
echo -e "${YELLOW}1. Frontend Tests${NC}"
test_endpoint "$BASE_URL/" "200" "Main page"
test_endpoint "$BASE_URL/assets/index-Jq3KfgsT.css" "200" "CSS bundle"
test_endpoint "$BASE_URL/assets/index-CDzvp6UE.js" "200" "JS bundle"
echo

# 2. API Health Tests
echo -e "${YELLOW}2. API Health Tests${NC}"
test_endpoint "$BASE_URL/api/health.php" "200" "Health check"

# Check health details
echo -n "Checking health status details... "
health_status=$(curl -k -s "$BASE_URL/api/health.php" | grep -o '"status":"[^"]*"' | head -1 | cut -d'"' -f4)
if [ "$health_status" == "healthy" ] || [ "$health_status" == "degraded" ]; then
    echo -e "${GREEN}✓${NC} (Status: $health_status)"
else
    echo -e "${RED}✗${NC} (Status: $health_status)"
    ((ERRORS++))
fi
echo

# 3. Security Headers Test
echo -e "${YELLOW}3. Security Headers Test${NC}"
echo -n "Checking security headers... "
headers=$(curl -k -s -I "$BASE_URL/api/health.php")
has_csp=$(echo "$headers" | grep -i "Content-Security-Policy")
has_xframe=$(echo "$headers" | grep -i "X-Frame-Options")

if [ -n "$has_csp" ]; then
    echo -e "${GREEN}✓${NC} CSP present"
else
    echo -e "${YELLOW}⚠${NC} CSP missing"
fi
echo

# 4. Error Handling Tests
echo -e "${YELLOW}4. Error Handling Tests${NC}"
test_endpoint "$BASE_URL/api/login.php" "401" "Login without auth"

# Test invalid method
echo -n "Testing method validation... "
response=$(curl -k -s -X DELETE "$BASE_URL/api/login.php" | grep -o "Method")
if [ -n "$response" ]; then
    echo -e "${GREEN}✓${NC} (Method validation working)"
else
    echo -e "${RED}✗${NC} (Method validation failed)"
    ((ERRORS++))
fi
echo

# 5. New Features Test
echo -e "${YELLOW}5. New Features Test${NC}"
test_endpoint "$BASE_URL/monitoring-dashboard.html" "200" "Monitoring dashboard"
echo

# 6. Database Connection Test
echo -e "${YELLOW}6. Database Connection Test${NC}"
echo -n "Checking database connection... "
db_status=$(curl -k -s "$BASE_URL/api/health.php" | grep -o '"database":{"status":"[^"]*"' | cut -d'"' -f6)
if [ "$db_status" == "healthy" ]; then
    echo -e "${GREEN}✓${NC} Database connected"
else
    echo -e "${RED}✗${NC} Database issue"
    ((ERRORS++))
fi
echo

# Summary
echo -e "${YELLOW}Summary${NC}"
echo "======="
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed! Deployment successful.${NC}"
else
    echo -e "${RED}❌ $ERRORS tests failed. Check the issues above.${NC}"
fi
echo

# Additional info
echo -e "${YELLOW}Live URLs:${NC}"
echo "- App: $BASE_URL"
echo "- Health: $BASE_URL/api/health.php"
echo "- Monitoring: $BASE_URL/monitoring-dashboard.html"
echo

# Issues to note
echo -e "${YELLOW}Known Issues:${NC}"
echo "- Filesystem status: degraded (logs/data directories need permissions)"
echo "- fix-permissions.php needs to be deleted from server"
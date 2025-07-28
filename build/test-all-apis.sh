#!/bin/bash
#
# Comprehensive API Testing
#

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_URL="https://aze.mikropartner.de"
PASSED=0
FAILED=0

echo -e "${GREEN}Comprehensive API Testing${NC}"
echo "========================"
echo

# Function to test API
test_api() {
    local endpoint="$1"
    local method="$2"
    local expected="$3"
    local description="$4"
    
    echo -n "Testing $endpoint ($method)... "
    
    response=$(curl -k -s -X "$method" "$BASE_URL$endpoint" -o /dev/null -w "%{http_code}")
    
    if [ "$response" == "$expected" ]; then
        echo -e "${GREEN}✓${NC} ($response)"
        ((PASSED++))
    else
        echo -e "${RED}✗${NC} (Expected: $expected, Got: $response)"
        ((FAILED++))
    fi
}

# Test all endpoints
echo -e "${YELLOW}1. Health & Monitoring${NC}"
test_api "/api/health.php" "GET" "503" "Health check (503 due to degraded)"
test_api "/api/monitoring.php" "GET" "403" "Monitoring (requires admin)"
echo

echo -e "${YELLOW}2. Authentication APIs${NC}"
test_api "/api/auth-status.php" "GET" "200" "Auth status"
test_api "/api/auth-start.php" "GET" "302" "Auth start (redirect)"
test_api "/api/auth-logout.php" "POST" "401" "Auth logout (no session)"
test_api "/api/login.php" "POST" "401" "Login (no session)"
echo

echo -e "${YELLOW}3. Core APIs (without auth)${NC}"
test_api "/api/time-entries.php" "GET" "401" "Time entries"
test_api "/api/timer-control.php" "POST" "401" "Timer control"
test_api "/api/users.php" "GET" "401" "Users"
test_api "/api/approvals.php" "GET" "401" "Approvals"
test_api "/api/masterdata.php" "GET" "401" "Master data"
test_api "/api/history.php" "GET" "401" "History"
test_api "/api/settings.php" "GET" "401" "Settings"
test_api "/api/logs.php" "GET" "401" "Logs"
echo

echo -e "${YELLOW}4. Method Validation${NC}"
test_api "/api/login.php" "DELETE" "405" "Invalid method on login"
test_api "/api/health.php" "POST" "405" "Invalid method on health"
echo

echo -e "${YELLOW}5. Security Headers Check${NC}"
echo -n "Checking CSP header... "
if curl -k -s -I "$BASE_URL/api/health.php" | grep -q "Content-Security-Policy"; then
    echo -e "${GREEN}✓${NC} Present"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Missing"
    ((FAILED++))
fi

echo -n "Checking X-Frame-Options... "
if curl -k -s -I "$BASE_URL/api/health.php" | grep -q "X-Frame-Options"; then
    echo -e "${GREEN}✓${NC} Present"
    ((PASSED++))
else
    echo -e "${RED}✗${NC} Missing"
    ((FAILED++))
fi
echo

echo -e "${YELLOW}6. Frontend Assets${NC}"
test_api "/" "GET" "200" "Index page"
test_api "/assets/index-Jq3KfgsT.css" "GET" "200" "CSS bundle"
test_api "/assets/index-CDzvp6UE.js" "GET" "200" "JS bundle"
echo

# Summary
echo -e "${YELLOW}Test Summary${NC}"
echo "============"
echo -e "Passed: ${GREEN}$PASSED${NC}"
echo -e "Failed: ${RED}$FAILED${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "\n${GREEN}✅ All tests passed!${NC}"
else
    echo -e "\n${RED}❌ Some tests failed!${NC}"
fi
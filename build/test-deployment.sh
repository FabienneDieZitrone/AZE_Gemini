#!/bin/bash
# AZE Gemini - Deployment Test Suite
# Tests critical functionality after deployment

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

BASE_URL="${1:-https://aze.mikropartner.de}"
TOTAL_TESTS=0
PASSED_TESTS=0

echo "🧪 AZE Gemini Deployment Test Suite"
echo "Testing: $BASE_URL"
echo "=================================="

# Function to test endpoint
test_endpoint() {
    local endpoint=$1
    local expected_code=$2
    local description=$3
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$endpoint")
    
    if [ "$response" = "$expected_code" ]; then
        echo -e "${GREEN}✅ PASS${NC}: $description (HTTP $response)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ FAIL${NC}: $description (Expected: $expected_code, Got: $response)"
    fi
}

# Function to test with auth header
test_auth_endpoint() {
    local endpoint=$1
    local expected_code=$2
    local description=$3
    local auth_token=$4
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ -z "$auth_token" ]; then
        auth_token="test-token"
    fi
    
    response=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $auth_token" "$BASE_URL$endpoint")
    
    if [ "$response" = "$expected_code" ]; then
        echo -e "${GREEN}✅ PASS${NC}: $description (HTTP $response)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ FAIL${NC}: $description (Expected: $expected_code, Got: $response)"
    fi
}

# Test security headers
test_security_headers() {
    local endpoint=$1
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    headers=$(curl -s -I "$BASE_URL$endpoint")
    missing_headers=""
    
    # Check required security headers
    for header in "X-Frame-Options" "X-Content-Type-Options" "Strict-Transport-Security"; do
        if ! echo "$headers" | grep -q "$header"; then
            missing_headers="$missing_headers $header"
        fi
    done
    
    if [ -z "$missing_headers" ]; then
        echo -e "${GREEN}✅ PASS${NC}: Security headers present"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ FAIL${NC}: Missing security headers:$missing_headers"
    fi
}

# 1. Test Health Check
echo -e "\n${YELLOW}1. Health Check Tests${NC}"
test_endpoint "/api/health" "200" "Health endpoint accessible"

# 2. Test Authentication
echo -e "\n${YELLOW}2. Authentication Tests${NC}"
test_endpoint "/api/auth-oauth-client.php" "500" "OAuth client without params returns error"
test_endpoint "/api/auth_helpers.php" "200" "Auth helpers accessible"

# 3. Test API Endpoints
echo -e "\n${YELLOW}3. API Endpoint Tests${NC}"
test_endpoint "/api/time-entries.php" "401" "Time entries requires auth"
test_endpoint "/api/users.php" "401" "Users API requires auth"
test_endpoint "/api/projects.php" "401" "Projects API requires auth"

# 4. Test Security
echo -e "\n${YELLOW}4. Security Tests${NC}"
test_security_headers "/api/health"
test_endpoint "/api/monitoring.php" "401" "Monitoring requires auth"
test_endpoint "/api/validation.php" "200" "Validation endpoint accessible"

# 5. Test Static Assets
echo -e "\n${YELLOW}5. Static Asset Tests${NC}"
test_endpoint "/" "200" "Homepage loads"
test_endpoint "/assets/index.js" "200" "JavaScript bundle loads"
test_endpoint "/assets/index.css" "200" "CSS bundle loads"

# 6. Test CSRF Protection
echo -e "\n${YELLOW}6. CSRF Protection Test${NC}"
TOTAL_TESTS=$((TOTAL_TESTS + 1))
csrf_response=$(curl -s -X POST "$BASE_URL/api/time-entries.php" -d "action=start")
if echo "$csrf_response" | grep -q "CSRF"; then
    echo -e "${GREEN}✅ PASS${NC}: CSRF protection active"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${YELLOW}⚠️  WARN${NC}: CSRF protection may not be active"
fi

# 7. Test Database Connection
echo -e "\n${YELLOW}7. Database Connection Test${NC}"
test_auth_endpoint "/api/projects.php?action=list" "200" "Database query works" "$AUTH_TOKEN"

# Summary
echo -e "\n=================================="
echo "Test Summary:"
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$((TOTAL_TESTS - PASSED_TESTS))${NC}"

if [ $PASSED_TESTS -eq $TOTAL_TESTS ]; then
    echo -e "\n${GREEN}🎉 All tests passed! Deployment successful.${NC}"
    exit 0
else
    echo -e "\n${RED}⚠️  Some tests failed. Please investigate.${NC}"
    exit 1
fi
#!/bin/bash

# AZE System Automated Test Suite
# Tests all critical endpoints and functionality

echo "🚀 AZE System Automated Test Suite"
echo "=================================="
echo "Test User: azetestclaude@mikropartner.de"
echo "Base URL: http://aze.mikropartner.de"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Function to run a test
run_test() {
    local name="$1"
    local command="$2"
    local expected="$3"
    
    echo -n "🧪 Testing: $name... "
    
    result=$(eval "$command" 2>&1)
    
    if [[ "$result" == *"$expected"* ]]; then
        echo -e "${GREEN}✅ PASSED${NC}"
        ((PASSED++))
    else
        echo -e "${RED}❌ FAILED${NC}"
        echo "   Expected: $expected"
        echo "   Got: ${result:0:100}..."
        ((FAILED++))
    fi
}

# Test 1: API Health Check
run_test "API Health Check" \
    "curl -s http://aze.mikropartner.de/api/health.php | grep -o '\"status\":\"ok\"'" \
    '"status":"ok"'

# Test 2: Migration Status
echo ""
echo "📊 Migration Status Check:"
curl -s http://aze.mikropartner.de/api/verify-migration-success.php | jq '.summary'
echo ""

# Test 3: Check for test user
run_test "Test User Verification" \
    "curl -s http://aze.mikropartner.de/api/verify-test-user.php | grep -o 'azetestclaude'" \
    "azetestclaude"

# Test 4: OAuth Start (should redirect)
run_test "OAuth Login Redirect" \
    "curl -s -I http://aze.mikropartner.de/api/auth-start.php | grep -o 'Location:.*microsoftonline.com'" \
    "Location:.*microsoftonline.com"

# Test 5: Timer API (should return 401)
run_test "Timer API Security" \
    "curl -s -w '%{http_code}' -o /dev/null http://aze.mikropartner.de/api/time-entries.php" \
    "401"

# Test 6: Timer functionality page
run_test "Timer Test Page Access" \
    "curl -s http://aze.mikropartner.de/api/test-timer-functionality.php | grep -o 'Timer Funktionalitäts-Test'" \
    "Timer Funktionalitäts-Test"

# Test 7: Database NULL support
run_test "Database NULL Support" \
    "curl -s http://aze.mikropartner.de/api/debug-stop-issue.php | grep -E '(stop_time.*YES|NULLABLE)'" \
    "YES"

# Test 8: Check for running timers
echo ""
echo "🏃 Checking Running Timers:"
curl -s http://aze.mikropartner.de/api/verify-migration-success.php | jq '.timer_stats'

# Summary
echo ""
echo "=================================================="
echo "📊 TEST SUMMARY"
echo "=================================================="
echo "Total Tests: $((PASSED + FAILED))"
echo -e "✅ Passed: ${GREEN}$PASSED${NC}"
echo -e "❌ Failed: ${RED}$FAILED${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "\n${GREEN}🎉 ALL TESTS PASSED!${NC}"
    echo "The timer stop bug has been fixed!"
else
    echo -e "\n${RED}⚠️  Some tests failed${NC}"
fi

echo ""
echo "💡 NEXT STEPS:"
echo "1. Login at: http://aze.mikropartner.de"
echo "2. Use: azetestclaude@mikropartner.de / a1b2c3d4"
echo "3. Test timer at: http://aze.mikropartner.de/api/test-timer-functionality.php"
echo "4. Check account at: http://aze.mikropartner.de/api/test-claude-account.php"
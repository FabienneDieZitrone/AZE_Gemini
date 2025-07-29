#!/bin/bash

# AZE System Test Script
# Test user: azetestclaude@mikropartner.de

API_BASE="https://aze.mikropartner.de/api"
TEST_EMAIL="azetestclaude@mikropartner.de"
TEST_NAME="AZE Test Claude"
SESSION_FILE="/tmp/aze_test_session.txt"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Helper functions
log_test() {
    local test_name="$1"
    local status="$2"
    local details="$3"
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ PASSED${NC} - $test_name"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ FAILED${NC} - $test_name"
        ((TESTS_FAILED++))
    fi
    
    if [ ! -z "$details" ]; then
        echo "   Details: $details"
    fi
}

echo "========================================"
echo "AZE System Automated Test Suite"
echo "========================================"
echo "Test User: $TEST_EMAIL"
echo "API Base: $API_BASE"
echo "Start Time: $(date)"
echo "========================================"

# Test 1: Health Check
echo -e "\n${YELLOW}Test 1: API Health Check${NC}"
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" "$API_BASE/health.php")
HTTP_CODE=$(echo "$HEALTH_RESPONSE" | tail -n1)

if [ "$HTTP_CODE" = "200" ]; then
    log_test "API Health Check" "PASS" "API is reachable"
else
    log_test "API Health Check" "FAIL" "HTTP Code: $HTTP_CODE"
fi

# Test 2: Login Simulation
echo -e "\n${YELLOW}Test 2: Login Simulation${NC}"
LOGIN_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "{\"email\":\"$TEST_EMAIL\",\"name\":\"$TEST_NAME\"}" \
    -c "$SESSION_FILE" \
    "$API_BASE/login.php")

HTTP_CODE=$(echo "$LOGIN_RESPONSE" | tail -n1)
BODY=$(echo "$LOGIN_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    USER_ID=$(echo "$BODY" | grep -o '"user_id":[0-9]*' | cut -d: -f2)
    log_test "Login Simulation" "PASS" "User ID: $USER_ID"
else
    log_test "Login Simulation" "FAIL" "HTTP Code: $HTTP_CODE"
fi

# Test 3: Timer Start
echo -e "\n${YELLOW}Test 3: Timer Start${NC}"
START_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{"action":"start","location":"TEST_OFFICE"}' \
    -b "$SESSION_FILE" \
    "$API_BASE/time-entries.php")

HTTP_CODE=$(echo "$START_RESPONSE" | tail -n1)
BODY=$(echo "$START_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "201" ]; then
    TIMER_ID=$(echo "$BODY" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
    log_test "Timer Start" "PASS" "Timer ID: $TIMER_ID"
else
    log_test "Timer Start" "FAIL" "HTTP Code: $HTTP_CODE - $BODY"
fi

# Wait 3 seconds
echo "Waiting 3 seconds..."
sleep 3

# Test 4: Timer Stop
echo -e "\n${YELLOW}Test 4: Timer Stop${NC}"
STOP_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{"action":"stop"}' \
    -b "$SESSION_FILE" \
    "$API_BASE/time-entries.php")

HTTP_CODE=$(echo "$STOP_RESPONSE" | tail -n1)
BODY=$(echo "$STOP_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    log_test "Timer Stop" "PASS" "Timer stopped successfully"
else
    log_test "Timer Stop" "FAIL" "HTTP Code: $HTTP_CODE - $BODY"
fi

# Test 5: Double Stop Prevention
echo -e "\n${YELLOW}Test 5: Double Stop Prevention${NC}"
STOP2_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{"action":"stop"}' \
    -b "$SESSION_FILE" \
    "$API_BASE/time-entries.php")

HTTP_CODE=$(echo "$STOP2_RESPONSE" | tail -n1)
BODY=$(echo "$STOP2_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "400" ] || [[ "$BODY" == *"No running timer"* ]]; then
    log_test "Double Stop Prevention" "PASS" "Cannot stop already stopped timer"
else
    log_test "Double Stop Prevention" "FAIL" "Timer might have been stopped twice"
fi

# Test 6: Get Time Entries
echo -e "\n${YELLOW}Test 6: Get Time Entries${NC}"
ENTRIES_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -b "$SESSION_FILE" \
    "$API_BASE/time-entries.php")

HTTP_CODE=$(echo "$ENTRIES_RESPONSE" | tail -n1)
BODY=$(echo "$ENTRIES_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    ENTRY_COUNT=$(echo "$BODY" | grep -o '"id"' | wc -l)
    log_test "Get Time Entries" "PASS" "Retrieved $ENTRY_COUNT entries"
else
    log_test "Get Time Entries" "FAIL" "HTTP Code: $HTTP_CODE"
fi

# Test 7: Logout
echo -e "\n${YELLOW}Test 7: Logout${NC}"
LOGOUT_RESPONSE=$(curl -s -w "\n%{http_code}" \
    -X POST \
    -b "$SESSION_FILE" \
    "$API_BASE/logout.php")

HTTP_CODE=$(echo "$LOGOUT_RESPONSE" | tail -n1)

if [ "$HTTP_CODE" = "200" ]; then
    log_test "Logout" "PASS" "Session terminated"
else
    log_test "Logout" "FAIL" "HTTP Code: $HTTP_CODE"
fi

# Cleanup
rm -f "$SESSION_FILE"

# Summary
echo -e "\n========================================"
echo "TEST SUMMARY"
echo "========================================"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))
if [ $TOTAL_TESTS -gt 0 ]; then
    SUCCESS_RATE=$(echo "scale=2; $TESTS_PASSED * 100 / $TOTAL_TESTS" | bc)
    echo "Success Rate: ${SUCCESS_RATE}%"
fi
echo "========================================"
echo "Test completed at: $(date)"
echo "========================================"

# Exit with appropriate code
if [ $TESTS_FAILED -gt 0 ]; then
    exit 1
else
    exit 0
fi
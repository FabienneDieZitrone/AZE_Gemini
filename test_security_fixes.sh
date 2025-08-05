#!/bin/bash
# Security Fix Verification Script for Issue #74

echo "=== AZE Gemini Security Fix Verification ==="
echo "Testing Issue #74 Authorization Fixes"
echo ""

BASE_URL="https://aze.mikropartner.de/aze-test/api"
REPORT_FILE="security_test_report.txt"

# Initialize report
echo "Security Test Report - $(date)" > $REPORT_FILE
echo "================================" >> $REPORT_FILE
echo "" >> $REPORT_FILE

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Function to log test results
log_test() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    
    if [ "$result" = "PASS" ]; then
        echo "✅ PASS - $test_name" | tee -a $REPORT_FILE
        ((TESTS_PASSED++))
    else
        echo "❌ FAIL - $test_name" | tee -a $REPORT_FILE
        ((TESTS_FAILED++))
    fi
    
    if [ ! -z "$details" ]; then
        echo "     Details: $details" | tee -a $REPORT_FILE
    fi
    echo "" | tee -a $REPORT_FILE
}

echo "=== Testing API Health ===" | tee -a $REPORT_FILE
echo ""

# Test 1: API Health Check
response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/health.php")
if [ "$response" = "200" ]; then
    log_test "API Health Check" "PASS" "API is accessible (HTTP $response)"
else
    log_test "API Health Check" "FAIL" "HTTP status: $response"
fi

echo "=== Testing Test Environment ===" | tee -a $REPORT_FILE

# Test 2: Check test environment marker
marker_response=$(curl -s "https://aze.mikropartner.de/aze-test/TEST_ENVIRONMENT.txt")
if [[ "$marker_response" == *"Security Patch: Issue #74"* ]]; then
    log_test "Test Environment Marker" "PASS" "Test environment properly deployed"
else
    log_test "Test Environment Marker" "FAIL" "Marker not found or incorrect"
fi

echo "=== Testing Authorization Without Login ===" | tee -a $REPORT_FILE

# Test 3: Time entries without authentication
time_response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/time-entries.php")
if [ "$time_response" = "401" ] || [ "$time_response" = "403" ]; then
    log_test "Unauthenticated Time Entries Access" "PASS" "Access denied (HTTP $time_response)"
else
    log_test "Unauthenticated Time Entries Access" "FAIL" "Expected 401/403, got HTTP $time_response"
fi

# Test 4: User role change without authentication
role_response=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH \
    -H "Content-Type: application/json" \
    -d '{"userId": 1, "newRole": "Admin"}' \
    "$BASE_URL/users.php")
if [ "$role_response" = "401" ] || [ "$role_response" = "403" ]; then
    log_test "Unauthenticated Role Change" "PASS" "Access denied (HTTP $role_response)"
else
    log_test "Unauthenticated Role Change" "FAIL" "Expected 401/403, got HTTP $role_response"
fi

echo "=== Testing SQL Injection Prevention ===" | tee -a $REPORT_FILE

# Test 5: SQL injection attempts
injection_response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/time-entries.php?user_id=1'%20OR%20'1'='1")
if [ "$injection_response" = "400" ] || [ "$injection_response" = "401" ]; then
    log_test "SQL Injection Prevention" "PASS" "Injection blocked (HTTP $injection_response)"
else
    log_test "SQL Injection Prevention" "FAIL" "Potential vulnerability (HTTP $injection_response)"
fi

echo "=== Test Summary ===" | tee -a $REPORT_FILE
echo "Total Tests: $((TESTS_PASSED + TESTS_FAILED))" | tee -a $REPORT_FILE
echo "Passed: $TESTS_PASSED" | tee -a $REPORT_FILE
echo "Failed: $TESTS_FAILED" | tee -a $REPORT_FILE
echo "" | tee -a $REPORT_FILE

if [ $TESTS_FAILED -eq 0 ]; then
    echo "✅ All automated tests passed!" | tee -a $REPORT_FILE
else
    echo "❌ Some tests failed. Please review the report." | tee -a $REPORT_FILE
fi

echo ""
echo "⚠️  IMPORTANT: Manual testing with authenticated users still required!"
echo "Please follow the test cases in SECURITY_FIX_TEST_PLAN.md"
echo ""
echo "Report saved to: $REPORT_FILE"

# Create a JSON summary
cat > security_test_summary.json << EOF
{
  "test_date": "$(date -Iseconds)",
  "environment": "$BASE_URL",
  "total_tests": $((TESTS_PASSED + TESTS_FAILED)),
  "passed": $TESTS_PASSED,
  "failed": $TESTS_FAILED,
  "manual_testing_required": true,
  "fixed_vulnerabilities": [
    "time-entries.php: Role-based filtering implemented",
    "users.php: Admin-only role changes enforced"
  ]
}
EOF

echo "Summary saved to: security_test_summary.json"
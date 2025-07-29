#!/bin/bash

echo "=== Testing time-entries.php API ==="
echo "Date: $(date)"
echo

# Test data
DATA='{
    "userId": 1,
    "username": "Test User",
    "date": "2025-07-29",
    "startTime": "09:00:00",
    "stopTime": null,
    "location": "office",
    "role": "employee",
    "updatedBy": "Test User"
}'

echo "Test data:"
echo "$DATA" | jq .
echo

# Function to test endpoint
test_endpoint() {
    local name="$1"
    local url="$2"
    local cookie="$3"
    
    echo "=== Test: $name ==="
    echo "URL: $url"
    if [ -n "$cookie" ]; then
        echo "Cookie: $cookie"
    fi
    
    if [ -n "$cookie" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X POST \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -H "Cookie: $cookie" \
            -d "$DATA" \
            "$url")
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
            -X POST \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$DATA" \
            "$url")
    fi
    
    # Extract HTTP code and body
    http_code=$(echo "$response" | grep -o "HTTP_CODE:[0-9]*" | cut -d: -f2)
    body=$(echo "$response" | sed '/HTTP_CODE:/d')
    
    echo "HTTP Code: $http_code"
    echo "Response:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    echo
}

# Test 1: Without session (should return 401)
test_endpoint "Without session" "http://localhost:8000/api/time-entries.php"

# Test 2: With fake session (should return 401)
test_endpoint "With fake session" "http://localhost:8000/api/time-entries.php" "PHPSESSID=fakesession123"

# Test 3: Test special characters
DATA_SPECIAL='{
    "userId": 1,
    "username": "Müller & Schmidt",
    "date": "2025-07-29",
    "startTime": "09:00:00",
    "stopTime": null,
    "location": "Büro München",
    "role": "Geschäftsführer",
    "updatedBy": "Müller"
}'

echo "=== Test: Special characters ==="
echo "Data: $DATA_SPECIAL"
curl -s -X POST \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$DATA_SPECIAL" \
    "http://localhost:8000/api/time-entries.php" \
    -w "\nHTTP Code: %{http_code}\n"

echo
echo "=== Testing complete ===
#!/bin/bash
echo "=== Test: login.php mit frischer Session ==="

# Step 1: Get fresh session from auth-start
echo "1. Hole Session von auth-start.php..."
curl -k -s -c cookies.txt -D headers.txt 'https://aze.mikropartner.de/api/auth-start.php' > /dev/null 2>&1
SESSION=$(grep 'AZE_SESSION' cookies.txt | awk '{print $7}')
echo "   Session ID: $SESSION"

# Step 2: Simulate setting user data (normallly done by auth-callback)
echo ""
echo "2. Simuliere User-Daten in Session..."
echo "   (In Realität macht das auth-callback.php nach Microsoft-Login)"

# Step 3: Try login.php
echo ""
echo "3. Teste login.php mit Session..."
curl -k -s -b cookies.txt -X POST 'https://aze.mikropartner.de/api/login.php' | head -50

rm -f cookies.txt headers.txt

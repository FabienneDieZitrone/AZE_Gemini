#!/bin/bash
# Simulate complete browser flow

echo "=== STEP 1: Start OAuth (get session cookie) ==="
RESPONSE=$(curl -k -s -c cookies.txt -D headers1.txt 'https://aze.mikropartner.de/api/auth-start.php')
SESSION_COOKIE=$(grep 'AZE_SESSION' cookies.txt | awk '{print $7}')
echo "Session Cookie: $SESSION_COOKIE"

echo -e "\n=== STEP 2: Simulate OAuth callback (user would login at Microsoft) ==="
echo "(Skipping actual Microsoft login - would need real user interaction)"

echo -e "\n=== STEP 3: Check auth-status.php (what App.tsx does on load) ==="
curl -k -s -b cookies.txt 'https://aze.mikropartner.de/api/auth-status.php'

echo -e "\n\n=== STEP 4: Try test-login-minimal.php (what api.ts calls) ==="
curl -k -s -b cookies.txt -X POST 'https://aze.mikropartner.de/api/test-login-minimal.php'

rm -f cookies.txt headers1.txt

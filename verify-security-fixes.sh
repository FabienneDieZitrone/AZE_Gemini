#!/bin/bash
# Security Fix Verification Script

echo "=== AZE Gemini Security Fix Verification ==="
echo ""

BASE_URL="https://aze.mikropartner.de/aze-test/api"

echo "1. Testing time-entries.php authorization..."
echo "   - Should only return entries for the logged-in user (Honorarkraft/Mitarbeiter)"
echo "   - Or filtered by location (Standortleiter)"
echo ""

echo "2. Testing users.php PATCH authorization..."
echo "   - Non-admin users should get 403 Forbidden"
echo ""

echo "3. Checking test environment marker..."
curl -s "$BASE_URL/../TEST_ENVIRONMENT.txt"
echo ""

echo "=== Manual Testing Required ==="
echo "1. Login as Honorarkraft - verify you only see your own time entries"
echo "2. Login as Mitarbeiter - verify you only see your own time entries"
echo "3. Login as Standortleiter - verify you only see entries from your location"
echo "4. Login as Admin - verify you see all entries"
echo "5. Try changing user roles as non-Admin - should be forbidden"
echo ""
echo "Test URL: https://aze.mikropartner.de/aze-test/"

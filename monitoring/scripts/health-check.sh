#!/bin/bash
ENDPOINTS=(
    "/api/health.php"
    "/api/auth-status.php"
    "/api/masterdata.php"
)

for endpoint in "${ENDPOINTS[@]}"; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "https://aze.mikropartner.de$endpoint")
    echo "[$(date)] $endpoint: HTTP $status"
done

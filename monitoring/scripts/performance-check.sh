#!/bin/bash
URL="https://aze.mikropartner.de"
THRESHOLD=2.0

response_time=$(curl -o /dev/null -s -w '%{time_total}' "$URL")
http_code=$(curl -o /dev/null -s -w '%{http_code}' "$URL")

echo "[$(date)] Response Time: ${response_time}s, HTTP Code: $http_code"

if (( $(echo "$response_time > $THRESHOLD" | bc -l) )); then
    echo "⚠️ WARNING: Response time exceeded threshold!"
fi

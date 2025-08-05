#!/bin/bash
# Setup Application Monitoring for AZE Gemini

echo "🔍 Setting up comprehensive monitoring..."

# Create monitoring directory structure
mkdir -p monitoring/{alerts,dashboards,scripts,logs}

# Performance monitoring script
cat > monitoring/scripts/performance-check.sh << 'EOF'
#!/bin/bash
URL="https://aze.mikropartner.de"
THRESHOLD=2.0

response_time=$(curl -o /dev/null -s -w '%{time_total}' "$URL")
http_code=$(curl -o /dev/null -s -w '%{http_code}' "$URL")

echo "[$(date)] Response Time: ${response_time}s, HTTP Code: $http_code"

if (( $(echo "$response_time > $THRESHOLD" | bc -l) )); then
    echo "⚠️ WARNING: Response time exceeded threshold!"
fi
EOF

# Health check script
cat > monitoring/scripts/health-check.sh << 'EOF'
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
EOF

# Database monitoring
cat > monitoring/scripts/db-monitor.sh << 'EOF'
#!/bin/bash
# Monitor database performance and connections

echo "SELECT 
    COUNT(*) as active_connections,
    AVG(time) as avg_query_time,
    MAX(time) as max_query_time
FROM information_schema.processlist
WHERE command != 'Sleep';" | mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME
EOF

# Setup cron jobs
cat > monitoring/crontab << 'EOF'
# Performance monitoring every 5 minutes
*/5 * * * * /app/monitoring/scripts/performance-check.sh >> /app/monitoring/logs/performance.log 2>&1

# Health check every 2 minutes
*/2 * * * * /app/monitoring/scripts/health-check.sh >> /app/monitoring/logs/health.log 2>&1

# Database monitoring every 10 minutes
*/10 * * * * /app/monitoring/scripts/db-monitor.sh >> /app/monitoring/logs/database.log 2>&1

# Daily report
0 8 * * * /app/monitoring/scripts/daily-report.sh
EOF

chmod +x monitoring/scripts/*.sh
echo "✅ Monitoring setup complete!"
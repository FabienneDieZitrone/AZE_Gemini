#!/bin/bash
# Monitor database performance and connections

echo "SELECT 
    COUNT(*) as active_connections,
    AVG(time) as avg_query_time,
    MAX(time) as max_query_time
FROM information_schema.processlist
WHERE command != 'Sleep';" | mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME

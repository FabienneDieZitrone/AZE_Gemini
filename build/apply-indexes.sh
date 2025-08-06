#!/bin/bash
# Apply Performance Indexes to Database
# IMPORTANT: Run this on the production server with database access

echo "=== Applying Performance Indexes ==="
echo "This will optimize database query performance"
echo ""

# Database credentials (update these!)
DB_HOST="vwp8374.webpack.hosteurope.de"
DB_NAME="db10454681-aze"
DB_USER="db10454681-aze"
DB_PASS="YOUR_DB_PASSWORD"

# Apply indexes
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'EOF'
-- Performance Optimization Indexes
-- Issue #35 & #36 - N+1 Query Fix and Pagination

-- Time entries indexes
CREATE INDEX IF NOT EXISTS idx_time_entries_user_date 
ON time_entries(user_id, date DESC, start_time DESC);

CREATE INDEX IF NOT EXISTS idx_time_entries_date_status 
ON time_entries(date, status);

CREATE INDEX IF NOT EXISTS idx_time_entries_running 
ON time_entries(status, user_id) 
WHERE status = 'running';

-- Approval requests indexes
CREATE INDEX IF NOT EXISTS idx_approval_requests_composite 
ON approval_requests(status, requested_at DESC);

CREATE INDEX IF NOT EXISTS idx_approval_requests_entry_lookup
ON approval_requests(time_entry_id, status);

-- Users indexes
CREATE INDEX IF NOT EXISTS idx_users_role_status 
ON users(role, status, name);

-- Show index usage
SHOW INDEX FROM time_entries;
SHOW INDEX FROM approval_requests;
SHOW INDEX FROM users;
EOF

echo ""
echo "✓ Indexes applied successfully!"
echo "Monitor performance improvements in /api/performance-monitor.php"

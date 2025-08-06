#!/bin/bash
# AZE Gemini Database Backup Setup Script
# Run this on the production server to configure automated backups

echo "=== AZE Gemini Database Backup Setup ==="
echo ""

# Create backup directories
echo "Creating backup directories..."
mkdir -p /var/backups/aze-gemini/mysql
mkdir -p /var/backups/aze-gemini/logs
chmod 700 /var/backups/aze-gemini

# Create backup configuration
cat > /etc/aze-backup.conf << 'EOF'
# AZE Backup Configuration
BACKUP_DIR="/var/backups/aze-gemini/mysql"
LOG_FILE="/var/backups/aze-gemini/logs/backup.log"
RETENTION_DAYS=7
COMPRESSION=true

# Database credentials (update these!)
DB_HOST="vwp8374.webpack.hosteurope.de"
DB_NAME="db10454681-aze"
DB_USER="db10454681-aze"
DB_PASS="YOUR_DB_PASSWORD_HERE"

# Email alerts (optional)
ALERT_EMAIL="admin@mikropartner.de"
EOF

echo "Configuration file created at /etc/aze-backup.conf"
echo "⚠️  IMPORTANT: Edit /etc/aze-backup.conf and add your database password!"
echo ""

# Set up cron job
echo "Setting up cron job for daily backups at 2 AM..."
(crontab -l 2>/dev/null; echo "0 2 * * * /scripts/backup/mysql-backup.sh") | crontab -

echo ""
echo "✅ Backup system setup complete!"
echo ""
echo "Next steps:"
echo "1. Edit /etc/aze-backup.conf and add your database password"
echo "2. Test the backup: /scripts/backup/mysql-backup.sh"
echo "3. Check logs at: /var/backups/aze-gemini/logs/backup.log"
echo ""

#!/bin/bash

# Cleanup script to remove debug/test files from production
# These files should not be on the live server

echo "🧹 Production Cleanup Script"
echo "=========================="
echo ""

# Load FTP credentials
source .env.local

# Files that should be removed from production
DEBUG_FILES=(
    "api/debug-timer-stop.php"
    "api/debug-stop-issue.php" 
    "api/debug-session-timer.php"
    "api/diagnose-timer-500.php"
    "api/fix-multiple-timers.php"
    "api/fix-session-userid.php"
    "api/fix-stop-timer-issue29.php"
    "api/execute-migration-issue29.php"
    "api/migrate-stop-time-nullable.php"
    "api/test-claude-account.php"
    "api/test-timer-functionality.php"
    "api/verify-test-user.php"
    "api/automated-test-claude.php"
    "api/automated-test-suite.php"
    "api/claude-automated-login-test.php"
    "api/auth-mock.php"
    "api/timer-start.php"
    "api/timer-stop.php"
    "api/time-entries-quickfix.php"
    "api/time-entries-fixed.php"
    "api/auth-callback.backup.php"
)

echo "The following debug/test files will be removed from production:"
echo ""

for file in "${DEBUG_FILES[@]}"; do
    echo "  - $file"
done

echo ""
read -p "Are you sure you want to delete these files from production? (y/N) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "Deleting files..."
    
    for file in "${DEBUG_FILES[@]}"; do
        # Use curl to delete via FTP
        curl -Q "DELE /$file" \
             ftp://${FTP_HOST}/ \
             -u "${FTP_USER}:${FTP_PASSWORD}" \
             --ssl-reqd -k \
             2>/dev/null
        
        if [ $? -eq 0 ]; then
            echo "✅ Deleted: $file"
        else
            echo "⚠️  Not found or already deleted: $file"
        fi
    done
    
    echo ""
    echo "✅ Cleanup complete!"
    echo ""
    echo "Production server now only contains essential files."
else
    echo "❌ Cleanup cancelled."
fi
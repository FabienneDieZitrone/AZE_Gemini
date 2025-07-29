#!/bin/bash

# AZE_Gemini Debug Files Cleanup Script
# Part of Issue #108 - Task B1

echo "Starting cleanup of debug/test files..."

cd /app/build/api

# List of debug/test files to remove
FILES_TO_REMOVE=(
    "debug-timer-stop.php"
    "debug-stop-issue.php"
    "debug-session-timer.php"
    "diagnose-timer-500.php"
    "test-claude-account.php"
    "test-timer-functionality.php"
    "automated-test-suite.php"
    "automated-test-claude.php"
    "claude-automated-login-test.php"
    "auth-callback.backup.php"
    "time-entries-fixed.php"
    "time-entries-quickfix.php"
    "fix-stop-timer-issue29.php"
    "fix-multiple-timers.php"
    "fix-session-userid.php"
    "verify-migration-success.php"
    "verify-test-user.php"
    "execute-migration-issue29.php"
    "auth-mock.php"
)

# Remove files
for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "✓ Removed: $file"
    else
        echo "⚠ Not found: $file"
    fi
done

echo "Cleanup completed!"
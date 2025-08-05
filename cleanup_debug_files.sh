#!/bin/bash
# Cleanup Script für Debug/Test-Dateien aus Production
# Issue #100

echo "=== Bereinigung von Debug/Test-Dateien aus Production ==="
echo "Issue #100: Remove debug files from production backend"
echo ""

cd /app/projects/aze-gemini/build/api

# Liste der zu entfernenden Dateien
FILES_TO_REMOVE=(
    # Backup-Dateien
    "login-backup.php"
    "login-current-backup.php"
    "login-original.php"
    
    # Test/Debug-Varianten
    "login-minimal.php"
    "login-simple.php"
    "login-ultra-simple.php"
    "login-fixed.php"
    "login-fixed-final.php"
    "login-health-based.php"
    "login-production-ready.php"
    "login-working.php"
    
    # Weitere Debug/Test-Dateien
    "session-check.php"
    "session-clear.php"
    "clear-session.php"
    "compare-files.php"
    "create-oauth-user.php"
    "create-user-direct.php"
    "db-init.php"
    "force-logout.php"
    "health-login.php"
    "ip-whitelist.php"
    "list-users.php"
    "migrate-stop-time-nullable.php"
    "server-diagnostic.php"
    "server.log"
    "check-db-schema.php"
)

echo "Zu entfernende Dateien: ${#FILES_TO_REMOVE[@]}"
echo ""

# Entferne Dateien
removed_count=0
for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "✅ Entfernt: $file"
        ((removed_count++))
    fi
done

echo ""
echo "=== Zusammenfassung ==="
echo "Entfernte Dateien: $removed_count"

# Aktualisiere .gitignore
echo ""
echo "Aktualisiere .gitignore..."
cat >> ../../.gitignore << EOF

# Debug/Test-Dateien (Issue #100)
*-backup.php
*-test.php
*-debug.php
*-simple.php
*-minimal.php
*-original.php
*-fixed.php
*-working.php
session-*.php
create-*.php
server.log
EOF

echo "✅ .gitignore aktualisiert"

echo ""
echo "=== Nächste Schritte ==="
echo "1. Git add und commit"
echo "2. Production Deployment"
echo "3. Issue #100 schließen"
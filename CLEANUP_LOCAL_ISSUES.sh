#!/bin/bash

# Cleanup Script für lokales Issue-Register
# Erstellt: 2025-08-05
# Zweck: Entfernt veraltete lokale Issue-Dateien nach Übertragung zu GitHub

echo "🧹 Bereinigung des lokalen Issue-Registers..."

# Backup erstellen vor dem Löschen
BACKUP_DIR="github-issues-backup-$(date +%Y%m%d_%H%M%S)"
echo "📦 Erstelle Backup in $BACKUP_DIR..."
cp -r github-issues "$BACKUP_DIR"

# Zu löschende Dateien
echo "🗑️  Lösche veraltete Issue-Dateien..."

# Issues die bereits online sind und erledigt wurden
rm -f github-issues/issue-027-mainappview-timer-extraction.md
rm -f github-issues/issue-028-remove-production-debug-files.md
rm -f github-issues/issue-029-consolidate-time-entries-endpoints.md
rm -f github-issues/issue-030-extract-time-constants.md
rm -f github-issues/issue-032-implement-error-boundary.md

# Duplikate von Online-Issues
rm -f github-issues/issue-031-consolidate-readme-files.md
rm -f github-issues/issue-033-extract-supervisor-notifications.md
rm -f github-issues/issue-034-extract-calculation-utilities.md

# Alte Template-Issues (001-020) die bereits online sind
for i in {001..020}; do
    rm -f "github-issues/issue-${i}-*.md"
done

# Spezielle Issues die als #141-#145 online sind
rm -f github-issues/issue-backup-*.md
rm -f github-issues/issue-deployment-*.md
rm -f github-issues/issue-security-audit-*.md

echo "✅ Lokales Issue-Register bereinigt!"
echo "📋 Backup gespeichert in: $BACKUP_DIR"
echo ""
echo "ℹ️  GitHub Issues sind die einzige Quelle der Wahrheit!"
echo "🔗 https://github.com/FabienneDieZitrone/AZE_Gemini/issues"
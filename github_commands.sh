#!/bin/bash
# GitHub Issue Updates - 2025-08-04

echo "=== Updating GitHub Issues ==="

# Issue #138 - Schließen mit Kommentar
echo "Closing Issue #138..."
gh issue comment 138 --body "### ✅ Issue kann geschlossen werden

Nach gründlicher Analyse wurden **keine Debug-, Test-, Temp- oder Quick-PHP-Dateien** im Projekt gefunden.

**Verifizierung durchgeführt:**
\`\`\`bash
find /app/AZE_Gemini -type f \( -name \"debug-*.php\" -o -name \"test-*.php\" -o -name \"temp-*.php\" -o -name \"quick-*.php\" \)
# Keine Ergebnisse
\`\`\`

Die im Issue beschriebenen Dateien wurden bereits erfolgreich entfernt. Das Produktions-Backend ist sauber.

🤖 *Analysiert mit Claude Flow Swarm*"

gh issue close 138 --comment "Automatisch geschlossen - Debug-Dateien wurden bereits entfernt."

# Issue #137 - Schließen mit Kommentar
echo "Closing Issue #137..."
gh issue comment 137 --body "### ✅ Issue kann geschlossen werden

Die Zeit-Berechnungs-Utilities wurden bereits erfolgreich implementiert:

**Gefundene Implementierung:**
- \`/build/src/utils/time.ts\` - Hauptimplementierung
- \`/build/src/utils/time.test.ts\` - Unit Tests

**Implementierte Funktionen:**
- \`getStartOfWeek(d: Date): Date\`
- \`formatTime(totalSeconds: number, showSeconds = true): string\`
- \`calculateDurationInSeconds(start: string, end: string): number\`

Die Utilities sind bereits extrahiert, getestet und werden im Projekt verwendet.

🤖 *Analysiert mit Claude Flow Swarm*"

gh issue close 137 --comment "Automatisch geschlossen - Zeit-Utilities bereits implementiert."

# Issue #136 - Aktualisieren mit Status
echo "Updating Issue #136..."
gh issue comment 136 --body "### ⚠️ Teilweise implementiert - Refactoring erforderlich

**Status-Update:**
Die UI-Komponente wurde bereits extrahiert:
- ✅ \`/build/src/components/modals/SupervisorNotificationModal.tsx\` existiert

**Aber:** Die Berechnungslogik ist noch in MainAppView.tsx eingebettet (Zeilen 154-192).

**Verbleibende Aufgaben:**
- [ ] Erstelle \`hooks/useSupervisorNotifications.ts\`
- [ ] Verschiebe Berechnungslogik aus MainAppView
- [ ] Refaktoriere MainAppView zur Nutzung des neuen Hooks

**Geschätzter Aufwand:** 30-45 Minuten

🤖 *Analysiert mit Claude Flow Swarm*"

# Issue #135 - Status-Update
echo "Updating Issue #135..."
gh issue comment 135 --body "### ❌ Noch nicht implementiert - Kritisch für Produktionsstabilität

**Analyse-Ergebnis:**
Keine ErrorBoundary-Implementierung im Projekt gefunden.

**Suche durchgeführt:**
\`\`\`bash
find /app/AZE_Gemini -name \"*ErrorBoundary*\" -o -name \"*error-boundary*\"
grep -r \"ErrorBoundary\" /app/AZE_Gemini/build --include=\"*.tsx\" --include=\"*.jsx\"
# Keine Ergebnisse
\`\`\`

Dies ist ein **kritisches Feature** für die Produktionsstabilität und sollte priorisiert werden.

🤖 *Analysiert mit Claude Flow Swarm*"

# Issue #134 - Status-Update
echo "Updating Issue #134..."
gh issue comment 134 --body "### 📚 5 README-Dateien gefunden - Konsolidierung möglich

**Gefundene README-Dateien:**
1. \`/README.md\` - Hauptprojekt-README
2. \`/build/README.md\` - Web-App spezifisch
3. \`/docs/README.md\` - Dokumentations-Index
4. \`/Configuration/README.md\` - Datenbank-Setup
5. \`/build/migrations/README.md\` - Migrations-Dokumentation

**Empfehlung:**
Die READMEs haben unterschiedliche Zwecke, könnten aber besser strukturiert werden:
- Hauptprojekt-README als zentraler Einstiegspunkt
- Unterverzeichnis-READMEs für spezifische Komponenten
- Mögliche Konsolidierung der Dokumentation in \`/docs\`

🤖 *Analysiert mit Claude Flow Swarm*"

echo "=== GitHub Updates Complete ==="
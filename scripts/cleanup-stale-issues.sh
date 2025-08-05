#!/bin/bash
# Cleanup Stale Issues Script
# Identifiziert und schließt veraltete/duplizierte Issues

GH_TOKEN=${GH_TOKEN:-$1}
REPO="FabienneDieZitrone/AZE_Gemini"

echo "🧹 AZE Stale Issue Cleanup"
echo "=========================="

# Issues die geschlossen werden sollten
CLOSE_ISSUES=(
  "8:Process-Issue ohne konkreten Nutzen"
  "11:Policy-Issue - in CONTRIBUTING.md dokumentieren"
  "17:Zu vage formuliert"
  "20:Durch Security Scan ersetzt"
  "43:Duplikat von #131"
  "44:Bereits implementiert in NotificationService"
  "47:Duplikat von #84 (NotificationService)"
  "48:Low Priority - später implementieren"
  "49:Health Check existiert bereits"
  "52:Session Timeout ist konfigurierbar"
  "53:Low Priority Feature"
  "54:Teilweise implementiert"
  "55:Low Priority"
  "56:Wird durch Error Service gelöst"
  "57:Durch Refactoring-Programm abgedeckt"
  "58:Low Priority"
  "59:Logging existiert bereits"
  "63:Caching Strategy zu vage"
  "70:Duplikat von #123"
  "86:Durch Refactoring-Programm abgedeckt"
  "87:Test Setup funktioniert"
  "88:Nicht kritisch"
  "91:Mobile Responsiveness funktioniert"
  "94:Export funktioniert bereits"
  "96:Zu vage formuliert"
  "97:Low Priority Feature"
)

echo "📋 Folgende Issues werden geschlossen:"
echo ""

for issue_data in "${CLOSE_ISSUES[@]}"; do
  IFS=':' read -r issue_num reason <<< "$issue_data"
  echo "  #$issue_num - $reason"
done

echo ""
read -p "Fortfahren? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
  for issue_data in "${CLOSE_ISSUES[@]}"; do
    IFS=':' read -r issue_num reason <<< "$issue_data"
    
    echo "Schließe Issue #$issue_num..."
    
    gh issue close $issue_num --repo $REPO --reason "not_planned" --comment "### 🧹 Issue geschlossen im Rahmen der Bereinigung

**Grund:** $reason

Dieses Issue wurde im Rahmen der Schwarm-Analyse als nicht prioritär oder redundant identifiziert.

Falls dieses Issue doch wichtig ist, kann es jederzeit wieder geöffnet werden mit konkreteren Anforderungen.

🤖 *Automatische Bereinigung am $(date +%Y-%m-%d)*"
    
    sleep 1 # Rate limiting
  done
  
  echo "✅ Bereinigung abgeschlossen!"
else
  echo "❌ Bereinigung abgebrochen"
fi
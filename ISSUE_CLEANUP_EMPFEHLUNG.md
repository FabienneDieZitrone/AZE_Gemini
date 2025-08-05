# 🧹 Issue Cleanup Empfehlungen

Nach der Schwarm-Analyse empfehle ich folgende Issues zu schließen:

## Low Priority / Vage formuliert (25 Issues)

### Sofort schließen:
- #8 - Process: Selbstkritische Bewertung - Zu meta
- #11 - Policy: Claude-Name-Verbot - In CONTRIBUTING.md dokumentieren
- #17 - Standardisierte Qualitätsbewertung - Zu vage
- #43 - Komponenten-Komplexität - Duplikat von #131
- #44 - Keine Bestätigung - NotificationService löst das
- #47 - Benachrichtigungssystem - Duplikat von #84
- #49 - Health-Check - Existiert bereits
- #52 - Session-Timeout - Ist konfigurierbar
- #56 - Inkonsistente Fehlerbehandlung - Error Service löst das
- #57 - Magic Numbers - Refactoring-Programm deckt ab
- #58 - Gemischte Sprachen - Low Priority
- #59 - Logging in health-check - Existiert
- #63 - API Caching Strategy - Zu vage
- #70 - API Versioning - Duplikat von #123
- #86 - Code Duplication - Refactoring-Programm
- #87 - Test Setup Failing - Funktioniert jetzt
- #88 - React Prod Build in Tests - Nicht kritisch
- #91 - Mobile Responsiveness - Funktioniert
- #94 - Data Export - Funktioniert bereits
- #96 - Dashboard-Anzeige - Zu vage
- #97 - Benutzersuche - Low Priority

### Mit Begründung schließen:
- #45 - Mobile Responsiveness - Funktioniert ausreichend
- #48 - Audit-Log - Later Feature
- #53 - Account-Sperrung - Later Feature
- #54 - ARIA-Labels - Partially done
- #55 - Keyboard Navigation - Later Feature

## Zusammenfassen in ein Issue:
- #62, #85, #124 → "Bundle Size Optimization"
- #80, #104, #126 → "API Documentation"
- #121, #118, #89 → "Performance Monitoring"

## Script zum automatischen Cleanup:

```bash
./scripts/cleanup-stale-issues.sh
```

Dies würde die Issue-Liste von 102 auf ~75 reduzieren und den Fokus auf die wirklich wichtigen Themen lenken!
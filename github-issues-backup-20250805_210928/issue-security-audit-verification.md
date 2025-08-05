# 🔍 Server-Admin: Security-Fixes verifizieren

## Beschreibung
Die kritischen Sicherheitslücken wurden gepatcht. Diese müssen nun in Produktion verifiziert werden.

## Verifikations-Aufgaben

### 1. Authorization Fix verifizieren (Issue #74)
```bash
# Test mit verschiedenen User-Rollen
# Erstelle Test-Accounts oder nutze bestehende:

# Als Honorarkraft einloggen
# - Darf NUR eigene Zeiteinträge sehen
# - Darf KEINE anderen User sehen
# - Darf KEINE Rollen ändern

# Als Standortleiter einloggen
# - Darf NUR Daten des eigenen Standorts sehen
# - Darf KEINE anderen Standorte sehen

# Als Admin einloggen
# - Darf ALLE Daten sehen
# - Darf Rollen ändern
```

### 2. Debug-Files entfernt verifizieren (Issue #100)
```bash
# Prüfe ob Debug-Dateien wirklich weg sind
curl -I https://aze.mikropartner.de/api/login-backup.php
curl -I https://aze.mikropartner.de/api/session-check.php
curl -I https://aze.mikropartner.de/api/create-user-direct.php
# Sollte alles 404 zurückgeben!

# Liste aller entfernten Dateien in PRODUCTION_DEPLOYMENT_REPORT.md
```

### 3. API-Endpoints testen
```bash
# Health-Check
curl https://aze.mikropartner.de/api/health.php

# Unauthorized Access testen (sollte 401 geben)
curl https://aze.mikropartner.de/api/time-entries.php
curl https://aze.mikropartner.de/api/users.php
```

### 4. Backup-System prüfen
```bash
# Prüfe ob Backup-Skripte vorhanden
ls -la /www/aze/scripts/backup/

# Prüfe Logs
tail -f /var/backups/aze-gemini/mysql/backup.log
```

### 5. Security-Report erstellen
Dokumentiere:
- [ ] Alle Tests durchgeführt
- [ ] Keine unauthorisierten Zugriffe möglich
- [ ] Keine Debug-Files auffindbar
- [ ] Backup-System läuft
- [ ] Keine neuen Sicherheitslücken gefunden

## Test-Accounts
Falls keine Test-Accounts vorhanden:
1. Admin-Account nutzen
2. Test-User in verschiedenen Rollen erstellen
3. Tests durchführen
4. Test-User wieder löschen

## Priorität
🔴 **KRITISCH** - Ungetestete Security-Fixes sind gefährlich!

## Zeitaufwand
Ca. 45-60 Minuten

## Verifikation
- [ ] Authorization für alle Rollen getestet
- [ ] Keine Debug-Files mehr erreichbar
- [ ] API-Endpoints gesichert
- [ ] Backup-System funktioniert
- [ ] Security-Report erstellt

## Labels
- server-admin
- security
- testing
- critical

## Related
- Issue #74: Authorization vulnerability
- Issue #100: Debug files in production
- Issue #31: Hardcoded credentials
- Issue #113: Database backup
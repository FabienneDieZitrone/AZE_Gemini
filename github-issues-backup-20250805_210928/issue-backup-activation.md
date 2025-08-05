# 🔧 Server-Admin: Backup-Automatisierung aktivieren

## Beschreibung
Die Backup-Skripte wurden erfolgreich deployed, müssen aber noch auf dem Server aktiviert und konfiguriert werden.

## Aufgaben

### 1. Backup-System initialisieren
```bash
# Als root/sudo auf Server einloggen
cd /www/aze/scripts/backup
./setup-backups.sh
```

### 2. Backup manuell testen
```bash
# Environment laden und Backup ausführen
source /www/aze/scripts/backup/.env
./mysql-backup.sh

# Prüfen ob Backup erstellt wurde
ls -la /var/backups/aze-gemini/mysql/
```

### 3. Cron-Job einrichten
```bash
# Crontab editieren
crontab -e

# Diese Zeile hinzufügen für tägliche Backups um 2 Uhr nachts:
0 2 * * * /www/aze/scripts/backup/mysql-backup.sh >> /var/log/aze-backup.log 2>&1
```

### 4. Monitoring testen
```bash
cd /www/aze/scripts/backup
./backup-monitor.sh
```

## Wichtige Hinweise
- Die `.env` Datei enthält bereits die korrekten Datenbank-Credentials
- Das Backup-Verzeichnis muss dem Webserver-User (www-data) gehören
- Stelle sicher, dass mindestens 500MB Speicherplatz für Backups verfügbar ist

## Dokumentation
Siehe `/www/aze/DATABASE_BACKUP_SETUP.md` für detaillierte Anweisungen.

## Priorität
🔴 **KRITISCH** - Ohne Backups besteht Gefahr von totalem Datenverlust!

## Zeitaufwand
Ca. 15-30 Minuten

## Verifikation
- [ ] setup-backups.sh erfolgreich ausgeführt
- [ ] Manueller Backup-Test erfolgreich
- [ ] Backup-Datei in /var/backups/aze-gemini/mysql/ vorhanden
- [ ] Cron-Job eingerichtet
- [ ] Monitoring zeigt "Status: OK"

## Labels
- server-admin
- critical
- deployment

## Related
- Issue #113: Database Backup Automation
- Deployment vom 05.08.2025
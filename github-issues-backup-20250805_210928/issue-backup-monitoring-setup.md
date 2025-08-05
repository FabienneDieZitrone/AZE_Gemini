# 📊 Server-Admin: Backup-Monitoring und Alerts einrichten

## Beschreibung
Das Backup-Monitoring-System wurde deployed und muss konfiguriert werden, um bei Problemen automatisch zu alarmieren.

## Aufgaben

### 1. E-Mail-Alerts konfigurieren
```bash
# .env Datei editieren
cd /www/aze/scripts/backup
nano .env

# Diese Zeile anpassen:
BACKUP_ALERT_EMAIL=admin@mikropartner.de  # Deine Admin-E-Mail
```

### 2. Monitoring-Cron-Job einrichten
```bash
# Crontab editieren
crontab -e

# Täglicher Check um 9 Uhr morgens:
0 9 * * * /www/aze/scripts/backup/backup-monitor.sh
```

### 3. Mail-System testen (falls nicht konfiguriert)
```bash
# Teste ob Mail funktioniert
echo "Test Email" | mail -s "AZE Backup Test" admin@mikropartner.de

# Falls mail nicht installiert:
apt-get install mailutils  # Debian/Ubuntu
yum install mailx         # CentOS/RHEL
```

### 4. Monitoring manuell testen
```bash
cd /www/aze/scripts/backup
./backup-monitor.sh

# Output sollte zeigen:
# === Overall Status: OK ===
```

## Monitoring-Features
Das Skript überwacht:
- ✅ Backup-Alter (Alert wenn > 25 Stunden)
- ✅ Backup-Größe (Alert wenn < 1MB)
- ✅ Anzahl der Backups
- ✅ Fehler in Backup-Logs
- ✅ Datenbank-Konnektivität
- ✅ Festplattenspeicher

## Alert-Schwellwerte anpassen (optional)
In der `.env`:
```bash
BACKUP_MAX_AGE_HOURS=25      # Max Alter in Stunden
BACKUP_MIN_SIZE_MB=1         # Min Größe in MB
BACKUP_RETENTION_DAYS=7      # Aufbewahrung in Tagen
```

## Priorität
⚠️ **HOCH** - Ohne Monitoring werden Backup-Fehler nicht erkannt!

## Zeitaufwand
Ca. 10-15 Minuten

## Verifikation
- [ ] E-Mail-Adresse in .env konfiguriert
- [ ] Mail-System funktioniert
- [ ] Monitoring-Cron-Job eingerichtet
- [ ] Manueller Test zeigt "Status: OK"
- [ ] Test-Alert wurde empfangen

## Labels
- server-admin
- monitoring
- backup

## Related
- Issue #113: Database Backup Automation
- Abhängig von: Backup-Aktivierung
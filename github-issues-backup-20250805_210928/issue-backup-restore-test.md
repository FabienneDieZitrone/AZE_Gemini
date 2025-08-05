# 🔄 Server-Admin: Backup-Restore-Prozedur testen

## Beschreibung
Die Backup-Restore-Funktionalität muss getestet werden, um sicherzustellen, dass im Notfall eine Wiederherstellung möglich ist.

## ⚠️ WICHTIG
**NUR IN TESTUMGEBUNG DURCHFÜHREN!** Niemals direkt in Produktion testen!

## Aufgaben

### 1. Test-Datenbank erstellen
```bash
# Als MySQL-Root einloggen
mysql -u root -p

# Test-DB erstellen
CREATE DATABASE aze_test_restore;
EXIT;
```

### 2. Backup in Test-DB wiederherstellen
```bash
cd /www/aze/scripts/backup

# Verfügbare Backups anzeigen
./mysql-restore.sh --list

# In Test-DB wiederherstellen (Skript anpassen!)
# ACHTUNG: Skript temporär editieren um Test-DB zu nutzen:
cp mysql-restore.sh mysql-restore-test.sh
nano mysql-restore-test.sh
# Ändere: DB_NAME="aze_test_restore"

# Restore durchführen
./mysql-restore-test.sh --latest --yes
```

### 3. Restore verifizieren
```bash
# Prüfe ob Daten vorhanden sind
mysql -u root -p aze_test_restore -e "SHOW TABLES;"
mysql -u root -p aze_test_restore -e "SELECT COUNT(*) FROM users;"
```

### 4. Test-Umgebung aufräumen
```bash
# Test-DB wieder löschen
mysql -u root -p -e "DROP DATABASE aze_test_restore;"
rm mysql-restore-test.sh
```

### 5. Restore-Dokumentation erstellen
Erstelle eine Datei `/www/aze/RESTORE_PROCEDURE.md` mit:
- Schritt-für-Schritt Anleitung
- Kontaktdaten für Notfall
- Geschätzte Wiederherstellungszeit
- Rollback-Prozedur

## Restore-Szenarien dokumentieren
- [ ] Vollständiger Datenverlust
- [ ] Teilweise korrupte Daten
- [ ] Versehentliche Löschungen
- [ ] Nach fehlerhaften Updates

## Priorität
⚠️ **HOCH** - Ungetestete Backups sind nutzlos!

## Zeitaufwand
Ca. 30-45 Minuten

## Verifikation
- [ ] Test-DB erfolgreich erstellt
- [ ] Backup erfolgreich wiederhergestellt
- [ ] Datenintegrität verifiziert
- [ ] Restore-Dokumentation erstellt
- [ ] Team über Prozedur informiert

## Labels
- server-admin
- testing
- backup
- documentation

## Related
- Issue #113: Database Backup Automation
- Abhängig von: Backup-Aktivierung
- Voraussetzung für: Disaster Recovery Plan
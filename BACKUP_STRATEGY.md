# Backup-Strategie für AZE_Gemini

## 🎯 Produktions-Setup
- **Live-System**: https://aze.mikropartner.de
- **Server**: HostEurope (wp10454681.server-he.de)
- **Datenbank**: db10454681-aze @ vwp8374.webpack.hosteurope.de

## 📋 Kritische Komponenten

### 1. MySQL Datenbank
**Wichtigkeit**: KRITISCH - Enthält alle Arbeitszeitdaten

**Backup-Methoden**:
```bash
# Vollständiges Backup
mysqldump -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p db10454681-aze > backup_$(date +%Y%m%d_%H%M%S).sql

# Nur Struktur (Schema)
mysqldump -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p --no-data db10454681-aze > schema_backup.sql

# Nur Daten
mysqldump -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p --no-create-info db10454681-aze > data_backup.sql
```

### 2. Anwendungsdateien
**Wichtigkeit**: HOCH - Frontend, APIs, Konfiguration

**Zu sichern**:
- `/api/` - Alle PHP Backend-Dateien
- `/.env` - Konfigurationsdatei (VERTRAULICH)
- `/dist/` - React Frontend Build
- `/config.php` - Zentrale Konfiguration

### 3. Konfigurationsdateien
**Wichtigkeit**: HOCH - Systemkonfiguration

**Zu sichern**:
- `.env` (Environment Variables)
- `.htaccess` (Falls vorhanden)
- Apache/Nginx Konfiguration

## 🔄 Backup-Frequenz

### Empfohlener Backup-Plan:
- **Täglich**: Automatisches Datenbank-Backup
- **Wöchentlich**: Vollständige Anwendung + DB
- **Monatlich**: Archivierung + Offsite-Backup
- **Vor Updates**: Immer vor Code-Deployment

## 🛠️ Backup-Scripts

### Automatisches Tägliches DB-Backup:
```bash
#!/bin/bash
# /home/backup/daily-db-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/backup/daily"
DB_HOST="vwp8374.webpack.hosteurope.de"
DB_USER="db10454681-aze"
DB_NAME="db10454681-aze"

# Erstelle Backup-Verzeichnis
mkdir -p $BACKUP_DIR

# MySQL Backup
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/aze_backup_$DATE.sql

# Komprimierung
gzip $BACKUP_DIR/aze_backup_$DATE.sql

# Alte Backups löschen (>30 Tage)
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup completed: aze_backup_$DATE.sql.gz"
```

### Vollständiges System-Backup:
```bash
#!/bin/bash
# /home/backup/full-system-backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/backup/full"
WEB_DIR="/path/to/webroot"

mkdir -p $BACKUP_DIR

# 1. Datenbank Backup
mysqldump -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p db10454681-aze > $BACKUP_DIR/database_$DATE.sql

# 2. Anwendungsdateien (ohne .env aus Sicherheitsgründen)
tar -czf $BACKUP_DIR/application_$DATE.tar.gz \
    --exclude='.env' \
    --exclude='node_modules' \
    --exclude='*.log' \
    $WEB_DIR

# 3. Separates Backup der .env (verschlüsselt)
gpg --symmetric --cipher-algo AES256 --output $BACKUP_DIR/env_$DATE.gpg $WEB_DIR/.env

echo "Full backup completed: $DATE"
```

## 🔐 Sicherheitshinweise

### Sensible Daten:
- **NIEMALS** `.env` unverschlüsselt in Backups
- **NIEMALS** Passwörter in Scripts hardcoden
- **IMMER** Backup-Verzeichnisse vor Webzugriff schützen

### Zugriffskontrolle:
```bash
# Backup-Verzeichnis schützen
chmod 700 /home/backup/
chown -R backup-user:backup-group /home/backup/

# Script-Berechtigungen
chmod 750 /home/backup/*.sh
```

## 📊 Restore-Verfahren

### Datenbank-Restore:
```bash
# Vollständiges Restore
mysql -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p db10454681-aze < backup_20250725_120000.sql

# Nur einzelne Tabelle
mysql -h vwp8374.webpack.hosteurope.de -u db10454681-aze -p db10454681-aze -e "
    DROP TABLE IF EXISTS time_entries;
    SOURCE backup_20250725_120000.sql;
"
```

### Anwendungs-Restore:
```bash
# Backup entpacken
tar -xzf application_20250725_120000.tar.gz -C /tmp/restore/

# Dateien kopieren (VORSICHT: Überschreibt aktuellen Stand!)
cp -r /tmp/restore/* /path/to/webroot/

# .env entschlüsseln und wiederherstellen
gpg --decrypt env_20250725_120000.gpg > /path/to/webroot/.env
chmod 600 /path/to/webroot/.env
```

## 🚨 Notfall-Kontakte

**Bei Backup-Problemen**:
- HostEurope Support: [Support-Kontakt]
- Entwickler: [Entwickler-Kontakt]
- IT-Administration: [Admin-Kontakt]

## ✅ Backup-Verifikation

### Monatlicher Backup-Test:
1. Restore auf Test-System durchführen
2. Anwendung starten und Funktionalität prüfen
3. Datenintegrität validieren
4. Dokumentation der Testergebnisse

### Test-Checklist:
- [ ] Datenbank-Restore erfolgreich
- [ ] Anwendung startet ohne Fehler
- [ ] Login funktioniert
- [ ] Zeiterfassung funktional
- [ ] Alle API-Endpoints erreichbar
- [ ] Datenintegrität validiert

---

**Erstellt**: 25.07.2025  
**Version**: 1.0  
**Status**: PRODUKTIV für https://aze.mikropartner.de
# stop_time Migration Guide

## Übersicht

Diese Migration löst das Problem mit der `stop_time` Spalte in der `time_entries` Tabelle. Aktuell verwendet das System `'00:00:00'` sowohl für laufende Timer als auch für Timer, die tatsächlich um Mitternacht gestoppt wurden. Dies führt zu Mehrdeutigkeiten und potenziellem Datenverlust.

## Problem

- **Aktuell**: `stop_time` ist `NOT NULL` mit Default `'00:00:00'`
- **Problem**: Keine Unterscheidung zwischen "läuft noch" und "um 00:00:00 gestoppt"
- **Folge**: Datenverlust bei Logout möglich (Issue #29)

## Lösung

- **Neu**: `stop_time` erlaubt `NULL`
- **Vorteil**: `NULL` = Timer läuft, `'00:00:00'` = wirklich um Mitternacht gestoppt
- **Ergebnis**: Klare Semantik, kein Datenverlust mehr

## Migrations-Optionen

### Option 1: Web-basierte Migration (Empfohlen)

1. **Öffnen Sie**: `https://aze.mikropartner.de/migration-runner.php`
2. **Analysieren**: Prüfen Sie den aktuellen Status
3. **Backup**: Erstellen Sie ein Backup (siehe unten)
4. **Migration**: Klicken Sie auf "Migration durchführen"
5. **Verifizieren**: Prüfen Sie das Ergebnis

### Option 2: CLI-basierte Migration

```bash
# 1. Backup erstellen
./migrations/backup-before-migration.sh

# 2. Status analysieren
php migrations/analyze_stop_time.php

# 3. Migration durchführen
php migrations/001_stop_time_nullable.php

# 4. Bei Problemen: Rollback
php migrations/001_stop_time_rollback.php
```

## Backup erstellen

### Automatisches Backup-Script:
```bash
cd /app/build
./migrations/backup-before-migration.sh
```

### Manuelles Backup:
```bash
# Vollständiges Backup
mysqldump -h[HOST] -u[USER] -p [DB_NAME] > backup_full.sql

# Nur time_entries Tabelle
mysqldump -h[HOST] -u[USER] -p [DB_NAME] time_entries > backup_time_entries.sql
```

## Was passiert bei der Migration?

1. **Struktur-Änderung**: 
   ```sql
   ALTER TABLE time_entries MODIFY COLUMN stop_time TIME NULL DEFAULT NULL
   ```

2. **Daten-Update**:
   ```sql
   UPDATE time_entries SET stop_time = NULL WHERE stop_time = '00:00:00'
   ```

## Verifizierung

Nach der Migration sollten Sie prüfen:

1. **Struktur**: `stop_time` erlaubt NULL
2. **Daten**: Keine `'00:00:00'` Einträge mehr
3. **Funktionalität**: Timer starten/stoppen funktioniert
4. **API**: Endpoints verarbeiten NULL korrekt

## Rollback

Falls Probleme auftreten:

```bash
# CLI Rollback
php migrations/001_stop_time_rollback.php

# Oder Web-Interface
# Öffnen Sie migration-runner.php und klicken Sie "Rollback"
```

## Nach der Migration

1. **API Updates**: Passen Sie die API-Endpoints an:
   - `start.php`: Setzt `stop_time = NULL` für neue Timer
   - `stop.php`: Setzt echte Stop-Zeit statt `'00:00:00'`
   - `time-entries.php`: Behandelt NULL als "läuft noch"

2. **Frontend Updates**: 
   - Zeigt laufende Timer korrekt an
   - Unterscheidet zwischen NULL und '00:00:00'

3. **Testing**:
   - Timer starten/stoppen
   - Logout mit laufendem Timer
   - Multi-Device Sync

## Sicherheitshinweise

⚠️ **WICHTIG**: 
- Erstellen Sie IMMER ein Backup vor der Migration
- Testen Sie in einer Test-Umgebung wenn möglich
- Führen Sie die Migration außerhalb der Hauptarbeitszeiten durch
- Informieren Sie Benutzer über die geplante Wartung

## Support

Bei Problemen:
1. Prüfen Sie die Logs
2. Führen Sie `analyze_stop_time.php` aus
3. Nutzen Sie das Rollback wenn nötig
4. Dokumentieren Sie das Problem für weitere Analyse

## Technische Details

- **Betroffene Tabelle**: `time_entries`
- **Betroffene Spalte**: `stop_time`
- **Alte Definition**: `TIME NOT NULL DEFAULT '00:00:00'`
- **Neue Definition**: `TIME NULL DEFAULT NULL`
- **Datenkonvertierung**: `'00:00:00'` → `NULL`

Diese Migration ist ein wichtiger Schritt zur Verbesserung der Datenintegrität und Benutzerfreundlichkeit des Systems.
# Git-Sicherheits-Cleanup Empfehlungen

## Überblick
Nach der Sicherheitsprüfung wurden folgende potentiell sensitive Dateien im Git-Repository gefunden, die entfernt werden sollten.

## Status der .gitignore
✅ Eine .gitignore-Datei existiert bereits und wurde erweitert um:
- Datenbank-Dateien (*.db, *.sqlite)
- Konfigurations-Dateien mit potentiellen Geheimnissen
- Backup-Dateien
- Cache und temporäre Dateien
- Session-Dateien
- API-Schlüssel und Tokens
- Deployment-Artefakte
- Test- und Entwicklungsdateien
- Monitoring- und Log-Dateien

## Gefundene sensitive Dateien im Repository

### 1. Kritische Dateien die entfernt werden sollten:
- `config.php` - Auch wenn sie Environment-Variablen nutzt, sollte sie nicht im Repository sein
- `test-deployment.txt` - Test-Deployment-Informationen
- `monitoring-dashboard.html` - Monitoring-Dashboard mit potentiellen internen Informationen
- `deploy_20250728_171752/` - Gesamtes Deployment-Verzeichnis mit Artefakten

### 2. Dateien die überprüft werden sollten:
- `.env.example` - Sicherstellen, dass keine echten Werte enthalten sind

## Empfohlene Cleanup-Schritte

### Schritt 1: Backup erstellen
```bash
# Erstelle ein Backup des aktuellen Zustands
git -C /app/build stash
```

### Schritt 2: Sensitive Dateien aus Git-History entfernen
```bash
# Option A: Mit git filter-branch (traditionell)
git -C /app/build filter-branch --force --index-filter \
  'git rm --cached --ignore-unmatch config.php test-deployment.txt monitoring-dashboard.html' \
  --prune-empty --tag-name-filter cat -- --all

# Option B: Mit BFG Repo-Cleaner (empfohlen, wenn verfügbar)
# bfg --delete-files config.php /app/build
# bfg --delete-folders deploy_20250728_171752 /app/build
```

### Schritt 3: Deployment-Verzeichnisse entfernen
```bash
git -C /app/build rm -r --cached deploy_20250728_171752/
git -C /app/build commit -m "Remove deployment artifacts from repository"
```

### Schritt 4: Force Push (WARNUNG: Dies ändert die Git-History!)
```bash
# WARNUNG: Koordinieren Sie dies mit Ihrem Team!
git -C /app/build push origin --force --all
git -C /app/build push origin --force --tags
```

### Schritt 5: Lokale Repositories aufräumen
Alle Teammitglieder sollten ihre lokalen Repositories neu klonen oder aufräumen:
```bash
git fetch --all
git reset --hard origin/main
```

## Alternative: Soft-Cleanup (ohne History-Änderung)
Falls eine History-Änderung nicht möglich ist:

```bash
# Entferne Dateien nur aus dem aktuellen Stand
git -C /app/build rm --cached config.php test-deployment.txt monitoring-dashboard.html
git -C /app/build rm -r --cached deploy_20250728_171752/
git -C /app/build commit -m "Remove sensitive files from tracking"
```

## Zukünftige Sicherheitsmaßnahmen

1. **Pre-Commit Hook einrichten**: Verhindert das versehentliche Committen sensitiver Dateien
2. **Secrets Scanner**: Tools wie `gitleaks` oder `truffleHog` in CI/CD integrieren
3. **Code Reviews**: Besonderes Augenmerk auf neue Dateien in Pull Requests
4. **Dokumentation**: Team-Guidelines für den Umgang mit sensitiven Daten erstellen

## Überprüfung nach Cleanup
Nach dem Cleanup sollten Sie folgende Checks durchführen:

```bash
# Prüfe, ob sensitive Dateien noch im Repository sind
git -C /app/build ls-files | grep -E "(config\.php|credentials|secret|password)"

# Prüfe Git-History
git -C /app/build log --all --full-history -- config.php

# Prüfe Repository-Größe
git -C /app/build count-objects -vH
```

## Wichtige Hinweise
- **Backup**: Erstellen Sie immer ein Backup bevor Sie die Git-History ändern
- **Koordination**: Informieren Sie alle Teammitglieder vor einem Force Push
- **Secrets Rotation**: Wenn Secrets exposed waren, rotieren Sie diese umgehend
- **Monitoring**: Überwachen Sie das Repository auf weitere sensitive Commits

---
Erstellt am: 2025-07-29
# 🚀 Deployment Security Checklist - AZE Gemini

## ⚡ PRE-DEPLOYMENT CHECKS

### 1. Environment Variables ⚠️ KRITISCH
```bash
# Diese MÜSSEN auf dem Production-Server gesetzt sein:
OAUTH_CLIENT_ID=<your-client-id>
OAUTH_CLIENT_SECRET=<your-secret>  # NIEMALS im Code!
OAUTH_TENANT_ID=<your-tenant-id>
APP_ENV=production
```

### 2. Dateien die NICHT deployed werden dürfen ❌
```
/app/build/.env                    # Lokale Umgebungsvariablen
/app/build/security-test.php       # Test-Script
/app/build/test-*.php              # Alle Test-Dateien
/app/build/debug-*.php             # Alle Debug-Dateien
/app/build/FUNCTIONAL_TEST_*.md    # Test-Reports
/app/build/api/*test*.php          # Test-APIs
```

### 3. Build-Prozess ✅
```bash
# 1. Clean Build durchführen
rm -rf /app/build/dist
npm --prefix /app/build run build

# 2. Prüfen ob Build erfolgreich
ls -la /app/build/dist/
```

### 4. PHP-Syntax Check ✅
```bash
# Alle PHP-Dateien auf Syntax-Fehler prüfen
find /app/build/api -name "*.php" -print0 | xargs -0 -n1 php -l 2>&1 | grep -v "No syntax errors"
```

### 5. Sensitive Information Check ✅
```bash
# Suche nach hartcodierten Secrets
grep -r "password\|secret\|key\|token" /app/build/api --include="*.php" | grep -v "getenv\|_ENV\|password_hash"

# Suche nach Debug-Ausgaben
grep -r "var_dump\|print_r\|echo\|die(" /app/build/api --include="*.php" | grep -v "json_encode\|send_response"
```

### 6. File Permissions ✅
```bash
# API-Verzeichnis sollte nicht schreibbar sein
chmod -R 755 /app/build/api
chmod 644 /app/build/api/*.php

# Logs-Verzeichnis muss schreibbar sein
chmod 777 /app/build/logs
```

## 🔒 SECURITY CONFIGURATION

### 1. Apache/Nginx Configuration
```apache
# .htaccess für API-Verzeichnis
<Directory /app/build/api>
    # Verhindere Directory Listing
    Options -Indexes
    
    # Blockiere direkte PHP-Zugriffe (außer Einstiegspunkte)
    <FilesMatch "\.php$">
        Order Deny,Allow
        Deny from all
    </FilesMatch>
    
    # Erlaube nur spezifische API-Endpunkte
    <FilesMatch "^(login|logout|time-entries|users|approvals|masterdata|settings|health)\.php$">
        Order Allow,Deny
        Allow from all
    </FilesMatch>
</Directory>
```

### 2. PHP Configuration
```ini
; php.ini Empfehlungen
display_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_NOTICE
expose_php = Off
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
```

## 📋 DEPLOYMENT STEPS

### 1. Backup erstellen
```bash
# Datenbank-Backup
mysqldump -u user -p aze_database > backup_$(date +%Y%m%d).sql

# Code-Backup
tar -czf backup_code_$(date +%Y%m%d).tar.gz /path/to/current/code
```

### 2. Deployment durchführen
```bash
# 1. Neue Dateien hochladen (außer excluded)
rsync -av --exclude='.env' --exclude='*.test.php' --exclude='debug-*' /app/build/ user@server:/path/to/deployment/

# 2. Cache leeren
rm -rf /path/to/deployment/cache/*

# 3. Permissions setzen
chown -R www-data:www-data /path/to/deployment/
chmod -R 755 /path/to/deployment/
chmod 777 /path/to/deployment/logs/
```

### 3. Post-Deployment Tests
```bash
# 1. Health Check
curl -I https://aze.mikropartner.de/api/health.php

# 2. Security Headers prüfen
curl -I https://aze.mikropartner.de/api/login.php | grep -E "X-Frame-Options|Strict-Transport"

# 3. Login testen (manuell)
# - Browser öffnen
# - Login durchführen
# - Timer starten/stoppen
# - Ausloggen
```

## 🚨 ROLLBACK PLAN

Falls Probleme auftreten:
```bash
# 1. Schneller Rollback
mv /path/to/deployment /path/to/deployment_broken
mv /path/to/backup /path/to/deployment

# 2. Datenbank wiederherstellen (falls nötig)
mysql -u user -p aze_database < backup_$(date +%Y%m%d).sql

# 3. Error Logs prüfen
tail -f /path/to/deployment/logs/error.log
tail -f /var/log/apache2/error.log
```

## ✅ FINAL CHECKLIST

- [ ] Umgebungsvariablen gesetzt
- [ ] Build erfolgreich
- [ ] PHP-Syntax geprüft
- [ ] Keine Secrets im Code
- [ ] Test-Dateien excluded
- [ ] File Permissions korrekt
- [ ] Backup erstellt
- [ ] Health Check erfolgreich
- [ ] Security Headers aktiv
- [ ] Login funktioniert
- [ ] Timer funktioniert
- [ ] Session-Timeout funktioniert
- [ ] Error Logs sauber

## 📞 NOTFALL-KONTAKTE

- **Entwickler**: [Kontakt]
- **Server-Admin**: [Kontakt]
- **Datenbank-Admin**: [Kontakt]

---
**Erstellt am**: 29.07.2025
**Zweck**: Sichere Deployment-Prozedur für AZE Gemini nach Security-Updates
# 🚀 Deployment & FTP-Zugriff - Dokumentation
**AZE Gemini - HostEurope Webhosting**

---

## 📡 FTP-Zugangsdaten

**WICHTIG**: Diese Daten sind in `.env.production` gespeichert und dürfen NICHT ins Git-Repository!

### Produktiv-Server (HostEurope)

```
Host:     wp10454681.server-he.de
User:     ftp10454681-aze
Pass:     321MPStart321
Protocol: FTP über SSL/TLS (FTPS)
Port:     21 (Standard)
```

### 🚨 **KRITISCH: Pfad-Mapping verstehen!**

```
FTP User Root:        /              (beim Login)
Absoluter Server-Pfad: /www/it/aze/   (tatsächlicher Pfad)
HTTP Root (Subdomain): aze.mikropartner.de → /www/it/aze/

WICHTIG: FTP_PATH muss "/" sein, NICHT "/www/aze/" oder ähnliches!
Der FTP-User ftp10454681-aze landet DIREKT in /www/it/aze/!
```

### Verzeichnisstruktur auf dem Server

```
/                                  ← FTP Root = /www/it/aze/ (HTTP Root)
├── index.php                      ← Haupt-Entry-Point (leitet zu /dist/ weiter)
├── dist/
│   ├── index.html                 ← React App Entry Point
│   └── assets/
│       ├── index-XXXXX.js         ← Vite Build (Hauptbundle)
│       ├── index-XXXXX.css        ← Styles
│       └── *.js                   ← Weitere Chunks
├── api/
│   ├── auth-start.php            ← OAuth Initiierung
│   ├── auth-callback.php         ← OAuth Callback
│   ├── auth-status.php           ← Session Check
│   ├── login.php                 ← Initial Data Load
│   ├── time-entries.php          ← Timer & Einträge
│   ├── approvals.php             ← Genehmigungen
│   ├── masterdata.php            ← Stammdaten
│   ├── users.php                 ← Benutzerverwaltung
│   ├── settings.php              ← Globale Einstellungen
│   ├── csrf-token.php            ← CSRF Token Generation
│   ├── health.php                ← Health Check
│   ├── security-middleware.php   ← Security Headers
│   ├── auth_helpers.php          ← Session Helper
│   └── DatabaseConnection.php    ← DB Connection
└── .htaccess                      ← Apache Rewrite Rules
```

---

## 🔧 Deployment-Script

### Lokales Script: `deploy-secure.sh`

**Verwendung**:
```bash
# Frontend deployen (dist/ + index.php)
./deploy-secure.sh frontend

# Backend deployen (api/*.php)
./deploy-secure.sh backend

# Alles deployen
./deploy-secure.sh all
```

**Konfiguration** (`.env.production`):
```bash
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASS=321MPStart321
FTP_PATH=/                         # KRITISCH: "/" nicht "/www/aze/"!
```

**⚠️ HÄUFIGER FEHLER:**
```bash
# FALSCH ❌
FTP_PATH=/www/aze/     # Führt zu /www/it/aze/www/aze/ (doppelt verschachtelt!)

# RICHTIG ✅
FTP_PATH=/             # FTP root = HTTP root
```

**Script-Features**:
- ✅ Verwendet FTPS (SSL/TLS verschlüsselt)
- ✅ Lädt Credentials aus `.env.production`
- ✅ Unterstützt selektives Deployment (frontend/backend/all)
- ✅ Zeigt Fortschritt und Fehler an

---

## 🌐 URL-Struktur

### Produktiv-URLs

```
Frontend:
https://aze.mikropartner.de/                    ← React App

API:
https://aze.mikropartner.de/api/auth-start.php  ← OAuth Login
https://aze.mikropartner.de/api/login.php       ← Initial Data
https://aze.mikropartner.de/api/time-entries.php ← Timer
https://aze.mikropartner.de/api/health.php      ← Health Check
```

### .htaccess Rewrite Rules

**Wichtig**: `/www/aze/.htaccess` leitet alle nicht-API-Requests zu `/dist/index.html`:

```apache
RewriteEngine On
RewriteBase /

# API-Requests durchlassen
RewriteRule ^api/ - [L]

# Statische Assets durchlassen
RewriteRule ^dist/assets/ - [L]

# Alle anderen Requests zu /dist/index.html (React Router)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /dist/index.html [L]
```

---

## 🔨 Manueller FTP-Upload (via cURL)

Falls `deploy-secure.sh` nicht funktioniert:

### Einzelne Datei hochladen:

```bash
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  -T "lokale-datei.php" \
  "ftp://wp10454681.server-he.de/www/aze/api/datei.php"
```

### Mehrere Dateien hochladen:

```bash
# Frontend
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  -T "dist/index.html" \
  "ftp://wp10454681.server-he.de/www/aze/dist/index.html"

curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  -T "dist/assets/index-C02UeB1c.js" \
  "ftp://wp10454681.server-he.de/www/aze/dist/assets/index-C02UeB1c.js"

# Backend
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  -T "api/login.php" \
  "ftp://wp10454681.server-he.de/www/aze/api/login.php"
```

**Wichtig**:
- `--ftp-ssl` aktiviert FTPS (TLS)
- `--insecure` ignoriert selbst-signierte Zertifikate
- `-T` = Upload (Transfer)

---

## 🧪 Nach Deployment testen

### 1. Health Check:

```bash
curl -k 'https://aze.mikropartner.de/api/health.php'
```

**Erwartung**:
```json
{
    "status": "ok",
    "timestamp": "2025-10-14T12:00:00+00:00",
    "php_version": "8.2.x",
    "session_configured": true
}
```

### 2. Frontend lädt:

```bash
curl -k -I 'https://aze.mikropartner.de/'
```

**Erwartung**:
```
HTTP/1.1 200 OK
Content-Type: text/html
```

### 3. Session-Cookie wird gesetzt:

```bash
curl -k -I 'https://aze.mikropartner.de/api/auth-start.php' | grep Set-Cookie
```

**Erwartung**:
```
Set-Cookie: AZE_SESSION=...; path=/; secure; HttpOnly; SameSite=Lax
```

### 4. OAuth Redirect funktioniert:

```bash
curl -k -I 'https://aze.mikropartner.de/api/auth-start.php' | grep Location
```

**Erwartung**:
```
Location: https://login.microsoftonline.com/...
```

---

## 🐛 Debugging

### FTP-Verbindung testen:

```bash
# Liste aller Dateien im Verzeichnis
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  "ftp://wp10454681.server-he.de/www/aze/" \
  -l
```

**Erwartete Ausgabe**:
```
index.php
dist
api
.htaccess
```

### Datei-Upload verifizieren:

```bash
# 1. Lokale Datei-Größe
ls -lh dist/assets/index-C02UeB1c.js

# 2. Hochladen
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  -T "dist/assets/index-C02UeB1c.js" \
  "ftp://wp10454681.server-he.de/www/aze/dist/assets/index-C02UeB1c.js"

# 3. Remote Datei-Größe prüfen
curl -k -I 'https://aze.mikropartner.de/assets/index-C02UeB1c.js' | grep Content-Length
```

**Die Größen müssen übereinstimmen!**

---

## 🔒 Sicherheit

### Credentials schützen:

1. **NIEMALS** Credentials direkt im Code
2. **IMMER** `.env.production` in `.gitignore`
3. **Regelmäßig** FTP-Passwort rotieren (alle 3-6 Monate)
4. **FTPS verwenden** (nicht Plain FTP)

### Berechtigungen auf dem Server:

```
.env Dateien:       600 (rw-------)
PHP Dateien:        644 (rw-r--r--)
.htaccess:          644 (rw-r--r--)
Verzeichnisse:      755 (rwxr-xr-x)
```

**WICHTIG**: `.env` Dateien dürfen NICHT über HTTP erreichbar sein!

---

## 🆘 Häufige Probleme

### 1. "530 Login incorrect"

**Ursache**: Falsches Passwort oder Username

**Fix**:
```bash
# Prüfe .env.production
cat .env.production | grep FTP_

# Aktualisiere bei Bedarf
FTP_PASS=321MPStart321  # ← Richtiges Passwort!
```

### 2. "Access denied: 530"

**Ursache**: Credentials falsch oder FTP-Account gesperrt

**Fix**: Login bei HostEurope Admin-Panel prüfen

### 3. Datei hochgeladen, aber alte Version auf Server

**Ursache**: PHP OPcache cached alte Version

**Fix**:
```bash
# Opcache-Reset-Script hochladen
cat > api/opcache-reset.php << 'EOF'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared!";
} else {
    echo "OPcache not enabled";
}
EOF

# Upload und Aufruf
curl --ftp-ssl --insecure --user "ftp10454681-aze:321MPStart321" \
  -T "api/opcache-reset.php" \
  "ftp://wp10454681.server-he.de/www/aze/api/opcache-reset.php"

curl -k 'https://aze.mikropartner.de/api/opcache-reset.php'
```

### 4. Deployment-Script findet .env.production nicht

**Ursache**: Script läuft im falschen Verzeichnis

**Fix**:
```bash
# Immer aus /app/build/ ausführen
cd /app/build
./deploy-secure.sh frontend
```

---

## 📦 Vollständiges Deployment

**Komplettes Deployment nach Code-Änderungen**:

```bash
# 1. Ins Build-Verzeichnis wechseln
cd /app/build

# 2. Frontend bauen
npm run build

# 3. Deployment durchführen
./deploy-secure.sh all

# 4. Verifizieren
curl -k 'https://aze.mikropartner.de/api/health.php'
curl -k -I 'https://aze.mikropartner.de/'

# 5. Browser-Test (nach Cache-Clear)
# → https://aze.mikropartner.de
```

---

## 🔄 Rollback-Strategie

**Falls Deployment Probleme verursacht**:

1. **Backup wiederherstellen**:
   ```bash
   # Lokales Git-Rollback
   git checkout HEAD~1 -- api/login.php

   # Neu deployen
   ./deploy-secure.sh backend
   ```

2. **Notfall-Fix direkt auf Server** (nur im Notfall!):
   - HostEurope KIS (Kunden-Informations-System) → Dateimanager
   - Datei editieren
   - Sofort Git-Commit machen mit den Änderungen

---

**Letzte Aktualisierung**: 2025-10-14
**Version**: 1.0
**Autor**: MP-IT
**Status**: PRODUKTIV

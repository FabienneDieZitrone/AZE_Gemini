# 🎯 DEPLOYMENT PATH GUIDE - PERMANENTE REFERENZ

**⚠️ KRITISCH**: Diese Dokumentation MUSS vor jedem Deployment gelesen werden!

## 📋 Problem-Historie

Seit Tagen tritt wiederholt das gleiche Problem auf:
- ✅ OAuth funktioniert perfekt
- ❌ Dashboard lädt nach Login NICHT
- 🔄 Pfad-Konfusion zwischen Deployment und Runtime

**Root Cause**: Verwirrung zwischen FTP-Root, Domain-Root und Asset-Pfaden.

---

## 🏗️ **ARCHITEKTUR-ÜBERSICHT**

### Server-Struktur (HostEurope) ⚠️ AKTUALISIERT 2025-10-19

```
FTP-User: ftp10454681-aze
Server: wp10454681.server-he.de

WICHTIG: FTP-Root ist direkt "/" (nicht /www/aze!)
├─ / (FTP-Root)                      ← Physischer Pfad: /is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/
   ├─ index.php                      ← Haupt-Entry-Point (NICHT index.html!)
   ├─ index.html                     ← Statische HTML (wird ignoriert wegen index.php)
   ├─ .htaccess                      ← DirectoryIndex: index.php index.html
   ├─ .env                           ← Production credentials (KRITISCH!)
   ├─ config.php                     ← Lädt .env Dateien
   ├─ api/                           ← Backend-APIs
   │  ├─ auth-callback.php           ← OAuth Callback (session_name Zeile 10!)
   │  ├─ auth-status.php             ← Session Check (session_name Zeile 12!)
   │  ├─ auth-start.php              ← OAuth Start (session_name Zeile 10!)
   │  ├─ login.php                   ← User Data Loader (session_name Zeile 13 + ob_start!)
   │  ├─ test-session-consistency.php ← Session Validator (NEU 2025-10-19)
   │  └─ DatabaseConnection.php      ← DB Connection Manager
   ├─ assets/                        ← 🔥 KRITISCH: Frontend-Assets (aktuell!)
   │  ├─ index-CVhqgbgK.js          ← Main JavaScript Bundle (720 KB) - 2025-10-19
   │  ├─ index-mmLeTg_1.css         ← Main CSS (20 KB)
   │  └─ ...                         ← Weitere Assets (html2canvas, purify, etc.)
   └─ docs/                          ← Dokumentation (Optional)
      ├─ HAR_ANALYSIS_GUIDE.md
      ├─ E2E_TEST_OAUTH_DASHBOARD.md
      └─ SESSION_LOGIN_TROUBLESHOOTING.md
```

### Domain-Konfiguration

```
aze.mikropartner.de
    └──→ DocumentRoot: /is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/
         (identisch mit FTP-Root / beim Login!)
```

**🔑 KRITISCHE ERKENNTNIS (2025-10-19 UPDATE):**
- **FTP-Login-Path**: `/` (relativ)
- **Physischer Server-Pfad**: `/is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/`
- **Domain-Root**: identisch mit physischem Pfad
- **FTP-Upload-Ziel**: IMMER `/` (relativ zum FTP-Root)
- **NIEMALS** `/www/aze` verwenden - das existiert NICHT!

---

## 🚀 **DEPLOYMENT-FLOW**

### Phase 1: Build (Lokal)

```bash
cd /home/aios/projekte/aze-gemini/claude-container/projekt/build

# 1. Build erstellen
npm run build

# Ergebnis:
├─ dist/
   ├─ index.html
   └─ assets/
      ├─ index-[hash].js
      ├─ index-[hash].css
      └─ ...
```

### Phase 2: Deployment (FTP)

**deploy-secure.sh** deployed:

```bash
# Frontend Assets
dist/assets/* → /assets/           # ✅ PRIMARY (index.php erwartet /assets/)
dist/assets/* → /dist/assets/      # ⚠️  BACKUP (falls direkt referenziert)
dist/index.html → /dist/index.html # ⚠️  Redirects to /

# Backend
api/*.php → /api/
.env.production → /api/.env

# Entry Point
index.php → /index.php
.htaccess → /.htaccess
```

### Phase 3: Runtime (Browser)

```
1. User: https://aze.mikropartner.de/
   ↓
2. Apache: Lädt index.php (DirectoryIndex)
   ↓
3. index.php: Gibt HTML mit:
   <script src="/assets/index-CVhqgbgK.js"></script>
   <link href="/assets/index-mmLeTg_1.css">
   ↓
4. Browser: Lädt https://aze.mikropartner.de/assets/index-CVhqgbgK.js
   ↓
5. ✅ Dashboard rendert
```

---

## ⚠️ **HÄUFIGE FEHLERQUELLEN**

### 1. Assets nicht deployed
```bash
# SYMPTOM: 404 für /assets/index-*.js
# URSACHE: Deployment-Script nicht ausgeführt oder fehlgeschlagen

# FIX:
bash deploy-secure.sh frontend
```

### 2. Falsche Asset-Pfade in index.php
```php
// ❌ FALSCH:
<script src="/dist/assets/index-*.js"></script>

// ✅ KORREKT:
<script src="/assets/index-*.js"></script>
```

### 3. FTP-Path-Konfusion
```bash
# ❌ FALSCH: FTP_PATH=/www/it/aze
# (FTP-User ist BEREITS in /www/it/aze chrooted!)

# ✅ KORREKT: FTP_PATH=/
# (Relativ zum FTP-Root)
```

### 4. Cache-Probleme
```bash
# SYMPTOM: Alte Assets werden geladen trotz neuem Deploy

# FIX 1: Cache-Busting in index.php
$cacheBuster = time();
<script src="/assets/index-*.js?v=<?php echo $cacheBuster; ?>">

# FIX 2: Browser-Cache löschen
# Ctrl+Shift+R (Hard Reload)
```

---

## ✅ **DEPLOYMENT-CHECKLIST**

### Pre-Deployment

- [ ] **Build erstellt**: `npm run build` erfolgreich
- [ ] **Assets existieren**: `ls -la dist/assets/` zeigt JS/CSS
- [ ] **Credentials geladen**: `.env.production` vorhanden
- [ ] **FTP_PATH korrekt**: `FTP_PATH=/` in .env.production

### Deployment

- [ ] **Frontend deployed**: `bash deploy-secure.sh frontend`
- [ ] **Backend deployed**: `bash deploy-secure.sh backend`
- [ ] **Keine Fehler**: Alle Uploads ✓
- [ ] **Asset-Hashes aktualisiert**: index.php zeigt neueste Hashes

### Post-Deployment

- [ ] **Browser-Test**: `https://aze.mikropartner.de/` lädt
- [ ] **Assets laden**: DevTools Network-Tab zeigt 200 für /assets/*
- [ ] **OAuth funktioniert**: Login → Callback → Dashboard
- [ ] **Dashboard rendert**: UI ist sichtbar

---

## 🔍 **DEBUG-PROZEDUR**

### Schritt 1: FTP-Struktur verifizieren

```bash
# Via FTP-Client oder curl
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  "ftp://wp10454681.server-he.de/" --list-only

# Erwartete Ausgabe:
# index.php
# .htaccess
# api/
# assets/
# dist/
```

### Schritt 2: Assets verifizieren

```bash
# Prüfe assets-Verzeichnis
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  "ftp://wp10454681.server-he.de/assets/" --list-only

# Erwartete Ausgabe:
# index-CVhqgbgK.js
# index-mmLeTg_1.css
# ...
```

### Schritt 3: Browser DevTools

```
1. Öffne: https://aze.mikropartner.de/
2. DevTools → Network Tab
3. Filter: JS
4. Erwartung:
   - Request: https://aze.mikropartner.de/assets/index-CVhqgbgK.js
   - Status: 200 OK
   - Size: ~720 KB

5. Wenn 404:
   → Assets nicht deployed!
   → Führe deploy-secure.sh frontend aus
```

### Schritt 4: Session-Debug

```bash
# Prüfe callback-debug.log auf FTP
curl --ftp-ssl --insecure \
  --user "ftp10454681-aze:321MPStart321" \
  "ftp://wp10454681.server-he.de/api/callback-debug.log"

# Erwartung:
# [timestamp] callback_start
# [timestamp] exchanging_code
# [timestamp] login_success
```

---

## 🛠️ **VALIDIERUNGS-SCRIPT**

Automatisches Deployment-Validation-Script wird erstellt:
→ Siehe: `scripts/validate-deployment.sh`

---

## 📊 **PFAD-MAPPING-REFERENZ**

| Kontext | Pfad | Bedeutung |
|---------|------|-----------|
| **FTP Login** | `/` | FTP-User-Root = `/is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/` |
| **Domain** | `https://aze.mikropartner.de/` | DocumentRoot = `/is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/` |
| **Deploy-Script** | `FTP_PATH=/` | Relativ zu FTP-Root |
| **index.php** | `/assets/index-*.js` | Absolut vom DocumentRoot |
| **Browser** | `https://aze.mikropartner.de/assets/` | Absolut von Domain-Root |

**GOLDENE REGEL:**
Alle Pfade sind RELATIV ZUM FTP-ROOT (`/is/htdocs/wp10454681_6ZVVNFOUIZ/www/it/aze/`), der IDENTISCH mit Domain-Root ist!

---

## 🚨 **NOTFALL-PROZEDUR**

### Dashboard lädt nicht nach Login

```bash
# 1. Sofort-Check
curl -I https://aze.mikropartner.de/assets/index-CVhqgbgK.js

# Wenn 404:
cd /home/aios/projekte/aze-gemini/claude-container/projekt/build
bash deploy-secure.sh frontend

# Wenn 200 aber Dashboard leer:
# → JavaScript-Fehler in Console
# → Prüfe Browser DevTools Console
```

### FTP-Upload schlägt fehl

```bash
# Test FTP-Verbindung
bash deploy-secure.sh verify

# Wenn fehlschlägt:
# → Credentials in .env.production prüfen
# → FTP_HOST, FTP_USER, FTP_PASS
```

### OAuth funktioniert, aber Session geht verloren

```bash
# Session-Cookies prüfen
# DevTools → Application → Cookies
# Erwartung:
# - AZE_SESSION (HttpOnly, Secure)

# Wenn fehlt:
# → session_set_cookie_params in auth-callback.php prüfen
```

---

## 📚 **WEITERE DOKUMENTATION**

- **FTP-Zugangsdaten**: `build/.env.production`
- **API-Dokumentation**: `build/docs/API_DOCUMENTATION.md`
- **Security-Checklist**: `build/DEPLOYMENT_SECURITY_CHECKLIST.md`
- **Session-Troubleshooting**: `docs/SESSION_LOGIN_TROUBLESHOOTING.md`

---

**Autor**: Günnix
**Letzte Aktualisierung**: 2025-10-19
**Version**: 1.1.0 (Session-Fix Update)
**Status**: ✅ **PRODUCTION-READY**

---

## 📝 **SESSION-NOTIZEN**

Bei jedem wiederkehrenden Problem:
1. ✅ Diese Dokumentation lesen
2. ✅ Deployment-Checklist durchgehen
3. ✅ Debug-Prozedur folgen
4. ❌ NIEMALS Pfade raten oder ändern ohne Referenz!

**🔥 KRITISCH**: Diese Datei ist die WAHRHEIT über Deployment-Pfade!

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

### Server-Struktur (HostEurope)

```
FTP-User: ftp10454681-aze
├─ FTP-Root = /www/it/aze/          ← FTP-User wird hier "chrooted"
   ├─ index.php                      ← Haupt-Entry-Point
   ├─ .htaccess                      ← DirectoryIndex: index.php index.html
   ├─ api/                           ← Backend-APIs
   │  ├─ auth-callback.php           ← OAuth Callback
   │  ├─ auth-status.php
   │  ├─ login.php
   │  └─ .env                        ← Production credentials
   ├─ assets/                        ← 🔥 KRITISCH: Frontend-Assets
   │  ├─ index-C02UeB1c.js          ← Main JavaScript Bundle
   │  ├─ index-mmLeTg_1.css         ← Main CSS
   │  └─ ...                         ← Weitere Assets
   └─ dist/                          ← Optional: Vite dist output
      ├─ index.html                  ← Redirects to /
      └─ assets/                     ← Duplicate (fallback)
```

### Domain-Konfiguration

```
aze.mikropartner.de
    └──→ DocumentRoot: /www/it/aze/
         (identisch mit FTP-Root!)
```

**🔑 KRITISCHE ERKENNTNIS:**
- **FTP-User-Root** = `/www/it/aze/`
- **Domain-Root** = `/www/it/aze/`
- **BEIDE ZEIGEN AUF DENSELBEN PUNKT!**

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
   <script src="/assets/index-C02UeB1c.js"></script>
   <link href="/assets/index-mmLeTg_1.css">
   ↓
4. Browser: Lädt https://aze.mikropartner.de/assets/index-C02UeB1c.js
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
# index-C02UeB1c.js
# index-mmLeTg_1.css
# ...
```

### Schritt 3: Browser DevTools

```
1. Öffne: https://aze.mikropartner.de/
2. DevTools → Network Tab
3. Filter: JS
4. Erwartung:
   - Request: https://aze.mikropartner.de/assets/index-C02UeB1c.js
   - Status: 200 OK
   - Size: ~735 KB

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
| **FTP Login** | `/` | FTP-User-Root = `/www/it/aze/` |
| **Domain** | `https://aze.mikropartner.de/` | DocumentRoot = `/www/it/aze/` |
| **Deploy-Script** | `FTP_PATH=/` | Relativ zu FTP-Root |
| **index.php** | `/assets/index-*.js` | Absolut vom DocumentRoot |
| **Browser** | `https://aze.mikropartner.de/assets/` | Absolut von Domain-Root |

**GOLDENE REGEL:**
Alle Pfade sind RELATIV ZUM FTP-ROOT (`/www/it/aze/`), der IDENTISCH mit Domain-Root ist!

---

## 🚨 **NOTFALL-PROZEDUR**

### Dashboard lädt nicht nach Login

```bash
# 1. Sofort-Check
curl -I https://aze.mikropartner.de/assets/index-C02UeB1c.js

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
**Letzte Aktualisierung**: 2025-10-15
**Version**: 1.0.0 (PERMANENT)
**Status**: ✅ **PRODUCTION-READY**

---

## 📝 **SESSION-NOTIZEN**

Bei jedem wiederkehrenden Problem:
1. ✅ Diese Dokumentation lesen
2. ✅ Deployment-Checklist durchgehen
3. ✅ Debug-Prozedur folgen
4. ❌ NIEMALS Pfade raten oder ändern ohne Referenz!

**🔥 KRITISCH**: Diese Datei ist die WAHRHEIT über Deployment-Pfade!

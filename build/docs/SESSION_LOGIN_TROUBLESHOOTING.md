# 🔐 Session & Login Troubleshooting Guide
**AZE Gemini - Kritische Dokumentation**

⚠️ **WICHTIG**: Dieses Dokument muss IMMER konsultiert werden, bevor Änderungen an der Session-Verwaltung oder am Login-System vorgenommen werden!

---

## 📋 Problem-Historie

### Bekannte wiederkehrende Probleme (bereits mehrfach aufgetreten):

1. **App lädt nach OAuth-Login nicht** ("MP Zeiterfassung Laden..." bleibt stehen)
2. **Session-Daten gehen verloren** nach erfolgreicher Authentifizierung
3. **Cookie-Domain-Probleme** verhindern Session-Persistenz
4. **Session-Name-Inkonsistenzen** zwischen verschiedenen PHP-Dateien
5. **Daten werden nicht geladen** (leere Dropdown-Listen, keine Zeiteinträge, etc.)

**Debug-Zeit pro Vorfall**: Mehrere Tage (!)

---

## 🎯 Root Causes - Die Hauptursachen

### 1. Session-Name muss ÜBERALL konsistent sein

**Problem**: PHP startet automatisch Sessions mit `PHPSESSID`, bevor unser Code läuft.

**Lösung**: `session_name('AZE_SESSION')` muss als **ALLERERSTE ZEILE** in jeder PHP-Datei stehen!

**Betroffene Dateien**:
```
/api/auth-start.php      → OAuth-Initiierung
/api/auth-callback.php   → OAuth-Rücksprung
/api/auth-status.php     → Session-Check (App.tsx)
/api/login.php          → Initiale Datenladung
/api/time-entries.php    → Timer & Einträge
/api/approvals.php       → Genehmigungen
/api/masterdata.php      → Stammdaten
/api/users.php          → Benutzerverwaltung
/api/settings.php        → Einstellungen
```

**RICHTIG** ✅:
```php
<?php
// CRITICAL: session_name MUSS die allererste Zeile sein!
session_name('AZE_SESSION');

// Danach erst require/define etc.
define('API_GUARD', true);
require_once __DIR__ . '/auth_helpers.php';
// ...
```

**FALSCH** ❌:
```php
<?php
require_once __DIR__ . '/auth_helpers.php';  // ← FEHLER! Hier könnte schon session_start() aufgerufen werden
session_name('AZE_SESSION');  // ← Zu spät!
```

---

### 2. Cookie-Domain MUSS leer sein

**Problem**: Explizite Domain-Angabe verhindert Cookie-Akzeptanz durch Browser.

**RICHTIG** ✅:
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // ← MUSS leer sein! Browser nutzt automatisch current domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

**FALSCH** ❌:
```php
'domain' => $_SERVER['HTTP_HOST']  // ← Browser akzeptiert Cookie nicht!
'domain' => 'aze.mikropartner.de'  // ← Browser akzeptiert Cookie nicht!
```

**Grund**: Browser setzen Cookies mit expliziter Domain restriktiver. Leerer String = "current domain".

---

### 3. Session-Initialisierung INLINE statt Funktion

**Problem**: Funktionen wie `start_secure_session()` werden zu spät aufgerufen oder importieren andere Dateien, die bereits Sessions starten.

**Lösung**: Session-Init direkt inline in jeder API-Datei!

**Template für JEDE API-Datei**:
```php
<?php
/**
 * Datei: /api/beispiel.php
 * KRITISCH: session_name() als allererste Zeile!
 */

// STEP 1: Session-Name setzen (ERSTE ZEILE!)
session_name('AZE_SESSION');

// STEP 2: Imports (falls benötigt)
define('API_GUARD', true);
require_once __DIR__ . '/security-middleware.php';
require_once __DIR__ . '/DatabaseConnection.php';

// STEP 3: Session-Parameter setzen
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // MUSS leer sein!
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// STEP 4: Session starten
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// STEP 5: Auth-Check
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['oid'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

// Ab hier: Normale API-Logik
$currentUser = $_SESSION['user'];
// ...
```

---

### 4. Login-Flow verstehen

**OAuth-Flow** (Microsoft Azure AD):

```
1. User klickt "Login"
   └→ Frontend: window.location.href = '/api/auth-start.php'

2. auth-start.php:
   ├→ Erstellt Session mit state (CSRF-Schutz)
   └→ Redirect zu Microsoft Login

3. User meldet sich bei Microsoft an
   └→ Microsoft redirected zu: /api/auth-callback.php?code=...&state=...

4. auth-callback.php:
   ├→ Prüft state (CSRF-Schutz)
   ├→ Tauscht code gegen Access Token
   ├→ Holt User-Infos (name, oid, upn)
   ├→ Speichert in $_SESSION['user']
   └→ Redirect zu: /

5. Frontend (App.tsx) lädt:
   ├→ useEffect() ruft api.checkAuthStatus()
   │  └→ Aufruf: GET /api/auth-status.php
   │     └→ Prüft: $_SESSION['user'] vorhanden?
   │        ├─ JA → setIsAuthenticated(true)
   │        └─ NEIN → Redirect zu /api/auth-start.php
   │
   └─ Wenn authenticated: api.loginAndGetInitialData()
      └→ Aufruf: POST /api/login.php
         └→ Lädt: users, masterData, timeEntries, approvals, etc.
```

**KRITISCHE PUNKTE**:

- ✅ Session muss von `auth-callback.php` bis `login.php` bestehen bleiben
- ✅ Cookie-Name muss überall `AZE_SESSION` sein
- ✅ Cookie-Domain muss überall leer sein (`''`)
- ✅ Session-Daten dürfen nicht verloren gehen

---

## 🔧 Debugging-Prozedur

### Schritt 1: Session nach OAuth prüfen

Rufe nach erfolgreichem Login auf:
```bash
curl -k 'https://aze.mikropartner.de/api/test-check-real-session.php'
```

**Erwartetes Ergebnis** ✅:
```json
{
    "session_name": "AZE_SESSION",
    "session_data": {
        "user": {
            "oid": "382fe473-...",
            "name": "Max Mustermann",
            "username": "max.mustermann@firma.de"
        }
    },
    "check": {
        "has_user": true,
        "has_oid": true,
        "would_login_work": true
    }
}
```

**Fehlerhaftes Ergebnis** ❌:
```json
{
    "session_name": "PHPSESSID",  // ← FALSCH! Muss AZE_SESSION sein
    "session_data": {},            // ← Leer = User-Daten gingen verloren
    "check": {
        "would_login_work": false  // ← login.php wird 401 zurückgeben
    }
}
```

**Fix**: Prüfe `auth-callback.php` - speichert es User-Daten korrekt?

---

### Schritt 2: Cookie im Browser prüfen

Browser DevTools (F12) → Application → Cookies → `https://aze.mikropartner.de`

**Erwartetes Cookie** ✅:
```
Name:     AZE_SESSION
Value:    751c4073ac5b2c7e819f05d39766e7bb
Domain:   aze.mikropartner.de          ← KEINE führende Punkt!
Path:     /
Secure:   ✓
HttpOnly: ✓
SameSite: Lax
```

**Fehlerhaftes Cookie** ❌:
```
Name:     PHPSESSID                    ← FALSCH!
Domain:   .aze.mikropartner.de         ← Führender Punkt ist Problem!
Secure:   ✗                            ← Muss ✓ sein
```

**Fix**: Prüfe `session_set_cookie_params()` in allen API-Dateien.

---

### Schritt 3: Browser-Konsole prüfen

Nach Login → F12 → Console

**Häufige Fehler**:

1. **`401 Unauthorized` auf `/api/auth-status.php`**
   - **Ursache**: Session hat keine User-Daten
   - **Fix**: Prüfe auth-callback.php

2. **`401 Unauthorized` auf `/api/login.php`**
   - **Ursache**: Session-Name inkonsistent
   - **Fix**: Prüfe session_name() in login.php

3. **`JSON.parse: unexpected end of data`**
   - **Ursache**: API gibt leere Antwort zurück
   - **Fix**: Prüfe PHP-Fehlerlog auf Server

4. **`TypeError: Cannot read property 'name' of null`**
   - **Ursache**: login.php gibt keine Daten zurück
   - **Fix**: Prüfe Datenbank-Connection und SQL-Queries

---

### Schritt 4: Server PHP-Fehlerlog prüfen

HostEurope Admin Panel → Logs → PHP Error Log

**Typische Fehler**:

1. **`Warning: session_start(): Cannot send session cookie`**
   - **Ursache**: Headers bereits gesendet (whitespace vor `<?php`)
   - **Fix**: Entferne alle Leerzeichen/Newlines vor `<?php`

2. **`Notice: Undefined index: user`**
   - **Ursache**: Session hat keine User-Daten
   - **Fix**: auth-callback.php speichert keine Daten

3. **`PDOException: Access denied for user`**
   - **Ursache**: Datenbank-Credentials falsch
   - **Fix**: Prüfe `DatabaseConnection.php` und `.env`

---

## 🚨 Notfall-Checkliste

Wenn App nach Login nicht lädt:

### ✅ Checkliste durcharbeiten:

1. [ ] Browser-Cache und Cookies komplett löschen
2. [ ] Neuer Inkognito-Tab öffnen
3. [ ] OAuth-Flow komplett neu durchlaufen
4. [ ] Browser DevTools → Network → Alle Requests prüfen:
   - [ ] `/api/auth-status.php` → 200 OK (nicht 401)
   - [ ] `/api/login.php` → 200 OK mit JSON-Daten
   - [ ] Cookie `AZE_SESSION` wird gesetzt und gesendet
5. [ ] Test-Script aufrufen: `/api/test-check-real-session.php`
6. [ ] Browser-Konsole auf JavaScript-Fehler prüfen
7. [ ] Server PHP-Fehlerlog prüfen

### 🔍 Systematisches Debugging:

```bash
# Test 1: OAuth-Initierung
curl -k -I 'https://aze.mikropartner.de/api/auth-start.php' | grep -E '(Set-Cookie|Location)'
# Erwartung: Set-Cookie: AZE_SESSION=... + Location: https://login.microsoftonline.com/...

# Test 2: Session nach Login
curl -k 'https://aze.mikropartner.de/api/test-check-real-session.php'
# Erwartung: would_login_work: true

# Test 3: Auth-Status
curl -k -b 'AZE_SESSION=<ECHTE_SESSION_ID>' 'https://aze.mikropartner.de/api/auth-status.php'
# Erwartung: 200 OK (kein JSON, nur Status)

# Test 4: Login-Daten
curl -k -b 'AZE_SESSION=<ECHTE_SESSION_ID>' -X POST 'https://aze.mikropartner.de/api/login.php'
# Erwartung: JSON mit users, masterData, timeEntries, etc.
```

---

## 📝 Änderungs-Checkliste

**BEVOR du Code an Session-Verwaltung änderst**:

1. [ ] Hast du diese Dokumentation gelesen?
2. [ ] Hast du `session_name('AZE_SESSION')` als ERSTE Zeile?
3. [ ] Hast du `'domain' => ''` in session_set_cookie_params?
4. [ ] Hast du alle betroffenen API-Dateien aktualisiert?
5. [ ] Hast du ein Backup gemacht? (`git commit -m "Before session changes"`)
6. [ ] Hast du nach dem Deployment getestet:
   - [ ] OAuth-Login komplett durchlaufen
   - [ ] App lädt nach Login
   - [ ] Daten werden angezeigt (Timer, Listen, etc.)
   - [ ] Browser-Konsole zeigt keine Fehler
   - [ ] Test-Scripts liefern korrekte Ergebnisse

---

## 📚 Weitere Ressourcen

- **PHP Session Docs**: https://www.php.net/manual/en/book.session.php
- **Cookie Attributes**: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie
- **OAuth 2.0 Flow**: https://learn.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow

---

## 🆘 Support

Wenn dieser Guide nicht hilft:

1. **Browser-Konsole Screenshot** erstellen (F12 → Console)
2. **Network-Tab Export** (F12 → Network → Export HAR)
3. **Server PHP-Error-Log** kopieren
4. **Test-Script Ausgaben** sammeln
5. **Issue auf GitHub** erstellen mit allen Infos

---

**Letzte Aktualisierung**: 2025-10-14
**Version**: 1.0
**Autor**: MP-IT / Claude Code
**Status**: 🔴 KRITISCH - Bei Änderungen an Session-Code IMMER konsultieren!

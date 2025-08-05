# 500 Error nach Azure AD Login - Kritisches Produktionsproblem

## Problem
Nach erfolgreicher Azure AD Authentifizierung gibt die Anwendung einen 500 Internal Server Error beim Aufruf von `/api/login.php` zurück.

## Symptome
- Azure AD Login funktioniert (Redirect nach Authentifizierung erfolgt)
- Frontend zeigt roten Hintergrund mit "API-Fehler: 500 Internal Server Error"
- Fehler tritt auf bei POST Request zu `/api/login.php`
- Generische Fehlermeldung: "An unexpected error occurred"
- ALLE neuen PHP-Dateien geben 500 Fehler (nicht nur login.php)

## Root Cause Analyse

### 1. Datenbankschema-Problem
- Code referenziert nicht-existente `created_at` Spalte in users Tabelle
- Tatsächliche Struktur: `id, username, display_name, role, azure_oid`

### 2. Session Cookie Konfiguration
- Cookie-Parameter wurden nach `session_start()` gesetzt (muss vorher sein)
- Domain-Parameter Problem bei Cookie-Konfiguration

### 3. Server-Konfiguration Problem
- ALLE neuen PHP-Dateien geben 500 Fehler
- Existierende Dateien wie health.php funktionieren
- Hinweis auf Server-spezifisches Problem (Permissions, Encoding, .htaccess)

## Bereits durchgeführte Fixes

- ✅ `created_at` Referenzen aus SQL-Queries entfernt
- ✅ Session Cookie Konfiguration korrigiert
- ✅ Domain-Parameter in Cookie-Konfiguration auf leer gesetzt
- ✅ Login.php basierend auf funktionierender health.php neu strukturiert
- ✅ Mehrere Debug-Versionen erstellt
- ✅ Ultra-minimale PHP-Dateien getestet (alle geben 500)

## Debug-Informationen

### Funktionierende Dateien:
- `/api/health.php` ✅
- `/api/auth-status.php` ✅
- `/api/auth-start.php` ✅

### Nicht funktionierende Dateien (500 Error):
- `/api/login.php` ❌
- `/api/session-test.php` ❌
- `/api/server-diagnostic.php` ❌
- `/api/simple-test.php` ❌ (nur 5 Zeilen PHP!)
- Alle neu erstellten PHP-Dateien ❌

## Technische Details

### Beispiel Request/Response:
```
POST https://aze.mikropartner.de/api/login.php
Status: 500 Internal Server Error
Response: {
  "success": false,
  "error": {
    "code": "INTERNAL_ERROR",
    "message": "An unexpected error occurred",
    "details": {
      "originalError": "Error"
    },
    "recoveryStrategy": null
  }
}
```

### Session Cookie nach Azure AD Login:
```
PHPSESSID=67c6069cf5a65f670d4e916c2b10ae4e
```

## Aktueller Status
- Anwendung ist **NICHT NUTZBAR**
- Login-Prozess blockiert bei login.php Aufruf
- Mehrere Lösungsversuche ohne Erfolg

## Dringend benötigte Aktionen

1. **Server-Logs prüfen**
   - PHP Error Log
   - Apache Error Log
   - Detaillierte Fehlermeldung finden

2. **Server-Konfiguration prüfen**
   - .htaccess Dateien
   - PHP-Konfiguration
   - Dateiberechtigungen (chmod/chown)

3. **Vergleich alte vs. neue Dateien**
   - File encoding (UTF-8 BOM?)
   - Line endings (CRLF vs LF?)
   - Permissions

## Umgebung
- **Server**: HostEurope (wp10454681.server-he.de)
- **PHP Version**: 8.2
- **Datenbank**: MySQL (db10454681-aze)
- **URL**: https://aze.mikropartner.de
- **FTP**: ftp10454681-aze

## Priorität
🔴 **KRITISCH** - Produktionsumgebung nicht nutzbar

## Reproduktion
1. Gehe zu https://aze.mikropartner.de
2. Klicke "Mit Microsoft anmelden"
3. Melde dich mit Azure AD an
4. Nach Redirect → 500 Error

## Workaround
Keiner verfügbar - System ist komplett blockiert
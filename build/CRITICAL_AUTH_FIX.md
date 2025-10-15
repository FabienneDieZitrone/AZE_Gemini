# KRITISCHER FIX: Session Name Problem

## Problem Identifiziert
Die Session wird mit dem Namen "PHPSESSID" gestartet BEVOR auth_helpers.php geladen wird. Wenn dann `start_secure_session()` aufgerufen wird, findet es eine aktive Session mit falschem Namen, ruft `session_write_close()` auf, aber startet die Session dann NICHT mit dem neuen Namen "AZE_SESSION" neu.

## Root Cause
Nach `session_write_close()` bleibt `session_status()` = `PHP_SESSION_ACTIVE`, aber der Session-Name bleibt "PHPSESSID". Die alte Logik prüfte nur ob die Session aktiv ist, nicht ob der Name korrekt ist.

## Lösung
Die Funktion `start_secure_session()` in `/api/auth_helpers.php` wurde korrigiert:

### Alt (Zeilen 98-128):
```php
$migrate = null;
$needsRestart = false;

if (session_status() === PHP_SESSION_ACTIVE) {
    if (session_name() !== 'AZE_SESSION') {
        $migrate = $_SESSION ?? null;
        session_write_close();
        $needsRestart = true;  // <-- Variable wird nie verwendet!
    } else {
        return;
    }
}

session_name('AZE_SESSION');
session_set_cookie_params([...]);
session_start();  // <-- Wird nie aufgerufen, weil session_status() noch ACTIVE ist
```

### Neu (Zeilen 98-128):
```php
if (session_status() === PHP_SESSION_ACTIVE) {
    if (session_name() === 'AZE_SESSION') {
        // Richtige Session bereits aktiv - fertig
        return;
    }
    // Falsche Session (z.B. PHPSESSID) aktiv - Daten migrieren
    $migrate = $_SESSION ?? null;
    session_write_close();
    // Nach session_write_close() können wir den Namen ändern
} else {
    $migrate = null;
}

// Set session name (immer, wenn wir hier ankommen)
session_name('AZE_SESSION');

// Set cookie params
session_set_cookie_params([...]);

// Start session mit dem neuen Namen (immer!)
session_start();
```

## Deployment

Die korrigierte Datei befindet sich in `/app/build/api/auth_helpers.php`.

### Option 1: FTP Upload
```bash
./deploy-secure.sh backend
```

### Option 2: Manueller Upload
Datei `/app/build/api/auth_helpers.php` per FTP nach `/www/aze/api/auth_helpers.php` hochladen.

### Option 3: Direkte Änderung auf Server
Falls FTP nicht verfügbar, die Funktion `start_secure_session()` (Zeilen 90-148) in `/www/aze/api/auth_helpers.php` mit dem neuen Code ersetzen.

## Erwartetes Ergebnis

Nach dem Fix sollte:
1. Der Test `/api/test-login-debug.php` zeigen: `"session_name": "AZE_SESSION"` (nicht "PHPSESSID")
2. OAuth Login sollte funktionieren
3. Nach Login sollte `/api/login.php` die Benutzerdaten zurückgeben (nicht "Unauthorized")
4. Die App sollte vollständig laden mit Dashboard

## Test
```bash
# Test 1: Prüfe Session Name
curl -k https://aze.mikropartner.de/api/test-login-debug.php | jq '.step3_after_start_secure_session.session_name'
# Sollte ausgeben: "AZE_SESSION"

# Test 2: Kompletter OAuth Flow
# 1. Browser öffnen: https://aze.mikropartner.de
# 2. Login Button klicken
# 3. Bei Microsoft anmelden
# 4. App sollte laden mit Dashboard
```

## Zusätzliche Änderungen
- Zeile 136: `if (isset($migrate) && is_array($migrate))` statt `if (is_array($migrate))` (robuster)

---
**Erstellt**: 2025-10-14
**Datei**: /app/build/api/auth_helpers.php (Zeilen 90-148)
**Status**: Bereit für Deployment

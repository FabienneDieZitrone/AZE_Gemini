# 🔧 Session Troubleshooting - Lessons Learned (2025-10-20)

**Autor**: Günnix
**Datum**: 2025-10-20
**Kontext**: Timer-Start-Funktionalität und Nachtragen-Feature Debugging
**Status**: ✅ Vollständig behoben und dokumentiert

---

## 📋 Executive Summary

Bei der Implementierung der Timer-Funktionalität traten mehrere kritische Session-Management-Fehler auf, die schrittweise behoben wurden. Dieses Dokument dokumentiert die komplette Fehler-Evolution, Root Causes und Best Practices, um zukünftige Session-Probleme zu vermeiden.

**Kern-Problem**: PHP Session-Konfiguration muss VOR `session_start()` erfolgen, aber wurde nach aktivierter Session aufgerufen.

---

## 🔴 Problem-Evolution (Chronologisch)

### Phase 1: JSON.parse Error beim Timer-Start

**Symptom**:
```
JSON.parse: unexpected end of data at line 2 column 1
```

**Root Cause**:
Mehrere PHP-Dateien hatten closing `?>` Tags mit trailing newlines, die premature output vor der JSON-Response verursachten.

**Betroffene Dateien**:
- `constants.php` (2 newlines nach `?>`)
- `config-compat.php` (2 newlines nach `?>`)
- `DatabaseConnection.php` (1 newline nach `?>`)
- `InputValidationService.php` (closing tag vorhanden)

**Fix**:
```php
// ❌ FALSCH
?>


// ✅ KORREKT
// Kein closing ?> tag - PHP Best Practice!
```

**Lesson Learned**:
- PSR-2: PHP-Dateien sollen NIEMALS closing `?>` Tags haben
- Trailing whitespace/newlines werden als Output gesendet und korrumpieren JSON-Responses

---

### Phase 2: Session-Name Inkonsistenz

**Symptom**:
```
Unauthorized: No valid session found
```

**Root Cause**:
- `login.php` setzte Session-Name auf `'AZE_SESSION'`
- `time-entries.php` verwendete Standard-Session-Name `'PHPSESSID'`
- Cookie-Mismatch führte zu "keine Session gefunden"

**Fix**:
```php
// CRITICAL: MUSS als ERSTE Zeile in JEDEM API-Endpoint sein!
session_name('AZE_SESSION');
```

**Lesson Learned**:
- Session-Name muss in ALLEN API-Dateien konsistent sein
- `session_name()` muss VOR jeder Session-Operation aufgerufen werden
- Session-Name muss VOR `session_start()` gesetzt werden

---

### Phase 3: Doppelte Deklarationen

**Symptome**:
- `Constant API_GUARD already defined`
- `Constant ERROR_CODES already defined`
- `Function handleError() already declared`

**Root Cause**:
Mehrere Dateien definierten dieselben Konstanten/Funktionen ohne Guards.

**Betroffene Dateien**:
- `time-entries.php` und `time-entries.impl.php`: beide definierten `API_GUARD`
- `error-handler.php`: ERROR_CODES und alle Funktionen ohne Guards

**Fix**:
```php
// ✅ Konstanten mit Guard
if (!defined('API_GUARD')) {
    define('API_GUARD', true);
}

// ✅ Funktionen mit Guard
if (!function_exists('handleError')) {
    function handleError($error) {
        // implementation
    }
}
```

**Lesson Learned**:
- ALLE Konstanten müssen mit `if (!defined())` Guard versehen werden
- ALLE Funktionen müssen mit `if (!function_exists())` Guard versehen werden
- Entry-Point-Dateien sollten Guards setzen, Implementation-Dateien sollten Guards prüfen

---

### Phase 4: Doppelter session_name() Aufruf

**Symptom**:
```
HTTP 500 - Empty response (Content-Length: 0)
```

**Root Cause**:
`session_name('AZE_SESSION')` wurde zweimal aufgerufen:
1. In `time-entries.php` (Entry Point) - Line 5
2. In `time-entries.impl.php` (Implementation) - Line 11

PHP erlaubt `session_name()` nur EINMAL pro Request.

**Fix**:
```php
// time-entries.php (Entry Point)
session_name('AZE_SESSION');  // ✅ Hier setzen

// time-entries.impl.php (Implementation)
// KEIN session_name() mehr!  // ✅ Entfernt
```

**Lesson Learned**:
- `session_name()` darf nur EINMAL pro Request aufgerufen werden
- Entry-Point-Dateien sollten Session-Name setzen
- Implementation-Dateien sollten Session-Name NICHT setzen

---

### Phase 5: Fehlende Methoden

**Symptom**:
```
Call to undefined method InputValidationService::sanitizeString()
```

**Root Cause**:
`time-entries.impl.php` rief `$validator->sanitizeString()` auf, aber die Methode existierte nicht in der InputValidationService-Klasse.

**Fix**:
```php
// InputValidationService.php - Line 345-350
public function sanitizeString($input) {
    return (new StringSanitizer())->sanitize($input);
}
```

**Lesson Learned**:
- Bei Refactorings alle Method-Calls prüfen
- Static vs. Instance Methods unterscheiden
- Public API einer Klasse dokumentieren

---

### Phase 6: Datenbank-Feld ohne Default

**Symptom**:
```
Field 'role' doesn't have a default value
```

**Root Cause**:
INSERT Statement für `time_entries` enthielt kein `role` Feld, aber die Datenbank-Spalte war `NOT NULL` ohne Default-Wert.

**Fix**:
```php
// 1. User role fetchen
$userRole = 'employee'; // Fallback
if ($stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $stmt->bind_result($roleResult);
        if ($stmt->fetch()) {
            $userRole = $roleResult;
        }
    }
    $stmt->close();
}

// 2. Role in dynamisches INSERT integrieren
if (!empty($cols['role'])) {
    $fields[] = '`role`';
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $userRole;
}
```

**Lesson Learned**:
- Alle NOT NULL Felder ohne Default-Wert müssen im INSERT enthalten sein
- Dynamische Column-Detection für Schema-Flexibilität
- Foreign Key Daten immer fetchen bevor sie verwendet werden

---

### Phase 7: Dashboard erkennt laufenden Timer nicht

**Symptom**:
Nach Dashboard-Reload wird Start-Button angezeigt statt Stop-Button, obwohl Timer läuft.

**Root Cause**:
`checkForRunningTimer()` Funktion im Frontend war DEAKTIVIERT wegen früherer API-Probleme.

**Betroffene Datei**: `src/components/TimerService.tsx`

**Code**:
```typescript
// ❌ DEAKTIVIERT
const checkForRunningTimer = useCallback(async () => {
    console.log('[Timer] Check for running timer disabled - API needs fix');
    return;  // Early return!

    /* ORIGINAL CODE - RE-ENABLE AFTER API FIX:
    try {
        const response = await fetch('/api/time-entries.php?action=check_running', {
```

**Fix**:
```typescript
// ✅ REAKTIVIERT
const checkForRunningTimer = useCallback(async () => {
    if (!currentUser) return;

    try {
        const response = await fetch('/api/time-entries.php?action=check_running', {
            method: 'GET',
            credentials: 'include'
        });

        if (response.ok) {
            const data = await response.json();
            if (data.hasRunningTimer && data.runningTimer) {
                const startTime = new Date(`${data.runningTimer.date}T${data.runningTimer.startTime}`).getTime();
                timer.setFromExisting(startTime, data.runningTimer.id);
            }
        }
    } catch (error) {
        console.error('Error checking for running timer:', error);
    }
}, [currentUser, timer]);
```

**Lesson Learned**:
- DISABLED-Code immer mit klarem TODO und Datum versehen
- API-Probleme beheben statt Frontend-Checks zu deaktivieren
- Nach API-Fixes Frontend-Code reaktivieren

---

### Phase 8: session_name() nach session_start() (KRITISCH!)

**Symptom**:
```
session_name(): Session name cannot be changed when a session is active
```

**Kontext**: Trat beim Nachtragen von Zeiten auf (nicht beim Timer-Start!)

**Root Cause**:
```php
// auth_helpers.php - start_secure_session()
function start_secure_session() {
    session_name('AZE_SESSION');  // ❌ FEHLER wenn Session bereits aktiv!

    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session läuft bereits...
    }
}
```

**Problem**: Wenn die Session bereits von einem anderen API-Endpoint gestartet wurde, war `session_name()` zu spät.

**Fix**:
```php
function start_secure_session() {
    $migrate = null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session bereits aktiv - KEIN session_name() mehr!
        $migrate = $_SESSION ?? null;
    } else {
        // Session noch nicht aktiv - jetzt session_name() setzen ✅
        session_name('AZE_SESSION');
    }
    // ... rest
}
```

**Lesson Learned**:
- `session_name()` darf nur aufgerufen werden wenn Session NICHT aktiv ist
- Immer `session_status()` prüfen BEVOR `session_name()` aufgerufen wird
- Entry-Point-Pattern: Erste API-Datei setzt Session-Name, alle anderen überspringen

---

### Phase 9: ini_set() nach session_start() (KRITISCH!)

**Symptom**:
```
ini_set(): Session ini settings cannot be changed when a session is active
```

**Kontext**: Trat beim Nachtragen von Zeiten auf (gleicher Trigger wie Phase 8)

**Root Cause**:
```php
// auth_helpers.php - start_secure_session()
function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $migrate = $_SESSION ?? null;
    } else {
        session_name('AZE_SESSION');
    }

    // ❌ FEHLER: Diese ini_set() Aufrufe passieren IMMER, auch wenn Session aktiv!
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_secure', '1');
    @ini_set('session.cookie_samesite', 'Lax');
}
```

**Problem**: Die `ini_set()` Aufrufe für Session-Settings wurden AUSSERHALB des if/else-Blocks ausgeführt, also auch wenn die Session bereits aktiv war.

**Fix (FINALE LÖSUNG)**:
```php
function start_secure_session() {
    $migrate = null;
    $sessionWasActive = (session_status() === PHP_SESSION_ACTIVE);

    if ($sessionWasActive) {
        // Session bereits aktiv - keine Neu-Konfiguration! ✅
        $migrate = $_SESSION ?? null;
    } else {
        // Session noch nicht aktiv - JETZT konfigurieren ✅

        // Set session name
        session_name('AZE_SESSION');

        // Härtung der Session-Engine (nur wenn Session noch nicht aktiv)
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_secure', '1');
        @ini_set('session.cookie_samesite', 'Lax');

        // Set cookie params BEFORE session_start
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Start session
        session_start();
    }

    // Migriere relevante Daten aus vorheriger Session (falls vorhanden)
    if (isset($migrate) && is_array($migrate)) {
        foreach (['user','created_at','last_activity','last_regeneration'] as $k) {
            if (isset($migrate[$k]) && !isset($_SESSION[$k])) {
                $_SESSION[$k] = $migrate[$k];
            }
        }
    }

    // Initialisiere/fixe Session-Zeitstempel robust
    $now = time();
    if (!isset($_SESSION['created_at'])) { $_SESSION['created_at'] = $now; }
    if (!isset($_SESSION['last_activity'])) { $_SESSION['last_activity'] = $now; }
}
```

**Lesson Learned**:
- **ALLE** Session-Konfigurationen müssen VOR `session_start()` erfolgen:
  - `session_name()`
  - `ini_set('session.*')`
  - `session_set_cookie_params()`
  - `session_start()`
- Diese Funktionen dürfen NICHT aufgerufen werden wenn Session bereits aktiv ist
- Lösung: Gesamte Session-Initialisierung in `else`-Block (wenn Session NICHT aktiv)

---

## ✅ PHP Session Best Practices (DEFINITIV)

### 1. Session-Initialisierung-Reihenfolge (KRITISCH!)

```php
// ✅ KORREKTE Reihenfolge (MUSS so sein!)
if (session_status() !== PHP_SESSION_ACTIVE) {
    // 1. Session-Name setzen (ZUERST!)
    session_name('YOUR_SESSION_NAME');

    // 2. INI-Settings konfigurieren
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Lax');

    // 3. Cookie-Parameter setzen
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // 4. Session starten (ZULETZT!)
    session_start();
}

// ❌ FALSCH: Nach session_start() konfigurieren
session_start();
session_name('NAME');  // ❌ Fehler!
ini_set('session.*');  // ❌ Fehler!
```

### 2. Session-Status prüfen (IMMER!)

```php
// ✅ KORREKT: Immer prüfen vor Session-Operationen
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Sichere Zone für Session-Konfiguration
    session_name('AZE_SESSION');
    session_start();
}

// ❌ FALSCH: Blind aufrufen
session_name('AZE_SESSION');  // Kann fehlschlagen!
session_start();
```

### 3. Session-Name-Konsistenz (KRITISCH!)

```php
// ✅ KORREKT: Session-Name in ALLEN API-Dateien konsistent
// login.php
session_name('AZE_SESSION');
session_start();

// time-entries.php
session_name('AZE_SESSION');  // Gleicher Name!
session_start();

// masterdata.php
session_name('AZE_SESSION');  // Gleicher Name!
session_start();

// ❌ FALSCH: Unterschiedliche Session-Namen
// login.php
session_name('AZE_SESSION');

// time-entries.php
session_name('PHPSESSID');  // ❌ Anderer Name = keine Session gefunden!
```

### 4. Entry-Point Pattern

```php
// ✅ EMPFOHLEN: Entry-Point setzt Session-Name
// time-entries.php (Entry Point)
session_name('AZE_SESSION');  // Hier setzen
define('API_GUARD', true);
require_once __DIR__ . '/time-entries.impl.php';

// time-entries.impl.php (Implementation)
if (!defined('API_GUARD')) {
    die('Direct access not allowed');
}
// KEIN session_name() hier!
// Session wird von start_secure_session() gehandhabt
```

### 5. Closing PHP Tags (NIE verwenden!)

```php
// ✅ KORREKT: Kein closing tag
<?php
// ... code

// EOF (keine ?> closing tag!)

// ❌ FALSCH: Closing tag mit trailing whitespace
<?php
// ... code
?>


// Trailing whitespace wird als Output gesendet!
```

### 6. Guards für Konstanten und Funktionen

```php
// ✅ KORREKT: Mit Guards
if (!defined('API_GUARD')) {
    define('API_GUARD', true);
}

if (!function_exists('send_response')) {
    function send_response($status, $data) {
        // implementation
    }
}

// ❌ FALSCH: Ohne Guards
define('API_GUARD', true);  // Fatal Error wenn bereits definiert!
function send_response($status, $data) {  // Fatal Error wenn bereits deklariert!
    // implementation
}
```

---

## 🔍 Debugging-Strategien

### 1. Session-Debug-Output

```php
// Temporärer Debug-Code (VOR Produktion entfernen!)
error_log("Session Status: " . session_status());
error_log("Session Name: " . session_name());
error_log("Session ID: " . session_id());
error_log("Session Data: " . json_encode($_SESSION));
```

### 2. Output-Buffer-Capture

```php
// Hilfreich bei "headers already sent" Fehlern
ob_start();
// ... include files
$captured_output = ob_get_clean();
if (!empty($captured_output)) {
    error_log("Premature output detected: " . $captured_output);
}
```

### 3. Include-Chain-Testing

```php
// test-includes.php
ob_start();
echo "BEFORE include\n";
require_once __DIR__ . '/problematic-file.php';
echo "AFTER include\n";
$output = ob_get_clean();
header('Content-Type: text/plain');
echo $output;
```

### 4. Minimal-Reproducer

Erstelle minimale Test-Dateien um Probleme zu isolieren:

```php
// test-minimal-timer-start.php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

session_name('AZE_SESSION');
session_start();

$_SESSION['user'] = [
    'id' => 2,
    'name' => 'Test User',
    'username' => 'test@example.com'
];

require_once __DIR__ . '/time-entries.impl.php';

// Test start action
$_GET['action'] = 'start';
$_POST = json_decode('{"date":"2025-10-20","startTime":"10:00:00","createdBy":"Test"}', true);

handleStart($conn, $_SESSION['user'], InputValidationService::getInstance());
```

---

## 📊 Troubleshooting-Flowchart

```
Session-Fehler beim Nachtragen?
│
├─ "session_name() cannot be changed"
│  └─ Check: Wird session_name() nach session_start() aufgerufen?
│     └─ FIX: session_name() nur wenn session_status() !== ACTIVE
│
├─ "ini_set() cannot be changed"
│  └─ Check: Wird ini_set('session.*') nach session_start() aufgerufen?
│     └─ FIX: Alle Session-INI in if-Block (nur wenn Session NICHT aktiv)
│
├─ "No valid session found" / "Unauthorized"
│  └─ Check: Ist session_name() konsistent in ALLEN API-Dateien?
│     └─ FIX: Verwende überall den gleichen Session-Namen
│
├─ "JSON.parse error" / "unexpected end of data"
│  └─ Check: Haben PHP-Dateien closing ?> Tags mit trailing whitespace?
│     └─ FIX: Entferne alle closing ?> Tags (PSR-2 Best Practice)
│
├─ "Constant already defined" / "Function already declared"
│  └─ Check: Werden Konstanten/Funktionen mehrfach definiert?
│     └─ FIX: if (!defined()) und if (!function_exists()) Guards
│
└─ "Field 'xyz' doesn't have a default value"
   └─ Check: Sind alle NOT NULL Felder im INSERT enthalten?
      └─ FIX: Fehlende Felder zum INSERT hinzufügen
```

---

## 🎯 Checkliste für zukünftige Session-Implementierungen

**Vor dem Coding:**
- [ ] Dokumentiere welcher Session-Name verwendet wird
- [ ] Prüfe ob Session-Name in allen API-Dateien konsistent ist
- [ ] Definiere Entry-Point-Datei die Session initialisiert

**Während des Codings:**
- [ ] Verwende NIEMALS closing `?>` Tags in PHP-Dateien
- [ ] Alle Session-Konfigurationen in if-Block (nur wenn Session NICHT aktiv)
- [ ] Verwende Guards für alle Konstanten und Funktionen
- [ ] Prüfe session_status() BEVOR session_name() aufgerufen wird
- [ ] Teste mit aktivierter Session UND ohne Session

**Nach dem Coding:**
- [ ] Teste alle API-Endpoints in korrekter Reihenfolge
- [ ] Teste Cross-API-Calls (z.B. Login → Timer-Start → Nachtragen)
- [ ] Prüfe Backend-Logs auf Warnings/Errors
- [ ] Teste mit frischer Session (Inkognito-Modus)
- [ ] Teste mit bestehender Session (normaler Browser)

---

## 📚 Referenzen

- **PHP Session Management**: https://www.php.net/manual/en/book.session.php
- **PSR-2 Coding Standard**: https://www.php-fig.org/psr/psr-2/
- **PHP Security Best Practices**: https://owasp.org/www-project-php-security-cheat-sheet/

---

## 🔄 Update-Historie

| Datum | Version | Änderungen |
|-------|---------|------------|
| 2025-10-20 | 1.0 | Initiale Dokumentation nach vollständiger Timer-Reparatur |

---

**⚠️ WICHTIG für zukünftige Entwicklung:**

Wenn Session-Fehler auftreten:
1. **NICHT** die Funktion deaktivieren
2. **NICHT** Workarounds implementieren
3. **Lies diese Dokumentation vollständig**
4. **Folge dem Troubleshooting-Flowchart**
5. **Implementiere den korrekten Fix**

**Session-Management in PHP ist komplex, aber mit den richtigen Patterns vollständig beherrschbar.** ✅

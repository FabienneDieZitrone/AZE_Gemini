# 🚀 PHP Session Quick Reference - AZE Gemini

**Schnellreferenz für Session-Management - Die wichtigsten DOs und DON'Ts**

---

## ✅ DOs (IMMER befolgen!)

### 1. Korrekte Session-Initialisierung

```php
// ✅ RICHTIG: Immer diese Reihenfolge!
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('AZE_SESSION');           // 1. Name setzen
    ini_set('session.use_strict_mode', '1'); // 2. INI-Settings
    session_set_cookie_params([...]);      // 3. Cookie-Params
    session_start();                       // 4. Session starten
}
```

### 2. Immer Session-Status prüfen

```php
// ✅ RICHTIG: Vor Session-Operationen prüfen
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('AZE_SESSION');
    session_start();
}
```

### 3. Guards für Definitionen

```php
// ✅ RICHTIG: Mit Guards
if (!defined('MY_CONST')) {
    define('MY_CONST', 'value');
}

if (!function_exists('my_function')) {
    function my_function() { }
}
```

### 4. Kein Closing Tag

```php
// ✅ RICHTIG: Keine closing ?> Tags
<?php
// ... code
// EOF (kein ?>)
```

---

## ❌ DON'Ts (NIEMALS tun!)

### 1. Session-Konfiguration nach session_start()

```php
// ❌ FALSCH: Nach session_start() konfigurieren
session_start();
session_name('NAME');  // ❌ Fatal Error!
ini_set('session.*');  // ❌ Fatal Error!
```

### 2. session_name() ohne Status-Prüfung

```php
// ❌ FALSCH: Blind aufrufen
session_name('AZE_SESSION');  // ❌ Kann fehlschlagen wenn Session aktiv!
```

### 3. Inkonsistente Session-Namen

```php
// ❌ FALSCH: Unterschiedliche Namen in verschiedenen APIs
// login.php
session_name('AZE_SESSION');
// time-entries.php
session_name('PHPSESSID');  // ❌ Session wird nicht gefunden!
```

### 4. Closing PHP Tags mit Whitespace

```php
// ❌ FALSCH: Closing tag + Whitespace
<?php
// code
?>


// ❌ Whitespace wird als Output gesendet!
```

### 5. Definitionen ohne Guards

```php
// ❌ FALSCH: Ohne Guards
define('MY_CONST', 'value');  // ❌ Fatal Error wenn bereits definiert!
function my_function() { }    // ❌ Fatal Error wenn bereits deklariert!
```

---

## 🔥 Häufigste Fehler & Sofort-Fixes

| Fehler | Sofort-Fix |
|--------|-----------|
| `session_name() cannot be changed when session is active` | session_name() nur wenn `session_status() !== PHP_SESSION_ACTIVE` |
| `ini_set(): Session ini settings cannot be changed` | Alle `ini_set('session.*')` nur wenn Session NICHT aktiv |
| `Unauthorized: No valid session found` | Session-Name in ALLEN APIs konsistent machen |
| `JSON.parse: unexpected end of data` | Alle closing `?>` Tags entfernen |
| `Constant already defined` | `if (!defined())` Guard hinzufügen |
| `Function already declared` | `if (!function_exists())` Guard hinzufügen |

---

## 📋 Pre-Commit Checklist

**Vor jedem Commit mit Session-Code:**
- [ ] Keine closing `?>` Tags in PHP-Dateien
- [ ] Session-Name konsistent in allen APIs
- [ ] `session_status()` geprüft vor `session_name()`
- [ ] Alle Session-INI-Settings in if-Block (nur wenn Session NICHT aktiv)
- [ ] Guards für alle Konstanten und Funktionen
- [ ] Getestet mit frischer Session (Inkognito)
- [ ] Getestet mit bestehender Session (normaler Browser)

---

## 🔧 Template für start_secure_session()

```php
function start_secure_session() {
    $migrate = null;
    $sessionWasActive = (session_status() === PHP_SESSION_ACTIVE);

    if ($sessionWasActive) {
        // Session bereits aktiv - keine Neu-Konfiguration
        $migrate = $_SESSION ?? null;
    } else {
        // Session noch nicht aktiv - jetzt konfigurieren
        session_name('AZE_SESSION');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // Migration falls nötig
    if (isset($migrate) && is_array($migrate)) {
        foreach (['user','created_at','last_activity'] as $k) {
            if (isset($migrate[$k]) && !isset($_SESSION[$k])) {
                $_SESSION[$k] = $migrate[$k];
            }
        }
    }
}
```

---

## 🆘 Bei Session-Problemen

1. **Lies SESSION_TROUBLESHOOTING_LESSONS_LEARNED.md** (vollständige Dokumentation)
2. **Folge dem Troubleshooting-Flowchart**
3. **Prüfe Backend-Logs**: `https://aze.mikropartner.de/api/test.html`
4. **Teste mit Minimal-Reproducer**
5. **NICHT** die Funktion deaktivieren - fixe den Root Cause!

---

**Für detaillierte Erklärungen siehe**: `SESSION_TROUBLESHOOTING_LESSONS_LEARNED.md`

# HAR-Analyse Guide - OAuth → Dashboard Flow Debugging

**Erstellt:** 2025-10-19
**Zweck:** Systematische Analyse des OAuth-Login-Flows via HAR-Export
**Status:** Production-Ready

---

## 🎯 Übersicht

Wenn das Dashboard nach dem OAuth-Login nicht lädt, ist eine HAR-Analyse (HTTP Archive) der effektivste Weg, den genauen Fehler zu identifizieren.

## 📋 Schritt-für-Schritt-Anleitung

### 1. Browser DevTools vorbereiten

1. **Browser öffnen** (Chrome/Edge empfohlen)
2. **F12** drücken → DevTools öffnen
3. **Network-Tab** öffnen
4. **Preserve Log** aktivieren (✅ Häkchen setzen!)
5. **Disable Cache** aktivieren (✅ Häkchen setzen!)
6. **Clear** klicken (Bisherige Requests löschen)

### 2. OAuth-Flow durchführen

1. **Öffnen:** `https://aze.mikropartner.de`
2. **"Mit Azure AD anmelden"** klicken
3. **Microsoft-Login** durchführen (Username + Passwort)
4. **Warten** bis:
   - Entweder: Dashboard lädt ✅
   - Oder: "Laden..." bleibt stehen ❌

### 3. HAR-Export erstellen

1. Im **Network-Tab**:
   - **Rechtsklick** auf beliebigen Request
   - **"Save all as HAR with content"** wählen
   - Speichern als: `aze-oauth-flow-[DATUM].har`

2. **SICHERHEIT:**
   - ⚠️ **HAR-Datei enthält Cookies und Session-Tokens!**
   - **NIEMALS** in Git committen oder öffentlich teilen!
   - Nach Analyse sofort löschen oder verschlüsseln

### 4. HAR-Datei analysieren

#### Option A: Online (Google HAR Analyzer)
1. Öffne: `https://toolbox.googleapps.com/apps/har_analyzer/`
2. HAR-Datei hochladen
3. Timeline & Waterfall prüfen

#### Option B: Manuell (mit Text-Editor)
Die HAR-Datei ist JSON-formatiert und kann mit jedem Text-Editor geöffnet werden.

---

## 🔍 Was zu prüfen ist

### **Erwarteter erfolgreicher Flow:**

```
1. GET / (200 OK)
   ↓
2. GET /assets/index-[hash].js (200 OK)
   ↓
3. GET /assets/index-[hash].css (200 OK)
   ↓
4. GET /api/auth-status.php (204 No Content) ← Erste kritische Prüfung!
   ↓
5. POST /api/login.php (200 OK mit JSON) ← Zweite kritische Prüfung!
   ↓
6. Dashboard lädt erfolgreich
```

### **Typische Fehler-Patterns:**

#### **Fehler 1: auth-status.php gibt 401 zurück**

```
GET /api/auth-status.php → 401 Unauthorized
```

**Ursache:** Session-Daten sind verloren gegangen oder Session-Name-Inkonsistenz
**Lösung:**
- Prüfen: Browser-Cookies (DevTools → Application → Cookies)
- Erwartung: Cookie namens `AZE_SESSION` (NICHT `PHPSESSID`!)
- Fix: Session-Name muss in **ALLEN** API-Dateien als **ERSTE ZEILE** gesetzt werden

#### **Fehler 2: login.php gibt 401 zurück**

```
POST /api/login.php → 401 Unauthorized
```

**Ursache:** auth-status.php war erfolgreich, aber login.php findet keine User-Daten
**Root Cause:** Session-Name-Inkonsistenz zwischen auth-callback.php und login.php
**Lösung:**
- `auth-callback.php` speichert in `PHPSESSID`
- `login.php` sucht in `AZE_SESSION`
- **Fix:** auth-callback.php muss `session_name('AZE_SESSION')` VOR allen `require_once` setzen!

#### **Fehler 3: login.php gibt 500 zurück**

```
POST /api/login.php → 500 Internal Server Error
```

**Ursache:** PHP-Fehler (Syntax, Datenbank-Connection, etc.)
**Lösung:**
- Server PHP-Error-Log prüfen (HostEurope Admin Panel)
- Häufigste Ursachen:
  - Datenbank-Connection fehlgeschlagen
  - SQL-Query-Fehler
  - `require_once` kann Datei nicht finden

#### **Fehler 4: login.php gibt leere Response zurück**

```
POST /api/login.php → 200 OK (aber kein JSON-Body!)
```

**Ursache:** PHP gibt keine Daten zurück (evtl. `echo` fehlt oder Output-Buffer-Problem)
**Lösung:**
- login.php prüfen: `echo json_encode($response);` am Ende?
- Output-Buffering korrekt? (`ob_start()` / `ob_end_clean()`)

#### **Fehler 5: Request hängt (Timeout)**

```
POST /api/login.php → (pending... pending... abort)
```

**Ursache:** PHP-Script läuft in Timeout oder Deadlock
**Lösung:**
- Datenbank-Query hängt? (z.B. Lock auf Tabelle)
- Infinite Loop im Code?
- Server überlastet?

---

## 🧪 Debug-Scripts verwenden

Nach HAR-Analyse und Fix-Implementierung:

### **Script 1: Session-Konsistenz-Test**

```bash
# Browser: Nach OAuth-Login aufrufen
https://aze.mikropartner.de/api/test-session-consistency.php
```

**Erwartetes Ergebnis:**
```json
{
  "status": "PASS",
  "message": "All session consistency tests passed!",
  "validation": {
    "errors": [],
    "warnings": []
  }
}
```

### **Script 2: Browser-Console-Logs**

Nach dem OAuth-Login:

1. **F12** → **Console-Tab** öffnen
2. Suche nach: `[AZE-API]` Prefix
3. Prüfe die Request/Response-Kette:

```
[AZE-API] Request: GET /auth-status.php
[AZE-API] Response: 204 No Content
[AZE-API] Request: POST /login.php
[AZE-API] Response: 200 OK
[AZE-API] Success: JSON data received { dataKeys: [...] }
```

**Falls Fehler:**
```
[AZE-API] API Error Response: { status: 401, statusText: "Unauthorized", ... }
```

---

## 📊 HAR-Datei-Struktur (JSON)

Die wichtigsten Felder:

```json
{
  "log": {
    "entries": [
      {
        "request": {
          "method": "GET",
          "url": "https://aze.mikropartner.de/api/auth-status.php",
          "headers": [...],
          "cookies": [
            { "name": "AZE_SESSION", "value": "..." }
          ]
        },
        "response": {
          "status": 204,
          "statusText": "No Content",
          "headers": [...],
          "content": {...}
        },
        "time": 123,  // Millisekunden
        "timings": {...}
      }
    ]
  }
}
```

### **Wichtige Felder zum Prüfen:**

1. **request.cookies**: Welche Cookies werden gesendet?
   - Erwartung: `AZE_SESSION`
   - Fehler: `PHPSESSID` oder kein Cookie!

2. **response.status**: HTTP-Statuscode
   - 204 = OK (für auth-status.php)
   - 200 = OK (für login.php)
   - 401 = Session-Problem
   - 500 = Server-Fehler

3. **response.content.text**: Response-Body
   - Bei login.php: JSON mit users, masterData, timeEntries, etc.
   - Bei Fehler: Error-Message oder leer

4. **timings.wait**: Server-Verarbeitungszeit
   - Über 3000ms = Verdacht auf Timeout/Performance-Problem

---

## 🚨 Häufigste Fehler-Szenarien & Lösungen

| Symptom | HAR-Befund | Root Cause | Fix |
|---------|-----------|-----------|-----|
| Dashboard bleibt bei "Laden..." | auth-status.php: 401 | Session verloren | Browser-Cookies löschen + neu anmelden |
| Dashboard bleibt bei "Laden..." | login.php: 401 | Session-Name-Inkonsistenz | auth-callback.php: session_name() VOR requires |
| Dashboard bleibt bei "Laden..." | login.php: 500 | PHP-Error | Server-Error-Log prüfen |
| Dashboard bleibt bei "Laden..." | login.php: 200 (empty) | Keine JSON-Response | login.php: echo json_encode() prüfen |
| Dashboard bleibt bei "Laden..." | login.php: timeout | Datenbank-Query hängt | Query-Performance optimieren |

---

## 📚 Weitere Ressourcen

- **SESSION_LOGIN_TROUBLESHOOTING.md** - Detaillierte Session-Fehler-Referenz
- **test-session-consistency.php** - Automatisierter Session-Test
- **AZURE_AD_REDIRECT_URI_FIX.md** - Azure AD Konfigurationsprobleme

---

## 🆘 Support

Falls HAR-Analyse keine Lösung bringt:

1. **HAR-Datei bereitstellen** (nach Anonymisierung!)
2. **Browser-Console-Screenshot** (F12 → Console)
3. **Server PHP-Error-Log** (HostEurope Admin Panel)
4. **test-session-consistency.php Output**

Mit diesen 4 Informationen kann das Problem in 90% der Fälle identifiziert werden.

---

**Version:** 1.0
**Status:** Production-Ready
**Maintenance:** Bei neuen OAuth-Problemen aktualisieren

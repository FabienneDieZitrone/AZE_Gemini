# End-to-End-Test: OAuth → Dashboard

**Erstellt:** 2025-10-19
**Zweck:** Vollständiger Test nach Session-Name-Fixes
**Status:** Test-Anleitung für manuelle Durchführung

---

## ⚠️ WICHTIG: Test-Vorbereitung

### **SCHRITT 0: Build & Deployment**

Bevor du den Test durchführst, müssen alle Änderungen deployed werden:

```bash
# 1. Frontend neu builden
cd /home/aios/projekte/aze-gemini/claude-container/projekt/build
npm run build

# 2. Deployment auf Server (via FTP/SFTP)
# - dist/assets/* → Server /assets/
# - api/* → Server /api/
# - index.html → Server /
# - api.ts → Wird zu JavaScript kompiliert und in assets/ gepackt

# 3. Server-Cache leeren (falls vorhanden)
# - PHP OpCache: php-fpm service restart
# - Browser-Cache: Hard Refresh (Ctrl+Shift+R)
```

**Alternativ (wenn Deployment-Script existiert):**
```bash
bash ~/projekte/aze-gemini/claude-container/projekt/deploy.sh
```

---

## 🧪 End-to-End-Test (Manuelle Durchführung)

### **Phase 1: Browser-Vorbereitung**

1. **Browser öffnen** (Chrome/Edge empfohlen)
2. **Private/Inkognito-Modus** aktivieren (wichtig für sauberen Test!)
3. **DevTools öffnen** (F12)
4. **Network-Tab** → **Preserve Log** aktivieren (✅)
5. **Console-Tab** bereithalten

### **Phase 2: Cookies löschen (KRITISCH!)**

⚠️ **Ohne diesen Schritt kann der Test falsch-positive Ergebnisse liefern!**

1. **F12** → **Application-Tab**
2. **Cookies** → `https://aze.mikropartner.de`
3. **Alle Cookies löschen**:
   - ✅ `AZE_SESSION` löschen (falls vorhanden)
   - ✅ `PHPSESSID` löschen (falls vorhanden)
   - ✅ Alle anderen Cookies löschen

### **Phase 3: OAuth-Login durchführen**

1. **Öffnen:** `https://aze.mikropartner.de`
2. **Erwartung:** Login-Seite wird angezeigt

3. **"Mit Azure AD anmelden"** klicken
4. **Erwartung:** Redirect zu `login.microsoftonline.com`

5. **Microsoft-Login** durchführen:
   - Username: `[DEINE_AZURE_AD_EMAIL]`
   - Passwort: `[DEIN_PASSWORT]`

6. **Erwartung nach Login:**
   - Redirect zurück zu `https://aze.mikropartner.de/api/auth-callback.php?code=...`
   - **Sofortiger Redirect** zu `https://aze.mikropartner.de/`
   - **Dashboard lädt:** "MP Arbeitszeiterfassung" Header sichtbar
   - **Timer-Component:** "Zeit starten" Button sichtbar
   - **Navigation:** "Arbeitszeiten anzeigen", "Zeit nachtragen", etc.

---

## ✅ Erfolgs-Kriterien

### **1. Browser-Console-Logs (F12 → Console)**

**Erwartete Ausgabe:**
```
[AZE-API] Request: GET /auth-status.php { isAuthCheck: true, ... }
[AZE-API] Response: 204 No Content { endpoint: "/auth-status.php", ... }
[AZE-API] Success: 204 No Content { endpoint: "/auth-status.php" }
[AZE-API] Request: POST /login.php { isAuthCheck: false, ... }
[AZE-API] Response: 200 OK { endpoint: "/login.php", contentType: "application/json", ... }
[AZE-API] Success: JSON data received { endpoint: "/login.php", dataKeys: [...], ... }
```

**Fehler-Indikatoren:**
```
❌ [AZE-API] Response: 401 Unauthorized
❌ [AZE-API] API Error Response: { status: 401, ... }
❌ [AZE-API] 401 Unauthorized - Redirecting to login
```

### **2. Network-Tab (F12 → Network)**

**Erwartete Requests (nach Login):**
```
✅ GET  /                                  → 200 OK
✅ GET  /assets/index-[hash].js           → 200 OK
✅ GET  /assets/index-[hash].css          → 200 OK
✅ GET  /api/auth-status.php              → 204 No Content
✅ POST /api/login.php                    → 200 OK (JSON Response)
```

**Fehler-Indikatoren:**
```
❌ GET  /api/auth-status.php  → 401 Unauthorized
❌ POST /api/login.php        → 401 Unauthorized
❌ POST /api/login.php        → 500 Internal Server Error
❌ POST /api/login.php        → 200 OK (aber leere Response!)
```

### **3. Application-Tab: Cookies (F12 → Application → Cookies)**

**Erwarteter Cookie:**
```
✅ Name:     AZE_SESSION
✅ Value:    [32-Zeichen Hex-String]
✅ Domain:   aze.mikropartner.de (OHNE führenden Punkt!)
✅ Path:     /
✅ Secure:   ✓ (Häkchen)
✅ HttpOnly: ✓ (Häkchen)
✅ SameSite: Lax
```

**Fehler-Indikatoren:**
```
❌ Cookie-Name: PHPSESSID (statt AZE_SESSION)
❌ Domain: .aze.mikropartner.de (mit führendem Punkt!)
❌ Kein Cookie vorhanden
```

### **4. Dashboard-Anzeige (Visuell)**

**Erwartete UI-Elemente:**
```
✅ Header: "MP Arbeitszeiterfassung"
✅ Benutzer-Info: "[NAME] (+/-X.XXh) - [DATUM]"
✅ Standort: "Erkannter Standort: [ORT]"
✅ Timer: "Zeit starten" Button
✅ Navigation: 5-6 Buttons (Arbeitszeiten, Zeit nachtragen, Dashboard, etc.)
```

**Fehler-Indikatoren:**
```
❌ "MP Zeiterfassung Laden..." bleibt stehen
❌ "Fehler beim Laden der Anwendungsdaten" wird angezeigt
❌ Leeres Dashboard (keine Buttons sichtbar)
```

---

## 🧪 Additional Validierungen

### **Test 1: Session-Konsistenz-Script**

Nach erfolgreichem Login:

```
1. Browser-Adresszeile: https://aze.mikropartner.de/api/test-session-consistency.php
2. Erwartetes Ergebnis: JSON mit "status": "PASS"
3. Prüfen: Keine Errors oder Warnings
```

**Beispiel-Output (Erfolg):**
```json
{
  "timestamp": "2025-10-19 15:30:00",
  "test_name": "Session Consistency Test",
  "status": "PASS",
  "message": "All session consistency tests passed!",
  "session": {
    "name": "AZE_SESSION",
    "has_user": true,
    "user_oid": "382fe473-...",
    "user_name": "Max Mustermann"
  },
  "cookies": {
    "has_aze_session": true,
    "has_phpsessid": false
  },
  "validation": {
    "errors": [],
    "warnings": [],
    "error_count": 0,
    "warning_count": 0
  }
}
```

**Beispiel-Output (Fehler):**
```json
{
  "status": "FAIL",
  "message": "Critical errors found - OAuth → Dashboard flow will likely fail!",
  "validation": {
    "errors": [
      "PHPSESSID cookie found - indicates session name was not set before session_start()",
      "Session name is 'PHPSESSID' but should be 'AZE_SESSION'"
    ],
    "error_count": 2
  }
}
```

### **Test 2: Logout & Re-Login**

Nach erfolgreichem ersten Login:

```
1. Klicke "Abmelden" Button
2. Erwartung: Redirect zur Login-Seite
3. Erneut einloggen (Azure AD)
4. Erwartung: Dashboard lädt wieder erfolgreich
```

### **Test 3: Browser-Neustart (Session-Persistenz)**

```
1. Dashboard erfolgreich geladen
2. Browser komplett schließen (nicht nur Tab!)
3. Browser neu öffnen
4. Navigiere zu: https://aze.mikropartner.de
5. Erwartung:
   - Entweder: Dashboard lädt direkt (Session noch gültig)
   - Oder: Login-Seite wird angezeigt (Session abgelaufen nach 24h)
```

---

## 🚨 Fehlerbehebung

### **Fehler 1: Dashboard lädt nicht (bleibt bei "Laden...")**

**Diagnose:**
```
1. F12 → Console → Suche nach "[AZE-API]" Logs
2. Identifiziere welcher Request fehlschlägt:
   - auth-status.php → 401?
   - login.php → 401/500?
3. F12 → Network → Request-Details anzeigen
```

**Fix:**
```
1. Browser-Cookies komplett löschen
2. Private/Inkognito-Modus verwenden
3. Test erneut durchführen
4. Falls weiterhin Fehler: HAR-Export erstellen (siehe HAR_ANALYSIS_GUIDE.md)
```

### **Fehler 2: "Session expired or invalid" nach Login**

**Ursache:** Session-Name-Inkonsistenz zwischen auth-callback.php und auth-status.php/login.php

**Fix:**
```
1. Prüfe Server-Dateien:
   - /api/auth-callback.php (Zeile 10): session_name('AZE_SESSION');
   - /api/auth-status.php (Zeile 12): session_name('AZE_SESSION');
   - /api/auth-start.php (Zeile 10): session_name('AZE_SESSION');

2. Alle session_name() Aufrufe MÜSSEN die ERSTE ausführbare Zeile sein!

3. Server-Dateien neu deployen

4. PHP OpCache leeren: sudo service php8.2-fpm restart
```

### **Fehler 3: Redirect-Loop (ständig zur Login-Seite)**

**Ursache:** auth-status.php gibt dauerhaft 401 zurück

**Diagnose:**
```
1. Test-Script aufrufen: /api/test-session-consistency.php
2. Prüfe Output: "status": "FAIL"?
3. Prüfe Errors: Session-Name-Problem?
```

**Fix:** Siehe "Fehler 2"

---

## 📊 Test-Protokoll (Ausfüllen nach Test)

```
=== OAUTH → DASHBOARD E2E-TEST ===

Datum: __________
Tester: __________
Browser: __________
Server: Production / Test (streiche nicht zutreffendes)

=== PHASE 1: Browser-Vorbereitung ===
[ ] Private/Inkognito-Modus aktiviert
[ ] DevTools geöffnet (F12)
[ ] Preserve Log aktiviert
[ ] Alle Cookies gelöscht

=== PHASE 2: OAuth-Login ===
[ ] Login-Seite lädt
[ ] "Mit Azure AD anmelden" funktioniert
[ ] Microsoft-Login erfolgreich
[ ] Redirect zu auth-callback.php
[ ] Redirect zu / (Dashboard)

=== PHASE 3: Dashboard-Anzeige ===
[ ] Header "MP Arbeitszeiterfassung" sichtbar
[ ] Benutzer-Info angezeigt
[ ] Timer-Component sichtbar
[ ] Navigation-Buttons sichtbar (5-6 Stück)

=== PHASE 4: Technische Validierung ===
[ ] Console: [AZE-API] Logs ohne Fehler
[ ] Network: auth-status.php → 204 No Content
[ ] Network: login.php → 200 OK (JSON)
[ ] Cookie: AZE_SESSION vorhanden
[ ] Cookie: PHPSESSID NICHT vorhanden
[ ] test-session-consistency.php: "status": "PASS"

=== PHASE 5: Zusätzliche Tests ===
[ ] Logout & Re-Login funktioniert
[ ] Browser-Neustart: Session persistent ODER Login-Seite

=== ERGEBNIS ===
[ ] TEST BESTANDEN ✅
[ ] TEST FEHLGESCHLAGEN ❌

Fehler (falls vorhanden):
_________________________________________________________________
_________________________________________________________________

Notizen:
_________________________________________________________________
_________________________________________________________________
```

---

## 📚 Weitere Ressourcen

- **HAR_ANALYSIS_GUIDE.md** - Systematische HAR-Analyse bei Fehlern
- **SESSION_LOGIN_TROUBLESHOOTING.md** - Detaillierte Session-Fehler-Referenz
- **test-session-consistency.php** - Automatisierter Session-Test

---

**Version:** 1.0
**Status:** Production-Ready
**Nächster Review:** Nach 10 erfolgreichen Tests

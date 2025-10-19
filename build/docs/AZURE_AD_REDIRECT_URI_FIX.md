# Azure AD Redirect-URI Problem - Lösung

## 🚨 **PROBLEM IDENTIFIZIERT**

Der OAuth-Flow bricht ab, weil **Azure AD nicht zu `/api/auth-callback.php` redirected!**

### **Symptome:**
- ✅ Azure AD Login funktioniert
- ✅ Benutzer authentifiziert sich erfolgreich
- ❌ Nach Login: Redirect zu `auth-callback.php` erfolgt NICHT
- ❌ Keine Session wird erstellt
- ❌ Dashboard bleibt bei "Laden..." hängen

### **Root Cause:**
Die **Redirect-URI in Azure AD** ist entweder:
1. Nicht konfiguriert
2. Falsch konfiguriert (z.B. ohne `/api/` Pfad)
3. Für einen falschen Application Type konfiguriert

## ✅ **LÖSUNG: Azure AD Redirect-URI korrekt konfigurieren**

### **Schritt 1: Azure Portal öffnen**
1. Gehe zu: https://portal.azure.com
2. Navigiere zu: **Azure Active Directory** → **App registrations**
3. Öffne die App: **AZE Gemini** (Client ID: `737740ef-8ab9-44eb-8570-5e3027ddf207`)

### **Schritt 2: Redirect-URIs prüfen**
1. Klicke auf: **Authentication** (linkes Menü)
2. Im Bereich **Platform configurations** → **Web**
3. Prüfe die **Redirect URIs**

### **Schritt 3: Korrekte URI eintragen**

**❌ FALSCH (häufige Fehler):**
```
https://aze.mikropartner.de
https://aze.mikropartner.de/
https://aze.mikropartner.de/auth-callback.php
http://aze.mikropartner.de/api/auth-callback.php    (HTTP statt HTTPS!)
```

**✅ KORREKT:**
```
https://aze.mikropartner.de/api/auth-callback.php
```

### **Schritt 4: Settings validieren**

Stelle sicher, dass:
- [x] **Platform**: "Web" ausgewählt
- [x] **Redirect URI**: Genau `https://aze.mikropartner.de/api/auth-callback.php`
- [x] **ID tokens**: Enabled (Häkchen gesetzt!)
- [x] **Access tokens**: Kann optional sein
- [x] **Implicit grant**: Disabled (wir nutzen Authorization Code Flow)

### **Schritt 5: Speichern & Testen**
1. Klicke auf **Save** oben in der Seite
2. Warte 1-2 Minuten (Azure AD braucht Zeit zum Propagieren)
3. Teste den Login-Flow neu:
   - Öffne: https://aze.mikropartner.de
   - Klicke: "Mit Azure AD anmelden"
   - Nach Azure-Login sollte automatisch Redirect zu `/api/auth-callback.php` erfolgen
   - Dashboard sollte laden

## 🔍 **ZUSÄTZLICHE PRÜFUNGEN**

### **Falls Problem weiterhin besteht:**

#### **1. Browser DevTools Network Tab prüfen:**
- "Preserve Log" aktivieren
- Kompletten Login-Flow durchführen
- **Suche nach:**
  - Request zu `login.microsoftonline.com` → sollte `302` zurückgeben
  - Redirect zu `aze.mikropartner.de/api/auth-callback.php?code=...`

#### **2. Azure AD Logs prüfen:**
- Azure Portal → **Azure Active Directory** → **Sign-in logs**
- Suche nach deinem Login-Versuch
- **Error Code prüfen**: AADSTS50011 = Falsche Redirect URI!

#### **3. Application Type prüfen:**
- Azure Portal → **App registration** → **Manifest**
- Suche nach: `"replyUrlsWithType"`
- Sollte enthalten:
```json
"replyUrlsWithType": [
    {
        "url": "https://aze.mikropartner.de/api/auth-callback.php",
        "type": "Web"
    }
]
```

## 📋 **DEBUG-CHECKLIST**

- [ ] Azure AD Redirect-URI = `https://aze.mikropartner.de/api/auth-callback.php`
- [ ] Platform = "Web" (nicht "Single Page Application"!)
- [ ] ID tokens = Enabled
- [ ] Änderungen gespeichert
- [ ] 2 Minuten gewartet
- [ ] Browser-Cache geleert
- [ ] Neuer Login-Versuch mit DevTools "Preserve Log"
- [ ] auth-callback.php erscheint im Network Tab
- [ ] Dashboard lädt erfolgreich

## 🎯 **ERWARTETES ERGEBNIS NACH FIX**

### **OAuth-Flow (korrekt):**
```
1. User → "Login" klicken
2. Redirect → https://login.microsoftonline.com/.../oauth2/v2.0/authorize
3. Azure AD → User authentifiziert
4. Azure AD → 302 Redirect zu: https://aze.mikropartner.de/api/auth-callback.php?code=XXX&state=YYY
5. auth-callback.php → Session erstellen → 302 Redirect zu: /
6. / → React App lädt
7. React App → api.checkAuthStatus() → 204 No Content (Session gültig!)
8. React App → api.loginAndGetInitialData() → Daten laden
9. Dashboard → Anzeige
```

### **Network Tab (nach Fix):**
```
GET / → 200 OK
GET /assets/index-[hash].js → 200 OK
GET /assets/index-[hash].css → 200 OK
GET /api/auth-status.php → 204 No Content    ← Jetzt korrekt!
POST /api/login.php → 200 OK (Daten)
```

## 📞 **SUPPORT**

Falls das Problem nach diesen Schritten weiterhin besteht:

1. **Azure AD Sign-in Logs exportieren** (letzten 10 Versuche)
2. **HAR-Export vom kompletten Login-Flow** (mit "Preserve Log")
3. **auth-callback.php Debug-Log prüfen:** `/api/callback-debug.log`

---
**Erstellt:** 2025-10-19
**Version:** 1.0
**Status:** Aktiv
**Problem:** auth-callback.php wird nicht aufgerufen
**Ursache:** Azure AD Redirect-URI falsch konfiguriert

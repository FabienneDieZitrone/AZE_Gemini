# Test-Authentifizierung für AZE Gemini

## Überblick
Da die direkte Azure AD Authentifizierung nicht ohne Browser möglich ist, wurde ein Test-Authentifizierungs-System implementiert.

## Verwendung

### 1. Test-Modus aktivieren
Die Test-Authentifizierung ist bereits in der `.env.production` aktiviert:
```
ENABLE_TEST_AUTH=true
```

### 2. Test-Login verwenden
Öffnen Sie in Ihrem Browser:
```
https://aze.mikropartner.de/api/test-auth.php
```

Dies erstellt eine Session für den Testbenutzer:
- Email: azetestclaude@mikropartner.de
- Name: Claude Test User
- Rolle: Mitarbeiter

### 3. Warnung
Wenn der Test-Modus aktiv ist, wird eine deutliche Warnung angezeigt:
- ⚠️ TEST-AUTHENTIFIZIERUNG AKTIV ⚠️
- Diese umgeht die normale Azure AD Authentifizierung
- NIEMALS in Produktion verwenden!

### 4. Test-Modus deaktivieren
Entfernen Sie die Zeile `ENABLE_TEST_AUTH=true` aus `.env.production` oder setzen Sie sie auf `false`.

## Sicherheit
- Der Test-Endpunkt prüft, ob `ENABLE_TEST_AUTH=true` gesetzt ist
- Ohne diese Einstellung wird der Zugriff mit HTTP 403 verweigert
- Die Session verhält sich wie eine normale Azure AD Session

## Alternative: Direkte Azure AD Anmeldung
Für echte Tests mit Azure AD:
1. Öffnen Sie https://aze.mikropartner.de
2. Klicken Sie auf "Mit Microsoft anmelden"
3. Melden Sie sich mit dem echten Azure AD Account an
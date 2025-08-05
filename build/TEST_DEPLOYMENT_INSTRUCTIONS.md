# Test-Deployment Anleitung für AZE Gemini Security Updates

## 🎯 Übersicht
Diese Anleitung beschreibt das Deployment und Testen der Sicherheits-Updates in der Testumgebung.

## 📁 Geänderte Dateien

### Neue Dateien:
- `api/auth-middleware.php` - Zentrale Autorisierungs-Middleware

### Aktualisierte Dateien:
- `api/time-entries.php` - Nutzt jetzt authorize_request()
- `api/users.php` - Nutzt jetzt authorize_request()
- `api/approvals.php` - Nutzt jetzt authorize_request()
- `api/settings.php` - Nutzt jetzt authorize_request()
- `api/masterdata.php` - Nutzt jetzt authorize_request()
- `api/history.php` - Nutzt jetzt authorize_request()
- `api/logs.php` - Nutzt jetzt authorize_request()

## 🚀 Deployment-Schritte

### 1. Vorbereitung
```bash
cd /app/projects/aze-gemini/build

# Deploy-Skript ausführbar machen (bereits erledigt)
chmod +x deploy-test.sh
```

### 2. Deployment zur Testumgebung
```bash
# Option A: Mit dem vorbereiteten Skript
./deploy-test.sh

# Option B: Manuell via FTP/SFTP
# 1. Verbinde zu wp10454681.server-he.de
# 2. Navigiere zu /www/aze/test/
# 3. Lade alle Dateien aus api/ hoch
```

### 3. Konfiguration in Testumgebung
- Stelle sicher, dass `.env` in der Testumgebung konfiguriert ist
- Prüfe Datenbankverbindung
- Verifiziere Session-Konfiguration

## 🧪 Test-Prozedur

### 1. Basis-Funktionalitätstest
- [ ] Login funktioniert
- [ ] Session wird korrekt erstellt
- [ ] Logout funktioniert

### 2. Autorisierungstests

#### Admin-Rolle testen:
- [ ] Kann alle Benutzer sehen (GET /api/users.php)
- [ ] Kann Benutzerrollen ändern (PATCH /api/users.php)
- [ ] Kann globale Einstellungen ändern (PUT /api/settings.php)
- [ ] Kann Logs einsehen (GET /api/logs.php)

#### Mitarbeiter-Rolle testen:
- [ ] Kann eigene Zeiteinträge sehen
- [ ] Kann keine Benutzerrollen ändern (403 erwartet)
- [ ] Kann globale Einstellungen lesen aber nicht ändern
- [ ] Kann keine Admin-Logs einsehen (403 erwartet)

#### Honorarkraft-Rolle testen:
- [ ] Kann nur eigene Daten sehen
- [ ] Kann keine anderen Benutzer sehen
- [ ] Eingeschränkter Zugriff auf Stammdaten

### 3. Fehlerbehandlung testen
- [ ] Unbekannte Endpoints geben 403 zurück
- [ ] Nicht erlaubte HTTP-Methoden geben 403 zurück
- [ ] Fehlende Authentifizierung gibt 401 zurück

## 📊 Test-Skript ausführen
```bash
# Test-Skript in Testumgebung hochladen
php test-auth-endpoints.php
```

## ✅ Erfolgskriterien

1. **Authentifizierung**: Alle Benutzer können sich einloggen
2. **Autorisierung**: Jede Rolle hat nur Zugriff auf erlaubte Endpoints
3. **Sicherheit**: Keine unbefugten Zugriffe möglich
4. **Performance**: Keine spürbaren Verzögerungen
5. **Logging**: Alle Autorisierungsentscheidungen werden geloggt

## 🐛 Fehlerbehandlung

### Häufige Probleme:
1. **500 Error**: Prüfe PHP-Fehlerlog
2. **403 Forbidden**: Korrekt, wenn Berechtigung fehlt
3. **401 Unauthorized**: Session abgelaufen oder ungültig

### Debug-Tipps:
- Prüfe `/var/log/apache2/error.log` oder PHP-Fehlerlog
- Aktiviere Debug-Logging in auth-middleware.php
- Teste mit verschiedenen Browsern/Sessions

## 📝 Checkliste vor Produktion

- [ ] Alle Tests in Testumgebung erfolgreich
- [ ] Keine kritischen Fehler in Logs
- [ ] Performance akzeptabel
- [ ] Rollback-Plan vorhanden
- [ ] Backup vor Deployment erstellt (via HostEurope)

## 🚨 Rollback-Prozedur

Falls Probleme auftreten:
1. Alte Version der API-Dateien wiederherstellen
2. Cache leeren
3. Sessions invalidieren falls nötig
4. Monitoring auf Fehler prüfen

## 📞 Support

Bei Problemen:
- Prüfe zuerst die Logs
- Dokumentiere genau welcher Endpoint/Rolle betroffen ist
- Erstelle Screenshot/Fehlermeldung
- Kontaktiere Entwicklungsteam mit Details
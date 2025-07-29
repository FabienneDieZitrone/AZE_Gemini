# 📤 Manuelle FTP Deployment Anleitung

## Dateien zum Upload

Die folgenden PHP-Dateien befinden sich im Verzeichnis `deploy_20250728_171752/api/`:

1. **error-handler.php** - Zentrales Error Handling für alle APIs
2. **structured-logger.php** - Strukturiertes Logging mit Rotation
3. **security-headers.php** - Security Headers (CSP, HSTS, etc.)
4. **health.php** - Health Check Endpoint
5. **login.php** - Aktualisiert mit Error Handling
6. **validation.php** - Input Validation (falls geändert)

## FTP Upload Schritte

### 1. FTP Client öffnen (z.B. FileZilla)

### 2. Verbindungsdetails:
- **Server**: `wp10454681.server-he.de`
- **Benutzer**: `10454681-ftpaze`
- **Passwort**: ***REDACTED***
- **Port**: 21
- **Protokoll**: FTP mit TLS/SSL

### 3. Upload durchführen:
1. Navigiere zu `/aze/api/`
2. Lade alle PHP-Dateien aus `deploy_20250728_171752/api/` hoch
3. Erstelle das Verzeichnis `/aze/logs/` falls nicht vorhanden

## Nach dem Upload testen

### 1. Health Check:
```bash
curl https://aze.mikropartner.de/api/health.php
```

Erwartete Antwort:
```json
{
  "status": "healthy",
  "timestamp": "2025-07-28T...",
  "checks": {
    "database": {"status": "healthy"},
    "session": {"status": "healthy"},
    "filesystem": {"status": "healthy"},
    "php_extensions": {"status": "healthy"}
  }
}
```

### 2. Error Handling testen:
```bash
# Ungültiger Request
curl -X DELETE https://aze.mikropartner.de/api/login.php
```

Sollte strukturierten Fehler zurückgeben:
```json
{
  "success": false,
  "error": "Method not allowed"
}
```

### 3. Logs prüfen:
Die Logs sollten unter `/aze/logs/app-2025-07-28.log` erscheinen.

## Features die deployed werden:

### Error Handling (Issue #2)
- Konsistente Fehlerformate
- Recovery-Strategien
- Keine Stack Traces in Production

### Structured Logging (Issue #4)
- JSON-formatierte Logs
- Automatische Rotation
- Request-ID Tracking

### Security Headers
- Content Security Policy
- HSTS (Force HTTPS)
- XSS Protection
- Frame Options

### Health Check
- System-Status Monitoring
- Database Connection Check
- PHP Extensions Check

## Wichtige Hinweise

- Die `login.php` wird überschrieben (Backup empfohlen)
- Logs-Verzeichnis muss Schreibrechte haben (755)
- Alle anderen APIs funktionieren weiterhin normal
- Frontend-Änderungen sind NICHT in diesem Deployment

## Troubleshooting

Falls Probleme auftreten:
1. Prüfe die Dateiberechtigungen (644 für PHP-Dateien)
2. Stelle sicher, dass `/aze/logs/` existiert und beschreibbar ist
3. Teste den Health Check Endpoint
4. Prüfe die Server Error Logs

---

**Deployment Package**: `deploy_20250728_171752/`
**Ziel**: https://aze.mikropartner.de/api/
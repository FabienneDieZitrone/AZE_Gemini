# 📋 **API-Dokumentation (PHP Endpoints)**

## Authentifizierung (Azure AD OAuth2):
- `POST /api/auth-start.php` - Login initiieren → Weiterleitung zu Azure AD
- `GET /api/auth-callback.php` - OAuth2 Callback (Token Exchange)
- `GET /api/auth-status.php` - Session-Status prüfen
- `POST /api/auth-logout.php` - Logout und Session beenden
- `GET /api/auth-oauth-client.php` - OAuth2 Client-Konfiguration

## Zeiterfassung:
- `GET /api/time-entries.php` - Zeiteinträge abrufen
- `POST /api/time-entries.php` - Neue Zeiterfassung
- `PUT /api/time-entries.php` - Zeiteintrag bearbeiten
- `DELETE /api/time-entries.php` - Zeiteintrag löschen

## Genehmigungen:
- `GET /api/approvals.php` - Pending Approvals
- `POST /api/approvals.php` - Approval Request erstellen
- `PUT /api/approvals.php` - Approval verarbeiten

## Stammdaten:
- `GET /api/users.php` - Benutzer abrufen
- `GET /api/masterdata.php` - Standorte und Settings

## 🆕 Geplante APIs (v0.8):
- `GET /api/reports.php` - Berichte und Analytics
- `POST /api/export.php` - PDF/Excel Export
- `GET /api/notifications.php` - Push Notifications
# AZE_Gemini - Arbeitszeiterfassung Project Guide

## ⚠️ KRITISCHER HINWEIS: Dokumentation vs. Realität

**WICHTIG**: Die Dokumentation in `/app/meta/` beschreibt eine geplante .NET Windows Forms Anwendung, aber die **tatsächliche Implementierung** in `/app/build/` ist eine **React/PHP Web-Anwendung**!

## Projekt-Überblick

### Tatsächliche Implementierung:
- **Frontend**: React 18 + TypeScript + Vite
- **Backend**: PHP REST APIs  
- **Datenbank**: MySQL (Produktions-DB: db10454681-aze)
- **Status**: v1.0 - PRODUKTIV DEPLOYED auf https://aze.mikropartner.de
- **Standort**: `/app/build/` (echte Implementierung)
- **Authentifizierung**: Microsoft Azure AD OAuth2 (vollständig funktionsfähig)

### Veraltete Planung (NICHT implementiert):
- Framework: .NET 8.0 mit C# 12.0
- UI: Windows Forms
- Standort: `/app/meta/` (nur Dokumentation, kein Code!)

## Schnellstart für Entwicklung

### 1. Web-App lokal starten:
```bash
cd /app/build
npm install                         # Dependencies installieren
npm run dev                        # Development Server starten → http://localhost:5173
```

### 2. Backend APIs (falls PHP Server benötigt):
```bash
# PHP Development Server für APIs
cd /app/build
php -S localhost:8000              # APIs verfügbar unter http://localhost:8000/api/
```

### 3. Production Build:
```bash
cd /app/build
npm run build                      # Erstellt dist/ Ordner für Deployment
npm run preview                    # Testet Production Build
```

## Verzeichnisstruktur (Tatsächlich)

```
/app/
├── build/                          # ✅ ECHTE IMPLEMENTIERUNG
│   ├── src/                        # React Frontend
│   │   ├── components/             # UI Komponenten
│   │   │   ├── common/             # Wiederverwendbare Komponenten
│   │   │   └── modals/             # Dialog-Komponenten
│   │   ├── views/                  # Hauptansichten/Seiten
│   │   │   ├── DashboardView.tsx   # Haupt-Dashboard
│   │   │   ├── TimeSheetView.tsx   # Zeiterfassung
│   │   │   ├── ApprovalView.tsx    # Genehmigungen
│   │   │   └── MasterDataView.tsx  # Stammdaten
│   │   ├── utils/                  # Hilfsfunktionen
│   │   └── types.ts                # TypeScript Typen
│   ├── api/                        # PHP Backend
│   │   ├── db.php                  # Datenbankverbindung
│   │   ├── auth-*.php              # Authentifizierung
│   │   ├── time-entries.php        # Zeiterfassung API
│   │   ├── approvals.php           # Genehmigungen API
│   │   └── users.php               # Benutzerverwaltung API
│   ├── package.json                # Node.js Dependencies
│   ├── schema.sql                  # MySQL Datenbankschema
│   └── index.html                  # Web Entry Point
├── meta/                           # ❌ VERALTETE .NET DOKUMENTATION
├── Configuration/                  # Datenbank-Setup Scripts
└── CLAUDE.local.md                 # Diese Datei
```

## Implementierte Features

### ✅ Frontend (React):
- **Dashboard**: Zeiterfassung mit Start/Stop Buttons
- **Authentifizierung**: Login-System mit Session-Management
- **Zeitübersicht**: Filterbare Zeiteinträge mit Kalender
- **Genehmigungsworkflow**: Manager-Approval für Zeitänderungen
- **Stammdaten**: Benutzer- und Standortverwaltung
- **Export**: PDF-Generation mit jsPDF
- **Responsive Design**: Mobile-optimierte Oberfläche
- **Change History**: Audit-Trail aller Änderungen

### ✅ Backend (PHP):
- **REST APIs**: JSON-basierte Schnittstellen
- **Datenbankzugriff**: MySQLi mit Prepared Statements
- **Session-Management**: Sichere Benutzer-Sessions
- **OAuth2 Integration**: Azure AD Token Exchange (funktionsfähig)
- **Error Handling**: Strukturierte Fehlerbehandlung
- **CORS Support**: Cross-Origin Request Headers
- **Environment Config**: .env Datei für sichere Credentials

### ✅ Datenbank (MySQL):
- **Produktions-DB**: db10454681-aze @ vwp8374.webpack.hosteurope.de
- **User Management**: Rollen und Berechtigungen
- **Time Tracking**: Arbeitszeiterfassung mit Audit-Trail
- **Approval System**: Genehmigungsworkflow
- **Master Data**: Standorte und Einstellungen

## Sicherheitshinweise ✅

### PROBLEM BEHOBEN:
```php
// Credentials jetzt sicher in .env Datei
// config.php lädt automatisch aus .env
$config = Config::load();
$servername = Config::get('database.host');
$username = Config::get('database.username');
$password = Config::get('database.password');
```

### Sicherheitsmaßnahmen implementiert:
1. ✅ **Environment Variables**: `.env` Datei für alle Credentials
2. ✅ **OAuth2 Integration**: Azure AD Client Secret sicher geladen
3. ✅ **Config Management**: Zentrale config.php für alle Settings
4. ✅ **Gitignore**: .env Datei ausgeschlossen von Git
5. ✅ **Session Security**: HTTP-only Cookies und CSRF-Schutz

## Entwicklungsrichtlinien

### React/TypeScript Standards:
- Funktionale Komponenten mit Hooks verwenden
- Strikte TypeScript-Typisierung
- Props-Interfaces definieren
- Custom Hooks für Logic-Wiederverwendung

### PHP Standards:
- PSR-12 Coding Style
- Prepared Statements für DB-Queries
- Strukturierte JSON-Responses
- Error-Logging implementieren

### Git Workflow:
```bash
# Standard Workflow
git add .
git commit -m "Beschreibung der Änderungen"
git push origin main

# Mit Token (bereits konfiguriert)
# Remote: https://FabienneDieZitrone:TOKEN@github.com/FabienneDieZitrone/AZE_Gemini.git
```

## API-Dokumentation (PHP Endpoints)

### Authentifizierung (Azure AD OAuth2):
- `POST /api/auth-start.php` - Login initiieren → Weiterleitung zu Azure AD
- `GET /api/auth-callback.php` - OAuth2 Callback (Token Exchange)
- `GET /api/auth-status.php` - Session-Status prüfen
- `POST /api/auth-logout.php` - Logout und Session beenden
- `GET /api/auth-oauth-client.php` - OAuth2 Client-Konfiguration

### Zeiterfassung:
- `GET /api/time-entries.php` - Zeiteinträge abrufen
- `POST /api/time-entries.php` - Neue Zeiterfassung
- `PUT /api/time-entries.php` - Zeiteintrag bearbeiten
- `DELETE /api/time-entries.php` - Zeiteintrag löschen

### Genehmigungen:
- `GET /api/approvals.php` - Pending Approvals
- `POST /api/approvals.php` - Approval Request erstellen
- `PUT /api/approvals.php` - Approval verarbeiten

### Stammdaten:
- `GET /api/users.php` - Benutzer abrufen
- `GET /api/masterdata.php` - Standorte und Settings

## Häufige Entwicklungsaufgaben

### Neue React-Komponente erstellen:
```typescript
// /app/build/src/components/MyComponent.tsx
import React from 'react';

interface MyComponentProps {
  title: string;
  onAction: () => void;
}

export const MyComponent: React.FC<MyComponentProps> = ({ title, onAction }) => {
  return (
    <div>
      <h2>{title}</h2>
      <button onClick={onAction}>Action</button>
    </div>
  );
};
```

### Neue PHP API erstellen:
```php
<?php
// /app/build/api/my-endpoint.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("SELECT * FROM my_table WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}
?>
```

## Deployment

### Development:
```bash
cd /app/build
npm run dev                        # Frontend: http://localhost:5173
php -S localhost:8000              # Backend: http://localhost:8000
```

### Production (DEPLOYED):
✅ **Live-System**: https://aze.mikropartner.de
✅ **Deployment-Datum**: 25.07.2025
✅ **Server**: HostEurope (wp10454681.server-he.de)
✅ **FTP-Zugang**: ftp10454681-aze3 (erfolgreich getestet)
✅ **Datenbank**: db10454681-aze @ vwp8374.webpack.hosteurope.de
✅ **Azure AD**: Vollständig konfiguriert und funktionsfähig
✅ **Deployment-Dokumentation**: `/app/deployment/SUCCESSFUL_FTP_DEPLOYMENT.md`

```bash
# Deployment bereits erfolgt:
cd /app/build
npm run build                      # ✅ Build erstellt
# ✅ dist/ Ordner auf Web-Server uploadiert
# ✅ PHP-Dateien in Webroot kopiert
# ✅ .env Datei mit Credentials konfiguriert
# ✅ Azure AD OAuth2 Integration getestet
```

## Troubleshooting

### Häufige Probleme:

1. **"npm install" schlägt fehl**:
   ```bash
   node --version    # Node.js 18+ erforderlich
   npm cache clean --force
   rm -rf node_modules package-lock.json
   npm install
   ```

2. **PHP APIs nicht erreichbar**:
   - MySQLi Extension aktiviert?
   - Datenbankverbindung korrekt?
   - CORS-Header gesetzt?

3. **React Build-Fehler**:
   ```bash
   npm run build    # TypeScript-Fehler anzeigen
   npx tsc --noEmit # Type-Check ohne Build
   ```

## Migration zu .NET (falls gewünscht)

Falls die ursprünglich geplante .NET-Anwendung gewünscht ist:

### 1. Analyse der Web-App:
- React-Komponenten zu Windows Forms Designs mappen
- PHP-APIs zu .NET Web API portieren
- MySQL-Schema für Entity Framework vorbereiten

### 2. .NET Projekt erstellen:
```bash
dotnet new sln -n Arbeitszeiterfassung
dotnet new winforms -n Arbeitszeiterfassung.UI
dotnet new webapi -n Arbeitszeiterfassung.API
dotnet new classlib -n Arbeitszeiterfassung.Core
```

### 3. Schrittweise Migration:
- Backend APIs zuerst (.NET Web API)
- Frontend parallel (Windows Forms)
- Datenbank-Migration (EF Core)
- Testing und Deployment

## Nächste Entwicklungsschritte

### Priorität 1 - Kritisch:
1. ✅ **Security Fix**: DB-Credentials in Environment Variables (ERLEDIGT)
2. ✅ **OAuth2 Integration**: Azure AD Token Exchange (ERLEDIGT)
3. **Error Handling**: Robuste Fehlerbehandlung
4. **Testing**: Unit Tests für kritische Funktionen
5. **Logging**: Structured Logging implementieren

### Priorität 2 - Features:
1. **PWA**: Service Worker für Offline-Funktionalität
2. **Push Notifications**: Für Genehmigungen
3. **Advanced Filtering**: Erweiterte Suchfunktionen
4. **Bulk Operations**: Massenbearbeitung von Zeiteinträgen

### Priorität 3 - Optimierung:
1. **Performance**: Code-Splitting und Lazy Loading
2. **Monitoring**: Application Performance Monitoring
3. **CI/CD**: Automated Testing und Deployment
4. **Mobile App**: React Native Version

## Wichtige Erkenntnisse für Entwickler

1. **Ignoriere `/app/meta/` Dokumentation** - beschreibt nicht existierendes .NET Projekt
2. **Arbeite ausschließlich mit `/app/build/`** - hier ist die echte Anwendung
3. ✅ **Web-App ist PRODUKTIV** - v1.0 deployed auf https://aze.mikropartner.de
4. ✅ **Sicherheitsprobleme behoben** - Credentials in .env, OAuth2 funktionsfähig
5. **React/PHP Skills erforderlich** - nicht .NET/C#
6. ✅ **Azure AD Integration** - Microsoft OAuth2 vollständig implementiert

---

**Status**: PRODUKTIV DEPLOYED (v1.0)  
**Live-URL**: https://aze.mikropartner.de  
**Technologie**: React 18 + TypeScript + PHP + MySQL + Azure AD  
**GitHub**: https://github.com/FabienneDieZitrone/AZE_Gemini.git  
**Deployment**: 25.07.2025 - Vollständig funktionsfähig  
**Version**: 1.0
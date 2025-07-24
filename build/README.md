# AZE_Gemini Web-Anwendung

## Status: FUNKTIONSFÄHIGE BETA v0.5

Diese Web-Anwendung ist die **tatsächliche Implementierung** der Arbeitszeiterfassung (nicht die geplante .NET Anwendung!).

## Schnellstart

### Development Server:
```bash
npm install
npm run dev    # → http://localhost:5173
```

### Production Build:
```bash
npm run build  # → dist/ Ordner
npm run preview # Test Production Build
```

## Technologie-Stack

- **Frontend**: React 18 + TypeScript + Vite
- **Backend**: PHP REST APIs (api/ Ordner)
- **Datenbank**: MySQL (schema.sql)
- **Authentication**: Microsoft Azure AD OAuth2 (BFF Pattern)
- **PDF Export**: jsPDF + AutoTable
- **Build Tool**: Vite 6.3.5

## Sicherheit ✅

- **Environment Variables**: DB-Credentials in .env (nicht im Code!)
- **Prepared Statements**: SQL Injection Schutz
- **HTTP-only Cookies**: Sichere Session-Verwaltung
- **CORS Protection**: Konfigurierte Cross-Origin Headers
- **npm audit**: 0 Sicherheitslücken (gefixt am 2025-07-24)

## Hauptfeatures

### Zeiterfassung:
- Real-time Start/Stop Timer
- Automatische Überstunden-Warnung (8h)
- Location-basierte Erfassung
- Persistent Timer State

### Workflows:
- Drei-Stufen Genehmigungsprozess
- Role-based Access Control (5 Rollen)
- Vollständige Audit-Historie
- Manager-Dashboard mit Analytics

### Export & Reporting:  
- PDF Export mit Corporate Design
- CSV Export (deutsch formatiert)
- Soll/Ist Vergleiche
- Filterbare Zeiträume

## API-Endpoints

**Vollständige Dokumentation**: [`API_DOCUMENTATION.md`](API_DOCUMENTATION.md)

### Hauptkategorien:
- **Authentication**: `/api/auth-*.php` (4 Endpoints)
- **Time Entries**: `/api/time-entries.php` (2 Methods)  
- **Approvals**: `/api/approvals.php` (3 Methods)
- **User Management**: `/api/users.php` (2 Methods)
- **Master Data**: `/api/masterdata.php` (2 Methods)
- **Settings**: `/api/settings.php` (2 Methods)
- **History**: `/api/history.php` + `/api/logs.php`

## Architektur

### Frontend (React):
```
src/
├── components/
│   ├── common/     # LoadingSpinner, ThemeToggle
│   └── modals/     # EditEntry, ConfirmDelete, etc.
├── views/          # 8 Hauptansichten
├── utils/          # time.ts, aggregate.ts, export.ts
└── types.ts        # TypeScript Definitionen
```

### Backend (PHP):
```  
api/
├── auth-*.php      # OAuth2 Flow (5 Dateien)
├── db.php          # Sichere DB-Verbindung
├── *.php           # REST Endpoints
└── auth_helpers.php # Gemeinsame Auth-Funktionen
```

## Development Setup

### Prerequisites:
- Node.js 18+
- PHP 8+
- MySQL/MariaDB
- Web-Server (Apache/Nginx)

### Environment:
```bash
# .env Datei erstellen:
cp .env.example .env
# DB-Credentials eintragen
```

### Dependencies:
```bash
npm install              # React Dependencies
composer install        # PHP Dependencies (falls composer.json existiert)
```

## Deployment

### Web-Server Setup:
1. `npm run build` → dist/ Ordner
2. dist/ Inhalte in Web-Root kopieren  
3. PHP-Dateien aus api/ Ordner bereitstellen
4. MySQL Datenbank mit schema.sql einrichten
5. .env Datei mit Produktions-Credentials

### Datenbank:
```sql
-- Import Schema:
mysql -u username -p database_name < schema.sql

-- Produktions-DB:
Host: vwp8374.webpack.hosteurope.de  
DB: db10454681-aze
```

## Performance

- **Vite Build**: Optimierte ES-Module mit Tree-Shaking
- **TypeScript**: Compile-time Optimierungen  
- **React**: Functional Components mit Hooks
- **PHP**: MySQLi mit Prepared Statements
- **Caching**: Browser-Caching für statische Assets

## Support

- **Hauptdokumentation**: [`../CLAUDE.local.md`](../CLAUDE.local.md)
- **GitHub**: https://github.com/FabienneDieZitrone/AZE_Gemini.git
- **Issues**: GitHub Issues für Bug-Reports

---

**⚠️ Wichtig**: Diese Web-App ersetzt die ursprünglich geplante .NET Windows Forms Anwendung. Die Dokumentation in `/meta/` beschreibt das alte Konzept!

**Version**: Beta v0.5  
**Status**: Produktiv einsetzbar  
**Letztes Update**: 2025-07-24
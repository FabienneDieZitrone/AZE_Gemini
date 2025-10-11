# AZE_Gemini - Arbeitszeiterfassungssystem

## 🚀 Status: PRODUKTIV DEPLOYED (v1.0)

**Live-System**: https://aze.mikropartner.de  
**Deployment**: 25.07.2025 - Vollständig einsatzbereit mit Azure AD Integration

## Schnellstart

### Web-Anwendung starten:
```bash
cd build/
npm install
npm run dev    # → http://localhost:5173
```

### Technologie-Stack:
- **Frontend**: React 18 + TypeScript + Vite
- **Backend**: PHP 8+ REST APIs  
- **Datenbank**: MySQL (db10454681-aze)
- **Authentifizierung**: Microsoft Azure AD OAuth2

## Verzeichnisstruktur

```
/app/
├── build/                    # ✅ FUNKTIONSFÄHIGE WEB-APP
│   ├── src/                  # React Frontend  
│   ├── api/                  # PHP Backend APIs
│   ├── package.json          # Node.js Dependencies
│   └── schema.sql            # Datenbankschema
├── meta/                     # Projektdokumentation
├── Configuration/            # Datenbank-Setup
├── CLAUDE.local.md          # Entwickler-Guide
└── README.md                # Diese Datei
```

## Features (Implementiert)

- ✅ **Zeiterfassung**: Start/Stop mit Real-time Timer
- ✅ **Dashboard**: Übersicht und Analytics  
- ✅ **Genehmigungsworkflow**: Manager-Approval System
- ✅ **Stammdaten**: Benutzer- und Standortverwaltung
- ✅ **Export**: PDF und CSV Export
- ✅ **Audit-Trail**: Vollständige Änderungshistorie
- ✅ **Responsive Design**: Mobile-optimiert

## Sicherheit

- 🔒 Microsoft Azure AD Integration
- 🔒 Environment Variables für DB-Credentials  
- 🔒 SQL Injection Schutz (Prepared Statements)
- 🔒 HTTP-only Cookies für Sessions

## Entwicklung

**Hauptdokumentation**: [`CLAUDE.local.md`](CLAUDE.local.md)  
**API-Dokumentation**: [`build/API_DOCUMENTATION.md`](build/API_DOCUMENTATION.md)

### Commands:
```bash
# Development
cd build && npm run dev

# Production Build  
cd build && npm run build

# Security Updates
npm audit fix
```

## Projekt-Geschichte

- **Ursprünglich geplant**: .NET Windows Forms (nicht implementiert)
- **Tatsächlich umgesetzt**: React/PHP Web-Anwendung
- **Status**: v1.0 - Produktiv deployed auf https://aze.mikropartner.de
- **Deployment**: 25.07.2025 - Vollständige Azure AD Integration funktionsfähig

## Support

GitHub Repository: https://github.com/FabienneDieZitrone/AZE_Gemini.git

**Für Entwickler**: Lesen Sie zuerst [`CLAUDE.local.md`](CLAUDE.local.md) für vollständige Setup-Anweisungen!
# MP Arbeitszeiterfassung

## Release v2025-10-07 – Approvals + Globale Einstellungen

- Approvals: Korrekte Pending/Alle-Filterung (GET), Transaktionen bei POST (kein Datenverlust), Login‑Payload liefert nur echte Pending‑Anträge.
- Standort: IP→Standort‑Zuordnung pflegbar; Standort wird bei Start/Stop erzwungen (keine „Web“-Defaults mehr).
- Globale Einstellungen: Stammliste inline editierbar, zusätzlicher Speichern‑Button, IP‑Zuordnung validiert (nur Stammlisten‑Namen), Tabellen untereinander.
- Infra: `.htaccess` Symlink‑Fix; Deploy synchronisiert index.html und Hash‑Assets; leere Seite (fehlende Assets) behoben.

Weitere Details siehe `build/docs/CONTINUATION_NOTES_2025-10-09.md`.

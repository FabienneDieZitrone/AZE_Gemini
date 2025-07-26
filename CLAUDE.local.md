# AZE_Gemini - Arbeitszeiterfassung Project Guide

## ⚠️ KRITISCHER HINWEIS: Dokumentation vs. Realität

**WICHTIG**: Die Dokumentation in `/app/meta/` beschreibt eine geplante .NET Windows Forms Anwendung, aber die **tatsächliche Implementierung** in `/app/build/` ist eine **React/PHP Web-Anwendung**!

## 🚀 **AKTUELLER STATUS (26.07.2025)**

### **Live-System**: https://aze.mikropartner.de ✅
- **Version**: v0.1 ALPHA → **Weiterentwicklung zu v1.0 Production Ready**
- **Deployment**: Basic FTP deployment erfolgreich
- **GitHub Issues**: **28 Issues** für strukturierte Weiterentwicklung (erstellt 25.-26.07.2025)
- **Entwicklungsstand**: 2-Tage-Projekt, funktionale Basis vorhanden
- **Timeline**: Projekt gestartet 25.07.2025, Issues systematisch erstellt

### **🔴 KRITISCHE ERKENNTNISSE:**

#### **Issue #1 - DATENVERLUST-BUG mit Server-First Lösung:**
- **Problem identifiziert**: Datenverlust bei Logout (NICHT Auto-Stop)
- ✅ Zeit läuft korrekt weiter (gewünschtes Verhalten)
- 💡 **Lösung**: DB-Status 'running' + Client-Sync → Kein Datenverlust möglich
- **Server-First**: Zeit sofort in DB, Multi-Device Support automatisch

#### **Security-Critical (Issues #19/20/28):**
- ✅ **DB-Password Security**: Environment Variables implementiert (26.07.2025)
- ✅ **Production Error Display**: Deaktiviert in allen PHP APIs  
- ✅ **OAuth Client Secret**: Sichere Fallback-Mechanismen implementiert
- ⚠️ **Input-Validation**: Noch nicht implementiert in API-Endpoints
- 📋 **Penetrationstests**: Erforderlich für Production Ready

## Projekt-Überblick

### Tatsächliche Implementierung:
- **Frontend**: React 18 + TypeScript + Vite
- **Backend**: PHP REST APIs  
- **Datenbank**: MySQL (Produktions-DB: db10454681-aze)
- **Status**: **v1.0 Live → Strategische Weiterentwicklung**
- **Standort**: `/app/build/` (echte Implementierung)
- **Authentifizierung**: Microsoft Azure AD OAuth2 (vollständig funktionsfähig)

### Veraltete Planung (NICHT implementiert):
- Framework: .NET 8.0 mit C# 12.0 | UI: Windows Forms
- Standort: `/app/meta/` (nur Dokumentation, kein Code!)

## 📊 **STRATEGISCHE ROADMAP (Issue #23)**

### **Investment**: 53.000€ → **ROI**: 38.133% ⭐
- Entwicklungszeit: 350h × 80€/h = 28.000€
- Security-Audit: 8.000€ | Übersetzungen: 12.000€ | Legal: 5.000€
- **Erwarteter Nutzen**: 20.265.000€ (DSGVO-Bußgeld-Vermeidung + Expansion)

### **4 Milestones bis v1.0 Production Ready:**

#### **v0.6 - Security & Compliance** (bis 10.08.2025)
- Issue #1: Datenverlust-Bug (Logout-Warnung + Zwischenspeichern)
- Issue #2-4: Error Handling, Unit Tests, Structured Logging
- Issue #10: DSGVO-konforme automatische Datenlöschung
- Issue #13: DSGVO-Reiter für Transparenz

#### **v0.7 - Security Hardening** (bis 15.08.2025)
- Issue #19: Security-Analysen + Penetrationstests etablieren
- Issue #20: Code-Review-Findings beheben (db.php hardcoded password!)

#### **v0.8 - Compliance & i18n** (bis 30.08.2025)
- Issue #21: Legal-Tiefenanalyse (DSGVO, ArbZG, GoBD)
- Issue #22: Mehrsprachigkeit (16 Sprachen, RTL-Support)

#### **v1.0 - Production Ready** (bis 01.09.2025)
- Issue #5: UI-Redesign mit Tab-Navigation
- Issue #6: Pausenwecker mit ArbZG-Compliance
- Issue #12: Dokumentations-Reiter | Issue #14: Admin-Reiter
- Issue #15: Live-Arbeitszeit im Header

## 🔒 **SICHERHEITSHINWEISE - STATUS 26.07.2025**

### ✅ **BEHOBENE SICHERHEITSPROBLEME:**

```php
// ✅ BEHOBEN: Environment Variables implementiert
$config = Config::load();
$password = Config::get('db.password');  // ← Sicher aus .env

// ✅ BEHOBEN: Production Error Display deaktiviert
ini_set('display_errors', 0);  // ← Keine Error-Details an Frontend
```

### **✅ Abgeschlossene Sicherheitsmaßnahmen (26.07.2025):**
1. ✅ **DB-Credentials** in Environment Variables (.env)
2. ✅ **Production Error Display** deaktiviert in allen APIs
3. ✅ **OAuth Client Secret** korrekt konfiguriert und funktional
4. ✅ **.gitignore** erweitert für .env-Dateien
5. ✅ **SQL-Injection-Schutz** mit Prepared Statements vorhanden
6. ✅ **Input-Validation** zentrale Bibliothek für alle 7 APIs implementiert
7. ✅ **Session-Management** Browser-Session-Security und CSRF-Protection
8. ✅ **XSS-Protection** htmlspecialchars für alle String-Inputs
9. ✅ **Password-Repository-Security** keine Credentials im Git-Repository

### ⚠️ **Noch zu implementieren:**
- Security-Headers (CSP, HSTS, Rate-Limiting)
- Penetrationstests beauftragen

### ✅ **Abgeschlossen (26.07.2025 - Abends):**
10. ✅ **Issue #1** - Logout-Warnung + localStorage-Zwischenspeicherung implementiert

### ✅ **Bereits sicher:**
- OAuth2 Integration: Azure AD Client Secret sicher
- Session Security: HTTP-only Cookies
- Database Queries: Prepared Statements verwendet

## Verzeichnisstruktur (Tatsächlich)

```
/app/
├── build/                          # ✅ ECHTE IMPLEMENTIERUNG
│   ├── src/views/
│   │   ├── DashboardView.tsx       # Haupt-Dashboard
│   │   ├── TimeSheetView.tsx       # ⚠️ Complexity 15 (Issue #20)
│   │   └── MainAppView.tsx         # 🔴 Datenverlust-Bug (Issue #1)
│   ├── api/
│   │   ├── db.php                  # ✅ Environment Variables (26.07.2025)
│   │   ├── time-entries.php        # ⚠️ Input validation fehlt
│   │   └── auth-*.php              # ✅ OAuth2 funktionsfähig
│   ├── package.json | schema.sql | index.html
├── meta/                           # ❌ VERALTETE .NET DOKU
├── deployment/                     # 📋 Deployment-Dokumentation
└── CLAUDE.local.md                 # Diese Datei
```

## 📋 **GITHUB ISSUES & ROADMAP**

**Details**: @import `/app/docs/GITHUB_ISSUES_OVERVIEW.md`

**🔴 Kritisch**: #1 (Datenverlust), #19 (DB-Password), #20 (Security)  
**📊 Master-Plan**: Issue #23 → ROI 38.133% (53k€ → 20M€) bis 01.09.2025

## Implementierte Features

### ✅ **Frontend (React):**
- Dashboard, Authentifizierung, Zeitübersicht, Genehmigungen
- Stammdaten, PDF-Export, Responsive Design, Change History

### ✅ **Backend (PHP):**
- REST APIs, MySQLi DB-Zugriff, Session-Management
- OAuth2 Integration (Azure AD), CORS Support

### ✅ **Datenbank (MySQL):**
- User Management, Time Tracking, Approval System, Master Data

## 🔧 **Entwicklungsstandards (AZE-spezifisch)**

### **Qualitätsgrundsätze:**
- **Best Practice First**: Mindeststandard IMMER erreichen, nie Kompromisse
- **Architektur vor Quick-Fixes**: Root-Cause-Analyse vor Lösungsumsetzung  
- **Refactoring Standard**: Nach jedem Feature Code systematisch aufräumen
- **Single Source of Truth**: CLAUDE.local.md als Master-Dokumentation

### **Testing vor Commits:**
```bash
cd /app/build
npm test                    # Unit Tests (Pflicht)
npm run build              # Production Build Test  
npm run typecheck          # TypeScript Validation
```

### **Issue-Disziplin:**
- **Fallback-Fehler** → Automatisch GitHub Issue erstellen
- **Lokale Issues** ↔ **GitHub Issues** MÜSSEN synchron bleiben
- **Problem-Ehrlichkeit**: Nie "funktioniert" behaupten wenn es das nicht tut
- **Ist-Zustand**: IMMER analysieren vor Änderungen

### **Persistenz-Check (vor jedem Commit):**
- ✅ Web-App nach Server-Restart funktional?
- ✅ Database-Connection nach Neustart OK?
- ✅ Session-Handling robust bei Browser-Reload?
- ✅ Keine Features/Funktionen verloren gegangen?

## Entwicklungsrichtlinien

**Details**: @import `/app/docs/DEVELOPMENT_GUIDELINES.md`  
**Standards**: @import `/app/docs/PROJECT_STANDARDS.md`

**Security-First**: .env für Credentials, Input-Validation, OWASP-Compliance  
**Tech-Stack**: React 18 + TypeScript + PHP + MySQL + Azure AD

## API-Dokumentation

**Details**: @import `/app/docs/API_DOCUMENTATION.md`

**Kern-APIs**: Auth (Azure AD), Time-Entries, Users, Approvals, MasterData  
⚠️ **Kritisch**: Logout-API hat Datenverlust-Bug (Issue #1)

## Deployment

### **Production (LIVE):**
✅ **Live-System**: https://aze.mikropartner.de  
✅ **Server**: HostEurope (wp10454681.server-he.de)  
✅ **Details**: @import `/app/deployment/SUCCESSFUL_FTP_DEPLOYMENT.md`

### **Development:**
```bash
cd /app/build
npm install && npm run dev     # Frontend: http://localhost:5173
php -S localhost:8000          # Backend: http://localhost:8000
```

### **Production Build:**
```bash
cd /app/build && npm run build && npm run preview
```

## Troubleshooting

**Details**: @import `/app/docs/TROUBLESHOOTING.md`

## 🎯 **NÄCHSTE SCHRITTE (PRIORISIERT)**

### **Diese Woche (KW 30):**
1. ✅ **Issue #1**: Logout-Warnung + localStorage-Zwischenspeicherung (ERLEDIGT 26.07.2025)
2. ✅ **Issue #19**: DB-Password aus `/app/build/api/db.php` entfernen (ERLEDIGT 26.07.2025)
3. ✅ **Issue #20**: Security-Code-Review abgeschlossen (ERLEDIGT 26.07.2025)

### **August 2025:**
1. **v0.6**: Error Handling + Unit Tests + DSGVO-Grundlagen
2. **v0.7**: Security-Hardening + Penetrationstests
3. **v0.8**: Legal-Compliance + Mehrsprachigkeit
4. **v1.0**: UI-Redesign + finale Production-Features

### **Business-kritische Ziele:**
- **DSGVO-Compliance**: 20 Mio € Bußgeld-Risiko vermeiden
- **Security-Hardening**: Penetrationstests bestehen (A+)
- **Performance**: Bundle < 1MB, Lighthouse > 95
- **Legal**: ArbZG + GoBD vollständig erfüllen

## Wichtige Erkenntnisse

1. **Arbeite ausschließlich mit `/app/build/`** (echte Implementierung)
2. 🚨 **KRITISCHE SECURITY-LÜCKEN** - Sofort beheben (Issue #19)
3. ✅ **System ist LIVE** - https://aze.mikropartner.de
4. 📈 **ROI 38.133%** - Extrem profitables Verbesserungsprojekt
5. 🎯 **23 strategische Issues** bis v1.0 Production Ready
6. 🔴 **Issue #1**: Datenverlust bei Logout (nicht Auto-Stop!)
7. **React/PHP Skills erforderlich** - nicht .NET/C#

## @imports für detaillierte Informationen:
- **Deployment**: @import `/app/deployment/SUCCESSFUL_FTP_DEPLOYMENT.md`
- **API-Docs**: @import `/app/docs/API_DOCUMENTATION.md`
- **Development**: @import `/app/docs/DEVELOPMENT_GUIDELINES.md`
- **Standards**: @import `/app/docs/PROJECT_STANDARDS.md`
- **Troubleshooting**: @import `/app/docs/TROUBLESHOOTING.md`
- **GitHub Issues**: @import `/app/docs/GITHUB_ISSUES_OVERVIEW.md`

**@import Info**: @import `/app/docs/README.md`

---

**Status**: ALPHA + STRUKTURIERTE WEITERENTWICKLUNG  
**Live-URL**: https://aze.mikropartner.de (Funktional, aber nicht Production Ready)  
**GitHub Issues**: 28 Issues → v1.0 Production Ready  
**Entwicklungsstand**: 2-Tage-Projekt mit funktionaler Basis
**Security-Status**: Grundlegende Sicherheitsprobleme behoben (26.07.2025)
**Nächster Meilenstein**: v0.6 Security & Compliance (Input-Validation)  
**Version**: v0.1 Alpha → v1.0 Production Ready  
**Letztes Update**: 26.07.2025 (Testing-Pipeline + Security-Fixes implementiert, Issue #19 abgeschlossen)  
**NÄCHSTER SCHRITT**: Issue #1 - Logout-Warnung + localStorage-Zwischenspeicherung
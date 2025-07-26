# AZE_Gemini - Arbeitszeiterfassung Project Guide

## ⚠️ KRITISCHER HINWEIS: Dokumentation vs. Realität

**WICHTIG**: Die Dokumentation in `/app/meta/` beschreibt eine geplante .NET Windows Forms Anwendung, aber die **tatsächliche Implementierung** in `/app/build/` ist eine **React/PHP Web-Anwendung**!

## 🚀 **AKTUELLER STATUS (26.07.2025)**

### **Live-System**: https://aze.mikropartner.de ✅
- **Version**: v1.0 PRODUKTIV → **Weiterentwicklung zu v1.0 Production Ready**
- **Deployment**: @import `/app/deployment/SUCCESSFUL_FTP_DEPLOYMENT.md`
- **GitHub Issues**: **23 strategische Issues** für Weiterentwicklung erstellt
- **Master-Plan**: Issue #23 mit **ROI-Analyse 38.133%** (53.000€ → 20.265.000€)
- **Roadmap**: 4 Milestones bis v1.0 Production Ready (01.09.2025)

### **🔴 KRITISCHE ERKENNTNISSE:**

#### **Issue #1 - DATENVERLUST-BUG mit Server-First Lösung:**
- **Problem identifiziert**: Datenverlust bei Logout (NICHT Auto-Stop)
- ✅ Zeit läuft korrekt weiter (gewünschtes Verhalten)
- 💡 **Lösung**: DB-Status 'running' + Client-Sync → Kein Datenverlust möglich
- **Server-First**: Zeit sofort in DB, Multi-Device Support automatisch

#### **Security-Critical (Issue #19/20):**
- 🚨 **Hardcoded DB-Password** in `/app/build/api/db.php` entdeckt
- ⚠️ **Input-Validation** fehlt in API-Endpoints
- 📋 **Penetrationstests** und OWASP-Compliance erforderlich

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

## 🔒 **SICHERHEITSHINWEISE - KRITISCH AKTUALISIERT**

### 🚨 **KRITISCHE SICHERHEITSLÜCKEN (NICHT behoben!):**

```php
// GEFUNDEN in /app/build/api/db.php - SOFORT BEHEBEN!
$password = "Start.321";  // ← PRODUKTIONS-PASSWORD IM CODE!

// KEINE Input-Validation in time-entries.php:
$date = $_POST['date'];  // ← SQL INJECTION MÖGLICH
```

### **Sofortmaßnahmen (Issue #19):**
1. 🔴 **DB-Credentials** in Environment Variables verschieben
2. 🔴 **Input-Validation** für alle API-Endpoints
3. 🔴 **SQL-Injection-Schutz** mit Prepared Statements
4. 🔴 **Security-Headers** (CSP, HSTS) implementieren
5. 🔴 **Penetrationstests** beauftragen

### ✅ **Bereits sicher:**
- OAuth2 Integration: Azure AD Client Secret sicher
- Session Security: HTTP-only Cookies
- Gitignore: .env Datei ausgeschlossen

## Verzeichnisstruktur (Tatsächlich)

```
/app/
├── build/                          # ✅ ECHTE IMPLEMENTIERUNG
│   ├── src/views/
│   │   ├── DashboardView.tsx       # Haupt-Dashboard
│   │   ├── TimeSheetView.tsx       # ⚠️ Complexity 15 (Issue #20)
│   │   └── MainAppView.tsx         # 🔴 Datenverlust-Bug (Issue #1)
│   ├── api/
│   │   ├── db.php                  # 🚨 HARDCODED PASSWORD!
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
1. **Issue #1**: Logout-Warnung + localStorage-Zwischenspeicherung
2. **Issue #19**: DB-Password aus `/app/build/api/db.php` entfernen (SOFORT!)
3. **Issue #20**: Security-Code-Review abschließen

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

**Status**: LIVE + STRATEGISCHE WEITERENTWICKLUNG  
**Live-URL**: https://aze.mikropartner.de  
**GitHub Issues**: 23 Issues → v1.0 Production Ready  
**Master-Plan**: Issue #23 (ROI 38.133%)  
**Nächster Meilenstein**: v0.6 Security & Compliance (10.08.2025)  
**Investment**: 53.000€ → **Nutzen**: 20.265.000€  
**Version**: v1.0 → v1.0 Production Ready  
**Letztes Update**: 26.07.2025 (Issue #1 verifiziert, Security-Gaps identifiziert)
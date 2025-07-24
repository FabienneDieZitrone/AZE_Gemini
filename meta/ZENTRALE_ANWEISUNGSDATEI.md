---
title: ZENTRALE_ANWEISUNGSDATEI.md - Anweisungen zur Arbeitszeiterfassung
description: Zentrale Anweisungsdatei bei der Entwicklung der Arbeitszeiterfassungsanwendung
author: Tanja Trella
version: 1.9
lastUpdated: 08.07.2025
category: Konfiguration
---

# ZENTRALE_ANWEISUNGSDATEI.md - Arbeitszeiterfassung Projekt

Diese Datei enthält alle Anweisungen und Kontextinformationen zur Fortsetzung der Entwicklung der Arbeitszeiterfassungsanwendung.

## Projektübersicht

**Projekt**: Arbeitszeiterfassungsanwendung für einen Bildungsträger  

**⚠️ KRITISCHER HINWEIS**: Diese Dokumentation ist VERALTET! 

**Geplant war**: C# 12.0 mit .NET 8.0, Windows Forms, Entity Framework Core  
**Tatsächlich implementiert**: React 18 + TypeScript + PHP + MySQL als Web-Anwendung in `/app/build/`

**Aktuelle Technologie**: React/TypeScript Frontend + PHP Backend + MySQL
**Status**: Beta v0.5 funktionsfähig (NICHT die hier beschriebene .NET App!)

## Aktueller Projektstatus

### ⚠️ DOKUMENTATIONSFEHLER - Tatsächlicher Status:

**Diese Liste beschreibt NICHT existierende .NET Features!**

**Tatsächlich implementiert** (in `/app/build/`):
1. ✅ **React Frontend** - Vollständige Web-UI mit TypeScript
2. ✅ **PHP Backend APIs** - REST Endpoints für alle Funktionen  
3. ✅ **MySQL Datenbank** - Produktive DB mit Schema (db10454681-aze)
4. ✅ **Benutzerauthentifizierung** - Session-basierte Anmeldung
5. ✅ **Zeiterfassung** - Start/Stop Funktionalität
6. ✅ **Genehmigungsworkflow** - Approval System implementiert
7. ✅ **Stammdaten-Verwaltung** - User/Location Management
8. ✅ **Export-Funktionen** - PDF Generation mit jsPDF
9. ✅ **Change History** - Audit Trail für Änderungen

### Nächste Schritte:
- **Status**: Web-App ist Beta v0.5 - FUNKTIONSFÄHIG!
- **Entwicklung**: Weitere Features in React/PHP hinzufügen
- **Dokumentation**: Diese veralteten .NET Pläne überarbeiten

### ⚠️ Prüfergebnisse - FALSCHE TESTS!

**Diese Tests prüfen auf .NET Dateien die NICHT existieren!**

Aktueller Testlauf mittels `meta/test-projekt.sh`:
```
❌ .NET Solution-Datei - EXISTIERT NICHT
❌ .NET Projekte - EXISTIEREN NICHT  
❌ C# Code - EXISTIERT NICHT
```

**Korrekte Prüfung der Web-App**:
```bash
cd /app/build
✅ package.json vorhanden - React Projekt OK
✅ src/ Verzeichnis - React Components OK  
✅ api/ Verzeichnis - PHP Backend OK
✅ schema.sql - Datenbank Schema OK
✅ npm install - Dependencies OK
✅ npm run dev - Development Server OK
```
✓ UI referenziert Common
✓ UI referenziert DAL
✓ UI referenziert BLL
✓ appsettings.json vorhanden
✓ .gitignore vorhanden
✓ Entity Framework Core in DAL
✓ Configuration Extensions in Common
✓ xUnit in Tests
✓ Arbeitszeiterfassung.Common lässt sich bauen
✓ Arbeitszeiterfassung.DAL lässt sich bauen
✓ Arbeitszeiterfassung.BLL lässt sich bauen
✓ Arbeitszeiterfassung.Tests lässt sich bauen
✓ UI-Projekt vorhanden
✓ Arbeitszeiterfassung.Common wurde gebaut
✓ Arbeitszeiterfassung.DAL wurde gebaut
✓ Arbeitszeiterfassung.BLL wurde gebaut
✓ Arbeitszeiterfassung.Tests wurde gebaut
✓ Unit-Tests erfolgreich
✓ Common/Configuration existiert
✓ Common/Enums existiert
✓ Common/Extensions existiert
✓ Common/Helpers existiert
✓ Common/Models existiert
✓ DAL/Context existiert
✓ DAL/Entities existiert
✓ DAL/Migrations existiert
✓ DAL/Repositories existiert
✓ Alle Tests bestanden!
```
Das Projekt ist bereit für die Entwicklung.
Nächster Schritt: Hauptfenster implementieren
Verwenden Sie: /app/AZE/Prompts/Schritt_4_1_Hauptfenster.md


## Entwicklungsrichtlinien

### Code-Stil:
- **Sprache**: C# 12.0 (.NET 8.0)
- **Kommentare**: Deutsch
- **Namenskonventionen**: 
  - Klassen/Interfaces: PascalCase
  - Methoden: PascalCase
  - Variablen: camelCase
  - Konstanten: UPPER_CASE
- **Einrückung**: 4 Spaces (keine Tabs)

### Dokumentation:
- Jede Datei beginnt mit YAML-Header
- XML-Dokumentation für alle öffentlichen Members
- Inline-Kommentare für komplexe Logik
- Änderungshistorie in Kommentaren dokumentieren

### Dateistruktur:
```
/app/AZE/
├── ZENTRALE_ANWEISUNGSDATEI.md (diese Datei)
├── Arbeitsplan_Arbeitszeiterfassung.md
├── Arbeitsplan_Bewertung.md
├── Zusammenfassung_Arbeitsplan.md
├── Prompts/
│   ├── Schritt_1_1_Projekt_Setup.md
│   ├── Schritt_1_2_Datenbankdesign.md
│   ├── Schritt_2_1_Repository_Pattern.md
│   ├── Schritt_3_2_Zeiterfassungslogik.md
│   └── Schritt_4_1_Hauptfenster.md
└── Arbeitszeiterfassung/ (wird erstellt)
    ├── Arbeitszeiterfassung.sln
    ├── Arbeitszeiterfassung.UI/
    ├── Arbeitszeiterfassung.BLL/
    ├── Arbeitszeiterfassung.DAL/
    └── Arbeitszeiterfassung.Common/
```

## Wichtige Anforderungen

### Funktional:
1. **Automatische Benutzeranmeldung** über Windows-Username
2. **IP-basierte Standorterkennung über Datenbanktabelle**
3. **Offline-Fähigkeit** mit automatischer Synchronisation
4. **Rollenbasierte Berechtigungen** (5 Rollen)
5. **Genehmigungsworkflow** für nachträgliche Änderungen
6. **Vollständiges Audit-Trail**

### Technisch:
1. **Keine externen Abhängigkeiten** (Standalone)
2. **Single-File Deployment**
3. **Thread-Safe Implementation**
4. **Async/Await durchgängig**
5. **DSGVO-konform**

### UI-Anforderungen:
- MP-Logo in allen Fenstern
- Responsive Design
- Farbschema: Dunkelblau (#003366), Orange (#FF6600)
- Schriftart: Segoe UI
- Minimale Fenstergröße: 800x600

## Datenbank-Verbindung

**Produktionsdatenbank**: db10454681-aze  
**Passwort**: Start.321  
**Provider**: MySQL/MariaDB

## Befehle für neue Chat-Session

```bash
# Projektverzeichnis
cd /app/AZE

# Aktuellen Status prüfen
ls -la

# Nächsten Schritt ausführen
# Verwende Prompt aus: Prompts/Schritt_1_1_Projekt_Setup.md
```

## Kontinuierliche Aufgaben

1. **Nach jeder Datei-Erstellung**: YAML-Header hinzufügen
2. **Bei Code-Änderungen**: Kommentare aktualisieren
3. **Nach Feature-Completion**: Dokumentation updaten (auch die ZENTRALE_ANWEISUNGSDATEI.md)
4. **Vor Schritt-Abschluss**: Code-Review durchführen
5. **Benutzerbestätigung**: Ein Schritt gilt erst nach positiver Rückmeldung des Benutzers über erfolgreiche Tests auf dem Zielsystem als abgeschlossen. Zusätzlich wird nach jedem Schritt eine Selbstbewertung auf einer Skala von 1-10 durchgeführt. Nur wenn keine Verbesserungen mehr möglich sind und die Bewertung bei 10 liegt, wird der Schritt endgültig abgeschlossen.

## Hilfreiche Referenzen

- **Hauptplan**: `/app/AZE/Arbeitsplan_Arbeitszeiterfassung.md`
- **Nächster Prompt**: `/app/AZE/Prompts/Schritt_2_2_Offline_Synchronisation.md`
- **Bewertung**: `/app/AZE/Arbeitsplan_Bewertung.md`

## Spezielle Hinweise

1. **Entity Framework**: Verwende Code-First Approach
2. **Offline-Sync**: SQLite muss identische Struktur wie MySQL haben
3. **Sicherheit**: Keine Passwörter im Code, nur Windows-Auth
4. **Performance**: Lazy Loading vermeiden, Eager Loading bevorzugen
5. **Testing**: **Immer 100% Code Coverage sicherstellen**
6. **Tests in Codex**: Kannst du Tests oder Prüfungen nicht selbst ausführen,
   erstelle Befehle oder Skripte für den Benutzer. Dieser führt sie auf dem
   Zielsystem aus und gibt dir die Ausgabe zurück.

Diese Datei dient als zentraler Einstiegspunkt für jede neue Session!

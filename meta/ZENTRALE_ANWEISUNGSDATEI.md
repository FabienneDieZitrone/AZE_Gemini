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

**Projekt**: Standalone Arbeitszeiterfassungsanwendung für einen Bildungsträger  
**Technologie**: C# 12.0 mit .NET 8.0, Windows Forms, Entity Framework Core  
**Architektur**: 3-Schichten-Architektur (UI, BLL, DAL)  
**Datenbanken**: MySQL/MariaDB (Haupt) + SQLite (Offline)

## Aktueller Projektstatus

### Bereits erstellt:
1. **Arbeitsplan_Arbeitszeiterfassung.md** – Vollständiger Entwicklungsplan mit 19 Schritten
2. **Prompts/** – Ordner mit 5 detaillierten Einzelprompts für die ersten Schritte
3. **Arbeitsplan_Bewertung.md** – Kritische Bewertung und Optimierungen
4. **Zusammenfassung_Arbeitsplan.md** – Executive Summary
5. **Konfigurationsmanagement** – ConfigurationManager und App.config eingerichtet
6. **Repository Pattern** – Generische Repositories und UnitOfWork (Schritt 2.1)
7. **Offline-Synchronisation** – Erste Vorbereitung mit SQLite (Schritt 2.2)
8. **Benutzerauthentifizierung** – Windows-Login mit IP-Pruefung (Schritt 3.1)

### Nächste Schritte:
- **Abgeschlossen**: Schritt 3.4 – Genehmigungsworkflow
- **Aktuell**: Schritt 4.1 – Hauptfenster
- **Prompt verfügbar in**: `/app/AZE/Prompts/Schritt_4_1_Hauptfenster.md`

### Prüfergebnisse
Aktueller Testlauf mittels `meta/test-projekt.sh`:
```
✓ Hauptverzeichnis existiert
✓ Solution-Datei vorhanden
✓ Projekt Arbeitszeiterfassung.Common existiert
✓ Projekt Arbeitszeiterfassung.DAL existiert
✓ Projekt Arbeitszeiterfassung.BLL existiert
✓ Projekt Arbeitszeiterfassung.UI existiert
✓ Projekt Arbeitszeiterfassung.Tests existiert
✓ DAL referenziert Common
✓ BLL referenziert Common
✓ BLL referenziert DAL
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

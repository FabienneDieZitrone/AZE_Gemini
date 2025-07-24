---
title: README - Arbeitszeiterfassung Projekt
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/README.md
description: Hauptdokumentation für das Arbeitszeiterfassungsprojekt
---

# Arbeitszeiterfassung für Bildungsträger

## 📋 Projektübersicht

Dieses Projekt implementiert eine **standalone Arbeitszeiterfassungsanwendung** für den Bildungsträger Mikropartner. Die Anwendung ermöglicht die digitale Erfassung, Verwaltung und Auswertung von Arbeitszeiten mit umfangreichen Features wie Offline-Synchronisation, automatischer Standorterkennung und einem mehrstufigen Genehmigungsworkflow.

## 🚀 Quick Start

### Voraussetzungen
- Windows 10/11 (64-bit)
- .NET 8.0 SDK
- MySQL/MariaDB Server
- Visual Studio 2022 oder VS Code

### Projekt initialisieren

**Windows (PowerShell):**
```powershell
.\init-projekt.ps1
```

**Linux/WSL (Bash):**
```bash
bash init-projekt.sh
```

### Projekt testen
```bash
bash test-projekt.sh
```

## 📁 Projektstruktur

```
AZE/
├── Arbeitszeiterfassung/              # Hauptprojekt (wird generiert)
│   ├── Arbeitszeiterfassung.Common/   # Gemeinsame Komponenten
│   ├── Arbeitszeiterfassung.DAL/      # Datenzugriffsschicht
│   ├── Arbeitszeiterfassung.BLL/      # Geschäftslogik
│   ├── Arbeitszeiterfassung.UI/       # Windows Forms UI
│   └── Arbeitszeiterfassung.Tests/    # Unit Tests
├── Prompts/                           # Entwicklungsanleitungen
│   ├── Schritt_1_1_Projekt_Setup.md
│   ├── Schritt_1_2_Datenbankdesign.md
│   └── ... (19 Schritte)
├── Dokumentation/
│   ├── Arbeitszeiterfassung_Anforderungsdokumentation.md
│   ├── Arbeitsplan_Arbeitszeiterfassung.md
│   └── PROJEKT_ZUSAMMENFASSUNG.md
└── Skripte/
    ├── init-projekt.sh/ps1           # Projektinitialisierung
    └── test-projekt.sh               # Projekttest
```

## 🛠️ Technologie-Stack

- **Framework**: .NET 8.0 mit C# 12.0
- **UI**: Windows Forms
- **Datenbank**: MySQL/MariaDB (Haupt) + SQLite (Offline)
- **ORM**: Entity Framework Core 8.0
- **Architektur**: 3-Schichten (UI, BLL, DAL)
- **Testing**: xUnit + Moq + FluentAssertions

## 📊 Hauptfunktionen

### Kernfeatures
- ✅ **Automatische Anmeldung** über Windows-Username
- ✅ **IP-basierte Standorterkennung**
- ✅ **Offline-Synchronisation** mit lokalem SQLite
- ✅ **5 Benutzerrollen** mit unterschiedlichen Berechtigungen
- ✅ **Genehmigungsworkflow** für nachträgliche Änderungen
- ✅ **Vollständiges Audit-Trail**
- ✅ **Export-Funktionen** (Excel, PDF, CSV)

### Benutzerrollen
1. **Administrator**: Vollzugriff auf alle Funktionen
2. **Bereichsleiter**: Verwaltung mehrerer Standorte
3. **Standortleiter**: Verwaltung eines Standorts
4. **Mitarbeiter**: Eigene Zeiterfassung
5. **Honorarkraft**: Eingeschränkte Zeiterfassung

## 🔧 Entwicklung

### Schritt-für-Schritt Anleitung

Das Projekt folgt einem strukturierten 19-Schritte-Plan:

**Phase 1: Projektinitialisierung**
- Schritt 1.1: Projekt-Setup ✓
- Schritt 1.2: Datenbankdesign
- Schritt 1.3: Konfigurationsmanagement

**Phase 2: Datenzugriffsschicht**
- Schritt 2.1: Repository-Pattern
- Schritt 2.2: Offline-Synchronisation

**Phase 3: Geschäftslogik**
- Schritt 3.1: Benutzerauthentifizierung
- Schritt 3.2: Zeiterfassungslogik
- Schritt 3.3: Rollenbasierte Zugriffskontrolle
 - Schritt 3.4: Genehmigungsworkflow

**Phase 4: Benutzeroberfläche**
- Schritt 4.1: Hauptfenster
- Schritt 4.2: Startseite
- Schritt 4.3: Stammdatenverwaltung
- Schritt 4.4: Arbeitszeitanzeige
- Schritt 4.5: Tagesdetails

**Phase 5: Erweiterte Funktionen**
- Schritt 5.1: Offline-Modus
- Schritt 5.2: Benachrichtigungen
- Schritt 5.3: Audit-Trail

**Phase 6: Testing & Deployment**
- Schritt 6.1: Unit-Tests
- Schritt 6.2: Integrationstests
- Schritt 6.3: Deployment

### Entwicklungsumgebung einrichten

1. **Repository klonen**
   ```bash
   git clone [repository-url]
   cd AZE
   ```

2. **Projekt initialisieren**
   ```bash
   bash init-projekt.sh
   ```

3. **Datenbank konfigurieren**
   - MySQL/MariaDB installieren
   - Datenbank `db10454681-aze` verwenden
   - Connection String in `appsettings.json` anpassen

4. **Entwicklung starten**
   ```bash
   cd Arbeitszeiterfassung
   dotnet build
   dotnet run --project Arbeitszeiterfassung.UI
   ```

## 📝 Dokumentation

### Wichtige Dokumente
- **[Anforderungsdokumentation](Arbeitszeiterfassung_Anforderungsdokumentation.md)**: Vollständige Spezifikation
- **[Arbeitsplan](Arbeitsplan_Arbeitszeiterfassung.md)**: Detaillierter Entwicklungsplan
- **[ZENTRALE_ANWEISUNGSDATEI.md](ZENTRALE_ANWEISUNGSDATEI.md)**: Anweisungen 
- **[Prompts/](Prompts/)**: Schritt-für-Schritt Anleitungen

### Entwicklungsrichtlinien
- **Sprache**: Deutsch für Kommentare und Dokumentation
- **Code-Stil**: C# Coding Conventions
- **Commits**: Konventionelle Commit-Messages
- **Tests**: Mindestens 80% Code Coverage
- **Schrittabschluss**: Ein Entwicklungsschritt wird erst nach positiver Rückmeldung des Benutzers über erfolgreiche Tests als erledigt markiert. Zudem erfolgt eine Selbstbewertung auf einer Skala von 1-10. Nur wenn keinerlei Verbesserungsmöglichkeiten bestehen und die Bewertung eine 10 erreicht, gilt der Schritt als abgeschlossen.

## 🔒 Sicherheit & Compliance

- **DSGVO-konform**: Datenschutz nach EU-Richtlinien
- **Verschlüsselung**: Sensitive Daten werden verschlüsselt
- **Audit-Trail**: Vollständige Protokollierung aller Änderungen
- **Windows-Authentifizierung**: Keine Passwörter im Code

## 📦 Deployment

### Standalone-Executable erstellen
```bash
cd Arbeitszeiterfassung.UI
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
```

### Systemanforderungen
- Windows 10/11 (64-bit)
- 4 GB RAM (empfohlen: 8 GB)
- 500 MB freier Speicherplatz
- Netzwerkzugriff für Online-Funktionen

## 🤝 Mitwirkende

- **Projektleitung**: Tanja Trella
- **Auftraggeber**: Mikropartner (Bildungsträger)

## 📄 Lizenz

Proprietäre Software - Alle Rechte vorbehalten.

## 💡 Support

Bei Fragen oder Problemen:
1. Konsultieren Sie die [Dokumentation](Dokumentation/)
2. Prüfen Sie die [Troubleshooting-Guides](Prompts/)
3. Kontaktieren Sie die Projektleitung

---
**Hinweis**: Dieses Projekt befindet sich in aktiver Entwicklung. Folgen Sie dem Arbeitsplan für die schrittweise Implementierung.

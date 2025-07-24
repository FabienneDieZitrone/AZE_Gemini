---
title: Entwickler-Checkliste für Arbeitszeiterfassung
version: 1.1
lastUpdated: 08.07.2025
author: Tanja Trella
status: Final
file: /app/AZE/ENTWICKLER_CHECKLISTE.md
description: Vollständige Checkliste für die Entwicklung der Arbeitszeiterfassungsanwendung
---

# 📋 Entwickler-Checkliste: Arbeitszeiterfassung

Diese Checkliste führt Sie durch alle 19 Entwicklungsschritte mit konkreten Prüfpunkten.

## ✅ Phase 1: Projektinitialisierung und Grundstruktur

### ☐ Schritt 1.1: Projekt-Setup und Verzeichnisstruktur
- [ ] .NET 8.0 SDK installiert
- [ ] Projekt mit `init-projekt.sh` initialisiert
- [ ] Solution erfolgreich erstellt
- [ ] Alle 5 Projekte angelegt (Common, DAL, BLL, UI, Tests)
- [ ] Projektverweise korrekt konfiguriert
- [ ] NuGet-Pakete installiert
- [ ] Build erfolgreich (`dotnet build`)
- [ ] Test mit `test-projekt.sh` bestanden

### ☐ Schritt 1.2: Datenbankdesign und Entity-Modelle
- [ ] Entity-Klassen erstellt (8 Haupttabellen)
- [ ] DbContext konfiguriert
- [ ] Relationships definiert
- [ ] Datenbank-Setup mit `setup-database.sh` durchgeführt
- [ ] Connection String in appsettings.json eingetragen
- [ ] Initial Migration erstellt
- [ ] Datenbank-Schema generiert

### ☐ Schritt 1.3: Konfigurationsmanagement
- [ ] ConfigurationManager implementiert
- [ ] appsettings.json strukturiert
- [ ] Verschlüsselung für Connection Strings
- [ ] Hot-Reload funktioniert
- [ ] Umgebungsspezifische Configs (Dev/Prod)

## ✅ Phase 2: Datenzugriffsschicht (DAL)

### ☐ Schritt 2.1: Repository-Pattern implementieren
- [ ] IRepository Interface definiert
- [ ] GenericRepository implementiert
- [ ] Spezifische Repositories erstellt
- [ ] Unit of Work Pattern
- [ ] Async-Methoden implementiert
- [ ] LINQ-Queries optimiert

### ☐ Schritt 2.2: Offline-Synchronisation vorbereiten
- [ ] SQLite-Context erstellt
- [ ] SyncQueue-Tabelle definiert
- [ ] Offline-Repository implementiert
- [ ] Sync-Mechanismus grundlegend vorhanden
- [ ] Konflikt-Erkennung vorbereitet

## ✅ Phase 3: Geschäftslogik (BLL)

### ☐ Schritt 3.1: Benutzerauthentifizierung
- [ ] Windows-Auth implementiert
- [ ] Benutzer-Service erstellt
- [ ] Auto-Login funktioniert
- [ ] Standort-Validierung per IP
- [ ] Session-Management

### ☐ Schritt 3.2: Zeiterfassungslogik
- [ ] Start/Stopp-Funktionalität
- [ ] Pausenverwaltung
- [ ] Chronologie-Prüfung
- [ ] Tagesabschluss-Logik
- [ ] Arbeitszeitberechnung

### ☑ Schritt 3.3: Rollenbasierte Zugriffskontrolle
- [x] 5 Rollen definiert
- [x] Berechtigungsmatrix implementiert
- [x] Authorize-Attribute gesetzt
- [x] Rollen-Service funktioniert

### ☑ Schritt 3.4: Genehmigungsworkflow
- [x] Antragserstellung
- [x] Genehmigungsprozess
- [x] E-Mail-Benachrichtigungen
- [x] Historie-Tracking

## ✅ Phase 4: Benutzeroberfläche (UI)

### ☐ Schritt 4.1: Hauptfenster und Navigation
- [ ] MDI-Container erstellt
- [ ] Menüstruktur implementiert
- [ ] MP-Logo eingebunden
- [ ] Statusleiste funktioniert
- [ ] Farbschema angewendet

### ☐ Schritt 4.2: Startseite mit Zeiterfassung
- [ ] Start/Stopp-Buttons
- [ ] Live-Timer implementiert
- [ ] Status-Anzeige
- [ ] Quick-Links vorhanden
- [ ] Tagesübersicht

### ☐ Schritt 4.3: Stammdatenverwaltung
- [ ] Benutzerformular
- [ ] Validierung funktioniert
- [ ] Speichern/Laden implementiert
- [ ] Berechtigungsprüfung

### ☐ Schritt 4.4: Arbeitszeitanzeige mit Filtern
- [ ] DataGridView konfiguriert
- [ ] Filter implementiert
- [ ] Sortierung funktioniert
- [ ] Export möglich
- [ ] Gruppierung vorhanden

### ☐ Schritt 4.5: Tagesdetails und Bearbeitung
- [ ] Detailformular erstellt
- [ ] Inline-Bearbeitung
- [ ] Validierung bei Änderungen
- [ ] Genehmigung erforderlich
- [ ] Druckfunktion

## ✅ Phase 5: Erweiterte Funktionen

### ☐ Schritt 5.1: Offline-Modus und Synchronisation
- [ ] Offline-Detection
- [ ] Lokale Speicherung
- [ ] Sync-Queue funktioniert
- [ ] Konfliktlösung implementiert
- [ ] UI-Feedback vorhanden

### ☐ Schritt 5.2: Benachrichtigungen und Validierungen
- [ ] Toast-Notifications
- [ ] E-Mail-Integration
- [ ] Freitags-Check aktiv
- [ ] Pausenerinnerung
- [ ] IP-Validierung scharf

### ☐ Schritt 5.3: Änderungsprotokoll und Audit
- [ ] Audit-Interceptor aktiv
- [ ] Alle Änderungen geloggt
- [ ] Audit-UI vorhanden
- [ ] Versions-Vergleich möglich
- [ ] Export funktioniert

## ✅ Phase 6: Testing und Deployment

### ☐ Schritt 6.1: Unit-Tests erstellen
- [ ] Test-Projekt konfiguriert
- [ ] >80% Code Coverage
- [ ] Alle Services getestet
- [ ] Mock-Framework verwendet
- [ ] CI/CD-Integration

### ☐ Schritt 6.2: Integrationstests
- [ ] End-to-End Tests
- [ ] UI-Automation
- [ ] Performance-Tests
- [ ] Lasttests durchgeführt
- [ ] Security-Tests

### ☐ Schritt 6.3: Deployment-Paket erstellen
- [ ] Single-File Executable
- [ ] MSI-Installer erstellt
- [ ] Code-Signing durchgeführt
- [ ] Update-Mechanismus
- [ ] Dokumentation komplett

## 🎯 Abschluss-Checkliste

### ☐ Dokumentation
- [ ] Benutzerhandbuch (PDF)
- [ ] Administratorhandbuch (PDF)
- [ ] API-Dokumentation
- [ ] Installationsanleitung
- [ ] Troubleshooting-Guide

### ☐ Qualitätssicherung
- [ ] Alle Tests grün
- [ ] Code-Review durchgeführt
- [ ] Performance akzeptabel
- [ ] Sicherheit geprüft
- [ ] DSGVO-konform

### ☐ Deployment-Bereitschaft
- [ ] Version 1.0.0 getaggt
- [ ] Release-Notes erstellt
- [ ] Installer getestet
- [ ] Update-Server bereit
- [ ] Support-Dokumentation

## 📊 Fortschritt-Tracking

```
Phase 1: [####] 100% - Projektinitialisierung
Phase 2: [####] 100% - Datenzugriffsschicht  
Phase 3: [####] 100% - Geschäftslogik
Phase 4: [####] 100% - Benutzeroberfläche
Phase 5: [####] 100% - Erweiterte Funktionen
Phase 6: [####] 100% - Testing & Deployment
```

## 🚀 Geschätzte Gesamtzeit: 48 Stunden

**Tipp**: Nutzen Sie diese Checkliste in Kombination mit den detaillierten Prompts in `/app/AZE/Prompts/` für eine erfolgreiche Entwicklung!
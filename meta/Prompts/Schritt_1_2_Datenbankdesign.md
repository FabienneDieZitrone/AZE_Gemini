---
title: Prompt für Schritt 1.2 - Datenbankdesign und Entity-Modelle
description: Detaillierter Prompt zur Erstellung der Datenbankstruktur und Entity Framework Modelle
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 1.2: Datenbankdesign und Entity-Modelle

## Aufgabe
Erstelle die kompletten Entity Framework Core Modelle für die Arbeitszeiterfassungsanwendung basierend auf dem vorgegebenen Datenbankdesign.

## Zu erstellende Entity-Modelle

### 1. Benutzer.cs
- BenutzerID, Username, Vorname, Nachname, Email
- Navigation zu Rolle, Stammdaten, Arbeitszeiten
- Audit-Felder (ErstelltAm, GeaendertAm, GeaendertVon)

### 2. Rolle.cs
- RolleID, Bezeichnung, Berechtigungsstufe, Beschreibung
- Enum für Berechtigungsstufen (1-5)
- Navigation zu Benutzern

### 3. Stammdaten.cs
- Wochenarbeitszeit, Arbeitstage (Mo-Fr als bool)
- HomeOfficeErlaubt
- 1:1 Beziehung zu Benutzer

### 4. Standort.cs
- StandortID, Bezeichnung, Adresse
- IPRangeStart, IPRangeEnd
- Navigation zu BenutzerStandorte

### 5. BenutzerStandort.cs
- Viele-zu-Viele Beziehungstabelle
- IstHauptstandort flag
- Zuweisungsinformationen

### 6. Arbeitszeit.cs
- Zeiterfassungsdaten (Start, Stopp, Pausen)
- Berechnete Felder (Gesamtzeit, Arbeitszeit)
- Offline-Synchronisations-Flags

### 7. Aenderungsprotokoll.cs
- Vollständiges Audit-Trail
- Änderungsgrund (Enum + Freitext)
- Genehmigungsstatus

### 8. Systemeinstellung.cs
- Key-Value Store für Konfiguration
- Typisierte Werte (string, number, boolean, json)

### 9. AppRessource.cs
- Id (int, Primary Key)
- Bezeichnung (nvarchar)
- Daten (BLOB)
- LetzteAktualisierung (datetime)
- Wird für Unternehmenslogo und weitere App-Ressourcen verwendet

## Zusätzlich zu erstellen

### 10. ApplicationDbContext.cs
- DbContext für MySQL/MariaDB
- Fluent API Konfiguration
- Seed-Daten für Rollen

### 11. OfflineDbContext.cs
- DbContext für SQLite
- Identische Struktur wie ApplicationDbContext
- Angepasste Konfiguration für SQLite

### 12. Enums/
- Berechtigungsstufe.cs
- AenderungsAktion.cs
- AenderungsGrund.cs
- EinstellungsTyp.cs

## Spezifische Anforderungen
1. Verwende Data Annotations und Fluent API
2. Implementiere INotifyPropertyChanged für UI-Binding
3. Erstelle partielle Klassen für berechnete Properties
4. Implementiere Validierung mit IValidatableObject
5. Berücksichtige Soft-Delete Pattern (Aktiv-Flag)
6. Erstelle Indizes für Performance-kritische Abfragen

## Benötigte Dateien
- Arbeitsplan_Arbeitszeiterfassung.md
- Projektstruktur aus Schritt 1.1

## Erwartete Ausgabe
- Alle Entity-Klassen im Models-Ordner
- ApplicationDbContext mit vollständiger Konfiguration
- OfflineDbContext für SQLite
- Migrations-Ordner mit InitialCreate
- Enums im Common-Projekt
- Konfiguration für beide Datenbanken

## Hinweise
- Beachte die Namenskonventionen (Singular für Entities, Plural für Tabellen)
- Implementiere bereits Lazy Loading Proxies
- Erstelle XML-Dokumentation für alle Properties
- Berücksichtige Thread-Safety für Offline-Sync
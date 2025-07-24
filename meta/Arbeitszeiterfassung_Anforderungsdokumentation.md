---
title: Anforderungsdokumentation Arbeitszeiterfassung
version: 2.0 (KORRIGIERT)
lastUpdated: 2025-07-24
author: Tanja Trella (aktualisiert von Claude Code)
status: IMPLEMENTIERT - Web-Anwendung funktionsfähig
file: /app/Arbeitszeiterfassung_Anforderungsdokumentation.md
description: KORRIGIERTE Dokumentation der TATSÄCHLICH implementierten React/PHP Web-Anwendung
---

# Anforderungsdokumentation: Arbeitszeiterfassungssystem

## ⚠️ KRITISCHE KORREKTUR

**URSPRÜNGLICHE PLANUNG**: .NET Windows Forms Anwendung  
**TATSÄCHLICHE IMPLEMENTIERUNG**: React/PHP Web-Anwendung

## 1. Projektübersicht

### 1.1 Tatsächliches Projektziel (IMPLEMENTIERT!)
**Web-basierte Arbeitszeiterfassungsanwendung** für einen Bildungsträger mit modernem Tech-Stack:
- Status: **Beta v0.5 FUNKTIONSFÄHIG**
- Standort: `/app/build/` (echte Implementierung)

### 1.2 Tatsächliche Technische Implementierung
- **Frontend**: React 18 + TypeScript + Vite (NICHT C#!)
- **Backend**: PHP 8+ REST APIs (NICHT .NET!)
- **Datenbank**: MySQL db10454681-aze @ vwp8374.webpack.hosteurope.de
- **Authentifizierung**: Microsoft Azure AD OAuth2 (BFF Pattern)
- **Deployment**: Web-Server (NICHT standalone .exe!)
- **Deployment**: Standalone-Anwendung ohne externe Abhängigkeiten
- **Netzwerk**: Offline-Fähigkeit mit automatischer Synchronisation

## 2. Benutzerrollen und Berechtigungen

### 2.1 Rollenhierarchie
1. **Admin**
   - Vollzugriff auf alle Daten und Funktionen
   - Kann alle Rollen vergeben
   - Kann Systemeinstellungen ändern

2. **Bereichsleiter**
   - Zugriff auf alle Standortdaten
   - Kann alle Rollen außer Admin vergeben
   - Kann Arbeitszeiten genehmigen/ablehnen

3. **Standortleiter**
   - Zugriff auf zugewiesene Standorte (mehrere möglich)
   - Kann Mitarbeiter- und Honorarkraft-Rollen vergeben
   - Kann Arbeitszeiten für seinen Bereich genehmigen/ablehnen

4. **Mitarbeiter**
   - Zugriff nur auf eigene Daten
   - Kann eigene Zeiten nachträglich ändern (mit Genehmigungsvorbehalt)

5. **Honorarkraft/freier Mitarbeiter**
   - Zugriff nur auf eigene Daten
   - Kann eigene Zeiten nachträglich ändern (mit Genehmigungsvorbehalt)

### 2.2 Datenzugriffsrechte
- Jeder Benutzer kann eigene Daten einsehen
- Änderungen durch Mitarbeiter benötigen Genehmigung
- Hierarchische Sichtbarkeit der Daten

## 3. Funktionale Anforderungen

### 3.1 Benutzerauthentifizierung
- **Automatische Benutzererkennung**: Aus lokaler Umgebungsvariable
- **Standorterkennung**: Über IP-Range aus der Datenbank
- **Erstanmeldung**: Automatische Benutzeranlage

### 3.2 Startseite
#### Layout (von oben nach unten, mittig):
1. Benutzername mit Gesamtüberstunden in Klammern
2. Aktueller Wochentag und Datum
3. Label "Zeiterfassung starten / stoppen"
4. Start/Stopp-Button (grün/rot) mit Zeitanzeige (HH:mm:ss)
5. Button "Arbeitszeiten anzeigen"
6. Button "Stammdaten" (rollenabhängig)
7. Quicklinks:
   - Jobrouter/Urlaubsworkflow (jobrouter.mikropartner.de)
   - Ticketsystem (ticket.mikropartner.de)
   - MPWeb 3.0 (mpweb.mikropartner.de)
   - Verbis (https://jobboerse2.arbeitsagentur.de/verbis/login)
   - MP-Laufwerke verbinden (C:\\tools\NetzLW.bat)
   - Aktuelle Telefonliste (O:\\Mikropartner_Allgemein/Telefonliste_13_12_2024 Änderungen vorbehalten.pdf)

### 3.3 Zeiterfassung
- **Start**: Grüner Button, wechselt zu rotem Stopp-Button
- **Zeitanzeige**: Laufende Zeit neben Button
- **Offline-Fähigkeit**: Lokale Speicherung bei Netzwerkausfall
- **Synchronisation**: Automatisch bei Netzwerkverfügbarkeit

### 3.4 Stammdatenverwaltung
#### Editierbare Felder:
- Regelmäßige Wochenarbeitszeit
- Wochenarbeitstage (Mo-Fr als Checkboxen)
- Zugeordnete Standorte (Mehrfachauswahl)
- Home Office Erlaubnis
- Rollenzuweisung (berechtigungsabhängig)

### 3.5 Arbeitszeitenanzeige
#### Filter (oberhalb der Tabelle links):
1. **Zeitraum**: Diese Woche (default), letzte Woche, Dieser Monat, etc.
2. **Standort**: Alle Standorte (default), einzelne Standorte
3. **Benutzer**: Eigener Account (default), andere (berechtigungsabhängig)

#### Anzeige (oberhalb der Tabelle rechts):
- Wochenarbeitsstunden
- Wochenarbeitstage
- Rolle (nicht für Mitarbeiter sichtbar)
- Zurück-Button

#### Tabellenspalten:
- Details (Button)
- Username
- Datum
- Startzeit (HH:mm)
- Stoppzeit (HH:mm)
- Gesamtzeit (HH:mm:ss)
- Pause (HH:mm:ss)
- Standort
- Rolle (ausgeblendet für Mitarbeiter)
- Erstellt_am (Datum HH:mm:ss)

### 3.6 Tagesdetails
#### Anzeige:
- Wochentag und Datum (mittig oben)
- Button "Anzeige der Änderungen" (links)
- Zurück-Button (rechts)

#### Tabelle:
- Bearbeiten-Button
- Löschen-Button
- Username
- Startzeit (HH:mm:ss)
- Stoppzeit (HH:mm:ss)
- Arbeitszeit (HH:mm:ss)
- Standort
- Rolle (ausgeblendet für Mitarbeiter)

### 3.7 Zeitenbearbeitung
#### Bearbeitungsformular:
- Startzeit (mit Datum- und Zeitauswahl)
- Stoppzeit (mit Datum- und Zeitauswahl)
- Änderungsgrund (Dropdown):
  - Vergessen zu starten
  - Vergessen zu stoppen
  - Rechner defekt
  - Netzwerkausfall
  - Softwarefehler
  - Sonstige (mit Freitextfeld)
- Speichern/Abbrechen-Buttons

### 3.8 Genehmigungsworkflow
- Nachträgliche Änderungen werden mit "nicht genehmigt"-Flag gespeichert
- Vorgesetzte sehen ungenehmgte Änderungen farblich markiert (orange)
- Genehmigung/Ablehnung per Klick möglich

### 3.9 Änderungsprotokoll
#### Datenbankstruktur:
- ID
- Original_ID
- Username
- Datum
- Startzeit/Stoppzeit
- Gesamtzeit/Arbeitszeit/Pause
- Standort
- Rolle
- Aktion (gelöscht/bearbeitet)
- Änderungsgrund
- Geändert von
- Geändert am

#### Suchfilter:
- Von-Bis Datum (Kalenderauswahl)
- Zeitraum (Dropdown)
- Benutzer (Dropdown)
- Standort (Mehrfachauswahl)
- Aktion (alle/bearbeitet/gelöscht)

## 4. Nicht-funktionale Anforderungen

### 4.1 Benutzeroberfläche
- MP-Logo in allen Titelleisten (oben links)
- Responsive Tabellenbreite
- Resizable Spalten
- Zentrierte Tabellenköpfe
- Fenster: verkleinerbar, vergrößerbar, schließbar, resizable
- Kalenderauswahl für Datumsfelder
- Farbliche Markierungen:
  - Rot: Über-/Unterschreitung der Arbeitszeit
  - Orange: Ungenehmgte Änderungen

### 4.2 Datenintegrität
- Vollständige Änderungshistorie
- Benutzer- und Zeitstempel für alle Änderungen
- Keine Anzeige von IDs in Tabellen

### 4.3 Fehlerbehandlung
- IP-Range-Validierung mit Fehlermeldung
- Offline-Modus mit lokaler Speicherung
- Warnung bei fehlender Netzwerkverbindung

### 4.4 Benachrichtigungen
- Freitags-Check: Warnung bei Über-/Unterschreitung der Wochenarbeitszeit
- Konfigurierbare Differenzzeit (Default: 1 Stunde)

## 5. Datenbankdesign

### 5.1 Haupttabellen

#### 5.1.1 Tabelle: Benutzer
- **BenutzerID** (INT, PK, AUTO_INCREMENT)
- **Username** (VARCHAR(50), UNIQUE, NOT NULL) - aus Umgebungsvariable
- **Vorname** (VARCHAR(100))
- **Nachname** (VARCHAR(100))
- **Email** (VARCHAR(255))
- **RolleID** (INT, FK → Rollen)
- **Aktiv** (BOOLEAN, DEFAULT TRUE)
- **ErstelltAm** (DATETIME, DEFAULT CURRENT_TIMESTAMP)
- **GeaendertAm** (DATETIME)
- **GeaendertVon** (INT, FK → Benutzer)

#### 5.1.2 Tabelle: Rollen
- **RolleID** (INT, PK, AUTO_INCREMENT)
- **Bezeichnung** (VARCHAR(50), UNIQUE)
- **Berechtigungsstufe** (INT) - 1=Mitarbeiter, 2=Honorarkraft, 3=Standortleiter, 4=Bereichsleiter, 5=Admin
- **Beschreibung** (TEXT)

#### 5.1.3 Tabelle: Stammdaten
- **StammdatenID** (INT, PK, AUTO_INCREMENT)
- **BenutzerID** (INT, FK → Benutzer, UNIQUE)
- **Wochenarbeitszeit** (DECIMAL(4,2)) - in Stunden
- **Arbeitstag_Mo** (BOOLEAN, DEFAULT FALSE)
- **Arbeitstag_Di** (BOOLEAN, DEFAULT FALSE)
- **Arbeitstag_Mi** (BOOLEAN, DEFAULT FALSE)
- **Arbeitstag_Do** (BOOLEAN, DEFAULT FALSE)
- **Arbeitstag_Fr** (BOOLEAN, DEFAULT FALSE)
- **HomeOfficeErlaubt** (BOOLEAN, DEFAULT FALSE)
- **GeaendertAm** (DATETIME)
- **GeaendertVon** (INT, FK → Benutzer)

#### 5.1.4 Tabelle: Standorte
- **StandortID** (INT, PK, AUTO_INCREMENT)
- **Bezeichnung** (VARCHAR(100))
- **Adresse** (VARCHAR(255))
- **IPRangeStart** (VARCHAR(15))
- **IPRangeEnd** (VARCHAR(15))
- **Aktiv** (BOOLEAN, DEFAULT TRUE)

#### 5.1.5 Tabelle: BenutzerStandorte (n:m)
- **BenutzerID** (INT, FK → Benutzer)
- **StandortID** (INT, FK → Standorte)
- **IstHauptstandort** (BOOLEAN, DEFAULT FALSE)
- **ZugewiesenAm** (DATETIME)
- **ZugewiesenVon** (INT, FK → Benutzer)
- PRIMARY KEY (BenutzerID, StandortID)

#### 5.1.6 Tabelle: Arbeitszeiten
- **ArbeitszeitID** (INT, PK, AUTO_INCREMENT)
- **BenutzerID** (INT, FK → Benutzer)
- **Datum** (DATE, NOT NULL)
- **Startzeit** (DATETIME, NOT NULL)
- **Stoppzeit** (DATETIME)
- **Gesamtzeit** (TIME) - berechnet
- **Pausenzeit** (TIME, DEFAULT '00:00:00')
- **Arbeitszeit** (TIME) - Gesamtzeit minus Pause
- **StandortID** (INT, FK → Standorte)
- **IstOffline** (BOOLEAN, DEFAULT FALSE)
- **SynchronisiertAm** (DATETIME)
- **ErstelltAm** (DATETIME, DEFAULT CURRENT_TIMESTAMP)
- **GeaendertAm** (DATETIME)
- **GeaendertVon** (INT, FK → Benutzer)
- INDEX idx_benutzer_datum (BenutzerID, Datum)

#### 5.1.7 Tabelle: Aenderungsprotokoll
- **AenderungID** (INT, PK, AUTO_INCREMENT)
- **OriginalID** (INT) - Referenz zur geänderten Arbeitszeit
- **BenutzerID** (INT, FK → Benutzer)
- **Datum** (DATE)
- **Startzeit_Alt** (DATETIME)
- **Startzeit_Neu** (DATETIME)
- **Stoppzeit_Alt** (DATETIME)
- **Stoppzeit_Neu** (DATETIME)
- **Gesamtzeit** (TIME)
- **Arbeitszeit** (TIME)
- **Pausenzeit** (TIME)
- **StandortID** (INT, FK → Standorte)
- **Aktion** (ENUM('bearbeitet', 'geloescht'))
- **Aenderungsgrund** (VARCHAR(255), NOT NULL)
- **GeaendertVon** (INT, FK → Benutzer)
- **GeaendertAm** (DATETIME, DEFAULT CURRENT_TIMESTAMP)
- **IstGenehmigt** (BOOLEAN, DEFAULT FALSE)
- **GenehmigtVon** (INT, FK → Benutzer)
- **GenehmigtAm** (DATETIME)

#### 5.1.8 Tabelle: Systemeinstellungen
- **EinstellungID** (INT, PK, AUTO_INCREMENT)
- **Schluessel** (VARCHAR(100), UNIQUE)
- **Wert** (TEXT)
- **Typ** (ENUM('string', 'number', 'boolean', 'json'))
- **Beschreibung** (TEXT)
- **GeaendertAm** (DATETIME)
- **GeaendertVon** (INT, FK → Benutzer)

### 5.2 Verbindungen und Indizes
- **Primärschlüssel**: Alle ID-Felder mit AUTO_INCREMENT
- **Fremdschlüssel**: Mit ON DELETE RESTRICT und ON UPDATE CASCADE
- **Indizes**: 
  - Benutzer.Username (UNIQUE)
  - Arbeitszeiten.(BenutzerID, Datum) - für schnelle Tagesabfragen
  - Aenderungsprotokoll.OriginalID - für Historie
  - BenutzerStandorte.(BenutzerID, StandortID) - zusammengesetzter PK
- **Constraints**:
  - CHECK (Stoppzeit > Startzeit OR Stoppzeit IS NULL)
  - CHECK (Wochenarbeitszeit BETWEEN 0 AND 60)
  - CHECK (Berechtigungsstufe BETWEEN 1 AND 5)

## 6. Technische Implementierung

### 6.1 Architektur
- **Anwendungstyp**: Windows Forms Desktop-Anwendung (C# 7.3)
- **Architekturmuster**: 3-Schichten-Architektur
  - Präsentationsschicht (Windows Forms)
  - Geschäftslogikschicht (Business Logic Layer)
  - Datenzugriffsschicht (Data Access Layer mit Entity Framework)
- **Lokale Datenhaltung**: SQLite für Offline-Daten
- **Konfiguration**: 
  - Standortdatenbank (IP-Range-Mapping)
  - app.config (Verbindungsstrings, Einstellungen)

### 6.2 Offline-Synchronisation

#### 6.2.1 Lokale Speicherung
- **Primäre DB**: MySQL/MariaDB (db10454681-aze)
- **Lokale DB**: SQLite mit identischer Struktur
- **Sync-Queue**: Warteschlange für ausstehende Operationen

#### 6.2.2 Synchronisationsmechanismus
1. **Bei Programmstart**:
   - Prüfung der Netzwerkverbindung
   - Sync aller lokalen Änderungen
   - Download aktueller Stammdaten

2. **Während der Laufzeit**:
   - Alle 30 Sekunden Verbindungsprüfung
   - Bei Verbindung: Sofortige Synchronisation
   - Bei Ausfall: Lokale Speicherung mit Flag

3. **Konfliktauflösung**:
   - Timestamp-basierte Auflösung
   - Server-Daten haben Vorrang bei Stammdaten
   - Lokale Zeiterfassungen werden priorisiert

#### 6.2.3 Sync-Status-Anzeige
- Grünes Icon: Online und synchronisiert
- Gelbes Icon: Offline-Modus aktiv
- Rotes Icon: Sync-Fehler
- Anzahl ausstehender Datensätze

### 6.3 Sicherheit
- **Authentifizierung**: Windows-Benutzer aus Umgebungsvariable
- **Autorisierung**: Rollenbasierte Zugriffskontrolle (RBAC)
- **Datenverschlüsselung**: 
  - SSL/TLS für Datenbankverbindung
  - AES-256 für lokale Daten
- **Audit-Trail**: Vollständige Protokollierung aller Änderungen
- **Session-Management**: Automatisches Timeout nach 30 Min. Inaktivität

### 6.4 Performance-Anforderungen
- **Startzeit**: < 3 Sekunden
- **Reaktionszeit**: < 500ms für UI-Aktionen
- **Datenbankabfragen**: < 1 Sekunde für Standardabfragen
- **Synchronisation**: < 5 Sekunden für 100 Datensätze
- **Speicherbedarf**: Max. 200MB RAM
- **Lokaler Speicher**: Max. 500MB für Offline-Daten

## 7. Deployment und Wartung

### 7.1 Installation
- Einzelne ausführbare Datei
- Keine externen Abhängigkeiten
- Automatische Datenbankinitialisierung

### 7.2 Updates
- Versionskontrolle
- Datenbankmigrationen
- Konfigurationsmanagement

## 8. Zusätzliche Funktionen (TODO)

### 8.1 Erweiterte Validierung
- Prüfung ob Benutzer im Mikropartner-Netzwerk (IP-Range)
- Bei ungültiger IP: Fehlermeldung und Programmbeendigung

### 8.2 Erweiterte Benachrichtigungen
- Wöchentliche Arbeitszeitprüfung (Freitags)
- Anzeige für Vorgesetzte bei Über-/Unterschreitung
- Konfigurierbare Toleranzwerte

### 8.3 Erweiterte Anzeigen
- Gesamtüberstunden hinter Benutzername
- Tägliche Soll-/Ist-Differenzen
- Berechnete Tagesarbeitszeit basierend auf Stammdaten

### 8.4 Mehrfachverantwortung
- Standortleiter können mehrere Standorte betreuen
- Flexible Zuordnung über Stammdaten

## 9. Konfigurationsdateien

### 9.1 Standorttabelle Beispielinhalt
```json
{
  "standorte": [
    {
      "id": 1,
      "name": "Hauptstandort Berlin",
      "adresse": "Beispielstraße 1, 10115 Berlin",
      "ipRanges": [
        {
          "start": "192.168.1.1",
          "end": "192.168.1.254"
        }
      ]
    },
    {
      "id": 2,
      "name": "Filiale Hamburg",
      "adresse": "Musterweg 2, 20095 Hamburg",
      "ipRanges": [
        {
          "start": "192.168.2.1",
          "end": "192.168.2.254"
        }
      ]
    }
  ],
  "homeOffice": {
    "enabled": true,
    "vpnRequired": true,
    "vpnRanges": [
      {
        "start": "10.0.0.1",
        "end": "10.0.0.254"
      }
    ]
  }
}
```

### 9.2 App.config Einstellungen
- Datenbankverbindung (verschlüsselt)
- Sync-Intervall (Standard: 30 Sek.)
- Session-Timeout (Standard: 30 Min.)
- Log-Level (Debug/Info/Warning/Error)
- UI-Theme-Einstellungen

## 10. Testanforderungen

### 10.1 Funktionale Tests
- Benutzeranmeldung und -erkennung
- Zeiterfassung Start/Stopp
- Offline/Online-Synchronisation
- Rollenberechtigung
- Genehmigungsworkflow
- Datenexport

### 10.2 Nicht-funktionale Tests
- Performance (Antwortzeiten)
- Lasttest (100 gleichzeitige Benutzer)
- Sicherheitstest (Penetrationstest)
- Usability-Test
- Kompatibilitätstest (Windows 7/8/10/11)

### 10.3 Testdaten
- Mindestens 5 Benutzer pro Rolle
- 6 Monate historische Daten
- Verschiedene Arbeitszeitmodelle
- Edge-Cases (Nachtschicht, Feiertage)

## 11. Wartung und Support

### 11.1 Logging
- Anwendungslog (Fehler, Warnungen)
- Auditlog (alle Änderungen)
- Performancelog (Antwortzeiten)
- Synchronisationslog

### 11.2 Monitoring
- Datenbankverbindung
- Sync-Status
- Fehlerrate
- Performance-Metriken

### 11.3 Backup-Strategie
- Tägliches Datenbank-Backup
- Lokale SQLite-Backups
- Konfigurationsdatei-Versionierung
- Disaster-Recovery-Plan

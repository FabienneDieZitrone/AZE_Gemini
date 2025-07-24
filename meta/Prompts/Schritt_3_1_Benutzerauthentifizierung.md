---
title: Prompt für Schritt 3.1 - Benutzerauthentifizierung
description: Detaillierter Prompt zur Implementierung der Windows-Benutzer-Erkennung
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 3.1: Benutzerauthentifizierung

## Aufgabe
Implementiere die automatische Benutzerauthentifizierung basierend auf Windows-Umgebungsvariablen und IP-Range-Validierung.

## Zu erstellende Komponenten

### 1. AuthenticationService.cs
```csharp
public class AuthenticationService : IAuthenticationService
{
    Task<Benutzer> AuthenticateAsync();
    Task<bool> ValidateIPRangeAsync(string ipAddress);
    Task<Standort> GetStandortByIPAsync(string ipAddress);
    Task<Benutzer> CreateOrUpdateBenutzerAsync(string username);
}
```

### 2. IPRangeValidator.cs
- IP-Adresse des Clients ermitteln
- Gegen die in der Datenbank gespeicherten Standorte validieren
- Home-Office Erkennung
- VPN-Range Support

### 3. StandortService.cs
- Standortdaten aus der Datenbank laden und cachen
- IP-Range Matching Algorithmus
- Fallback-Mechanismen
- Reload-Functionality

### 4. SessionManager.cs
- Aktuelle Benutzersession verwalten
- Benutzerkontext bereitstellen
- Timeout-Handling (30 Min)
- Thread-Safe Singleton

### 5. EnvironmentHelper.cs
- Windows-Username auslesen
- Computername ermitteln
- Domain-Information
- Fallback-Mechanismen

- Abbildung der Standorttabelle
- Strongly-Typed Configuration
- Validierung der Konfiguration

## Spezifische Anforderungen

### Authentifizierungsablauf:
1. Windows-Username aus Environment auslesen
2. Aktuelle IP-Adresse ermitteln
3. IP mit den in der Datenbank vorhandenen Standorten abgleichen
4. Bei ungültiger IP: Fehlermeldung und Exit
5. Benutzer in DB suchen oder anlegen
6. Standort zuweisen
7. Session initialisieren

### Fehlerbehandlung:
- Keine gültige IP: "Sie befinden sich nicht im Firmennetzwerk"
- Konfigurationsfehler: Detaillierte Fehlermeldung
- Netzwerkfehler: Offline-Modus aktivieren
- Benutzer nicht gefunden: Automatisch anlegen

### Sicherheit:
- Keine Passwörter (Windows-Auth)
- IP-Spoofing Schutz
- Audit-Log für Anmeldungen
- Session-Hijacking Prävention

## Benötigte Dateien
- Repository-Klassen aus Schritt 2.1
- Entity-Modelle
- SQL-Skript zur Initialisierung der Standorttabelle

## Erwartete Ausgabe
```
BLL/
├── Services/
│   ├── AuthenticationService.cs
│   ├── StandortService.cs
│   └── SessionManager.cs
├── Validators/
│   └── IPRangeValidator.cs
├── Helpers/
│   └── EnvironmentHelper.cs
├── Models/
│   └── StandortConfig.cs
└── Interfaces/
    ├── IAuthenticationService.cs
    ├── IStandortService.cs
    └── ISessionManager.cs
```


## Unit Tests
Erstelle Tests für:
- IP-Range Validierung (verschiedene Formate)
- Benutzeranlage
- Session-Timeout
- Offline-Fallback
- Edge Cases (ungültige IPs, leere Config)

## Hinweise
- Async/Await durchgängig verwenden
- Caching für Performance
- Retry-Logic bei Netzwerkfehlern
- Konfiguration Hot-Reload fähig
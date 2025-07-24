---
title: Prompt für Schritt 3.2 - Zeiterfassungslogik
description: Detaillierter Prompt zur Implementierung der Start/Stopp-Funktionalität mit Validierung
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 3.2: Zeiterfassungslogik

## Aufgabe
Implementiere die komplette Geschäftslogik für die Zeiterfassung mit Start/Stopp-Funktionalität, Validierung und Berechnungen.

## Zu erstellende Komponenten

### 1. IZeiterfassungService Interface
```csharp
public interface IZeiterfassungService
{
    Task<Arbeitszeit> StartArbeitszeitAsync(int benutzerId);
    Task<Arbeitszeit> StoppArbeitszeitAsync(int benutzerId);
    Task<Arbeitszeit> GetAktuelleArbeitszeitAsync(int benutzerId);
    Task<bool> IstArbeitszeitAktivAsync(int benutzerId);
    Task<TimeSpan> GetTagesarbeitszeitAsync(int benutzerId, DateTime datum);
    Task<decimal> GetWochenarbeitszeitAsync(int benutzerId, DateTime woche);
    Task<ArbeitszeitStatus> GetStatusAsync(int benutzerId);
}
```

### 2. ZeiterfassungService Implementierung
```csharp
public class ZeiterfassungService : IZeiterfassungService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly IStandortService _standortService;
    private readonly IValidationService _validationService;
    private readonly ISessionManager _sessionManager;
    
    // Implementierung aller Interface-Methoden
    // Geschäftslogik und Validierungen
    // Event-Handling für UI-Updates
}
```

### 3. ArbeitszeitValidator.cs
```csharp
public class ArbeitszeitValidator : IArbeitszeitValidator
{
    ValidationResult ValidateStart(Benutzer benutzer, Standort standort);
    ValidationResult ValidateStopp(Arbeitszeit arbeitszeit);
    ValidationResult ValidateZeitraum(DateTime start, DateTime stopp);
    ValidationResult ValidatePause(TimeSpan pausenzeit, TimeSpan arbeitszeit);
    ValidationResult ValidateWochenarbeitszeit(decimal istStunden, decimal sollStunden);
}
```

### 4. ArbeitszeitCalculator.cs
```csharp
public class ArbeitszeitCalculator : IArbeitszeitCalculator
{
    TimeSpan BerechneBruttoArbeitszeit(DateTime start, DateTime stopp);
    TimeSpan BerechneNettoArbeitszeit(TimeSpan brutto, TimeSpan pause);
    TimeSpan BerechneAutomatischePause(TimeSpan arbeitszeit);
    decimal BerechneWochenarbeitszeit(IEnumerable<Arbeitszeit> wochenzeiten);
    decimal BerechneUeberstunden(decimal ist, decimal soll);
    ArbeitszeitStatistik BerechneMonatsstatistik(int benutzerId, int jahr, int monat);
}
```

### 5. PausenManager.cs
```csharp
public class PausenManager : IPausenManager
{
    Task<TimeSpan> GetGesetzlichePauseAsync(TimeSpan arbeitszeit);
    Task<bool> ValidierePausenregelungAsync(Arbeitszeit arbeitszeit);
    Task<Pausenvorschlag> GetPausenvorschlagAsync(TimeSpan bisherige);
}

// Gesetzliche Pausenregelung:
// < 6 Stunden: keine Pause
// 6-9 Stunden: 30 Minuten
// > 9 Stunden: 45 Minuten
```

### 6. Validierungsregeln

#### Start-Validierung:
```csharp
public class StartValidierung
{
    // Keine aktive Arbeitszeit vorhanden
    // Benutzer ist aktiv
    // Standort ist gültig (IP-Check)
    // Nicht in der Zukunft
    // Maximal 24h in der Vergangenheit
}
```

#### Stopp-Validierung:
```csharp
public class StoppValidierung
{
    // Aktive Arbeitszeit vorhanden
    // Stopp nach Start
    // Nicht länger als 12h Arbeitszeit
    // Pausenregelung eingehalten
}
```

### 7. ArbeitszeitStatus.cs
```csharp
public class ArbeitszeitStatus
{
    public bool IstAktiv { get; set; }
    public DateTime? AktuellerStart { get; set; }
    public TimeSpan BisherigeArbeitszeit { get; set; }
    public decimal TagesSoll { get; set; }
    public decimal TagesIst { get; set; }
    public decimal WochenSoll { get; set; }
    public decimal WochenIst { get; set; }
    public decimal Ueberstunden { get; set; }
    public List<string> Warnungen { get; set; }
}
```

### 8. Events und Notifications

```csharp
public class ZeiterfassungEvents
{
    public event EventHandler<ArbeitszeitStartedEventArgs> ArbeitszeitGestartet;
    public event EventHandler<ArbeitszeitStoppedEventArgs> ArbeitszeitGestoppt;
    public event EventHandler<UeberstundenWarningEventArgs> UeberstundenWarnung;
    public event EventHandler<PausenzeitWarningEventArgs> PausenzeitWarnung;
}
```

## Spezielle Geschäftslogik

### 1. Automatische Korrekturen
```csharp
public class AutoKorrektur
{
    // Mitternachtsübergang behandeln
    Task<List<Arbeitszeit>> SplitteUeberMitternacht(Arbeitszeit original);
    
    // Wochenende behandeln
    Task<bool> PruefeWochenendarbeit(DateTime datum);
    
    // Feiertage behandeln
    Task<bool> IstFeiertag(DateTime datum, int standortId);
}
```

### 2. Wochenarbeitszeit-Berechnung
```csharp
public class WochenarbeitszeitLogik
{
    // Kalenderwoche ermitteln
    int GetKalenderwoche(DateTime datum);
    
    // Wochengrenzen bestimmen
    (DateTime start, DateTime ende) GetWochengrenzen(DateTime datum);
    
    // Soll-Arbeitszeit berechnen
    decimal BerechneSollArbeitszeit(Stammdaten stammdaten, DateTime woche);
}
```

### 3. Überstunden-Management
```csharp
public class UeberstundenManager
{
    decimal BerechneGesamtueberstunden(int benutzerId);
    Task<UeberstundenVerlauf> GetVerlaufAsync(int benutzerId, int monate);
    Task<bool> PruefeUeberstundengrenze(int benutzerId);
    Task SendeWarnungAsync(int benutzerId, decimal ueberstunden);
}
```

## Beispiel-Implementierung Start/Stopp

### Start-Methode:
```csharp
public async Task<Arbeitszeit> StartArbeitszeitAsync(int benutzerId)
{
    // 1. Validierung
    var benutzer = await _unitOfWork.Benutzer.GetByIdAsync(benutzerId);
    var standort = await _standortService.GetAktuellerStandortAsync();
    
    var validierung = _validator.ValidateStart(benutzer, standort);
    if (!validierung.IsValid)
        throw new ValidationException(validierung.Errors);
    
    // 2. Arbeitszeit erstellen
    var arbeitszeit = new Arbeitszeit
    {
        BenutzerID = benutzerId,
        Datum = DateTime.Today,
        Startzeit = DateTime.Now,
        StandortID = standort.StandortID,
        IstOffline = !await _networkMonitor.IsOnlineAsync()
    };
    
    // 3. Speichern
    await _unitOfWork.Arbeitszeiten.AddAsync(arbeitszeit);
    await _unitOfWork.SaveChangesAsync();
    
    // 4. Event auslösen
    ArbeitszeitGestartet?.Invoke(this, new ArbeitszeitStartedEventArgs(arbeitszeit));
    
    return arbeitszeit;
}
```

### Stopp-Methode:
```csharp
public async Task<Arbeitszeit> StoppArbeitszeitAsync(int benutzerId)
{
    // 1. Aktuelle Arbeitszeit holen
    var arbeitszeit = await GetAktuelleArbeitszeitAsync(benutzerId);
    if (arbeitszeit == null)
        throw new InvalidOperationException("Keine aktive Arbeitszeit vorhanden");
    
    // 2. Stopp setzen
    arbeitszeit.Stoppzeit = DateTime.Now;
    
    // 3. Zeiten berechnen
    arbeitszeit.Gesamtzeit = _calculator.BerechneBruttoArbeitszeit(
        arbeitszeit.Startzeit, 
        arbeitszeit.Stoppzeit.Value
    );
    
    // 4. Automatische Pause
    arbeitszeit.Pausenzeit = await _pausenManager.GetGesetzlichePauseAsync(
        arbeitszeit.Gesamtzeit
    );
    
    arbeitszeit.Arbeitszeit = _calculator.BerechneNettoArbeitszeit(
        arbeitszeit.Gesamtzeit, 
        arbeitszeit.Pausenzeit
    );
    
    // 5. Validierung
    var validierung = _validator.ValidateStopp(arbeitszeit);
    if (!validierung.IsValid)
        throw new ValidationException(validierung.Errors);
    
    // 6. Speichern
    await _unitOfWork.Arbeitszeiten.UpdateAsync(arbeitszeit);
    await _unitOfWork.SaveChangesAsync();
    
    // 7. Überstunden prüfen
    await PruefeUndWarneUeberstunden(benutzerId);
    
    // 8. Event
    ArbeitszeitGestoppt?.Invoke(this, new ArbeitszeitStoppedEventArgs(arbeitszeit));
    
    return arbeitszeit;
}
```

## Benötigte Dateien
- Repository-Klassen aus Schritt 2.1
- Entity-Modelle aus Schritt 1.2
- Authentication Service aus Schritt 3.1

## Erwartete Ausgabe
```
BLL/
├── Services/
│   ├── ZeiterfassungService.cs
│   ├── PausenManager.cs
│   └── UeberstundenManager.cs
├── Validators/
│   └── ArbeitszeitValidator.cs
├── Calculators/
│   └── ArbeitszeitCalculator.cs
├── Models/
│   ├── ArbeitszeitStatus.cs
│   ├── ValidationResult.cs
│   └── Pausenvorschlag.cs
├── Events/
│   ├── ArbeitszeitStartedEventArgs.cs
│   ├── ArbeitszeitStoppedEventArgs.cs
│   └── ZeiterfassungEvents.cs
└── Interfaces/
    ├── IZeiterfassungService.cs
    ├── IArbeitszeitValidator.cs
    ├── IArbeitszeitCalculator.cs
    └── IPausenManager.cs
```

## Hinweise
- Thread-Safe Implementation für Timer
- Timezone-Handling beachten
- Sommerzeit/Winterzeit berücksichtigen
- Performance bei Statistik-Berechnungen
- Caching für häufige Abfragen
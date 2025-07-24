---
title: Prompt für Schritt 3.4 - Genehmigungsworkflow
description: Detaillierter Prompt zur Implementierung des Änderungsgenehmigungssystems
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 3.4: Genehmigungsworkflow

## Aufgabe
Implementiere einen vollständigen Genehmigungsworkflow für nachträgliche Arbeitszeitänderungen mit Benachrichtigungen und Eskalationsmechanismen.

## Zu erstellende Komponenten

### 1. IGenehmigungService Interface
```csharp
public interface IGenehmigungService
{
    Task<Aenderungsprotokoll> CreateAenderungsantragAsync(
        int arbeitszeitId, 
        ArbeitszeitAenderung aenderung, 
        string grund);
        
    Task<GenehmigungResult> GenehmigeAenderungAsync(
        int aenderungId, 
        int genehmigerId, 
        string kommentar = null);
        
    Task<GenehmigungResult> LehneAenderungAbAsync(
        int aenderungId, 
        int genehmigerId, 
        string grund);
        
    Task<IEnumerable<Aenderungsprotokoll>> GetOffeneAntraegeAsync(int genehmigerId);
    Task<IEnumerable<Aenderungsprotokoll>> GetAntraegeVonBenutzerAsync(int benutzerId);
    Task EskaliereUeberfaelligeAntraegeAsync();
}
```

### 2. ArbeitszeitAenderung Model
```csharp
public class ArbeitszeitAenderung
{
    public DateTime? NeueStartzeit { get; set; }
    public DateTime? NeueStoppzeit { get; set; }
    public TimeSpan? NeuePausenzeit { get; set; }
    public int? NeuerStandortId { get; set; }
    
    // Berechnete Properties
    public bool IstStartzeitGeaendert => NeueStartzeit.HasValue;
    public bool IstStoppzeitGeaendert => NeueStoppzeit.HasValue;
    public bool IstPausenzeitGeaendert => NeuePausenzeit.HasValue;
    public bool IstStandortGeaendert => NeuerStandortId.HasValue;
}
```

### 3. GenehmigungService Implementierung
```csharp
public class GenehmigungService : IGenehmigungService
{
    private readonly IUnitOfWork _unitOfWork;
    private readonly IAuthorizationService _authService;
    private readonly INotificationService _notificationService;
    private readonly IAuditService _auditService;
    
    public async Task<Aenderungsprotokoll> CreateAenderungsantragAsync(
        int arbeitszeitId, 
        ArbeitszeitAenderung aenderung, 
        string grund)
    {
        // 1. Original-Arbeitszeit laden
        var original = await _unitOfWork.Arbeitszeiten.GetByIdAsync(arbeitszeitId);
        
        // 2. Validierung
        await ValidateAenderung(original, aenderung);
        
        // 3. Aenderungsprotokoll erstellen
        var protokoll = new Aenderungsprotokoll
        {
            OriginalID = arbeitszeitId,
            BenutzerID = original.BenutzerID,
            Datum = original.Datum,
            Startzeit_Alt = original.Startzeit,
            Startzeit_Neu = aenderung.NeueStartzeit ?? original.Startzeit,
            Stoppzeit_Alt = original.Stoppzeit,
            Stoppzeit_Neu = aenderung.NeueStoppzeit ?? original.Stoppzeit,
            // ... weitere Felder
            Aenderungsgrund = grund,
            Aktion = "bearbeitet",
            IstGenehmigt = false,
            GeaendertVon = _sessionManager.CurrentUser.BenutzerID,
            GeaendertAm = DateTime.Now
        };
        
        // 4. Genehmiger ermitteln
        var genehmiger = await GetGenehmigerAsync(original.BenutzerID);
        
        // 5. Speichern
        await _unitOfWork.Aenderungsprotokolle.AddAsync(protokoll);
        await _unitOfWork.SaveChangesAsync();
        
        // 6. Benachrichtigung
        await _notificationService.SendeGenehmigungsanfrageAsync(
            protokoll, 
            genehmiger
        );
        
        return protokoll;
    }
}
```

### 4. Workflow-Status Management
```csharp
public enum GenehmigungStatus
{
    Ausstehend = 0,
    Genehmigt = 1,
    Abgelehnt = 2,
    Eskaliert = 3,
    Zurueckgezogen = 4,
    Automatisch_Genehmigt = 5
}

public class WorkflowStatus
{
    public int AenderungsprotokollID { get; set; }
    public GenehmigungStatus Status { get; set; }
    public DateTime ErstelltAm { get; set; }
    public DateTime? BearbeitetAm { get; set; }
    public int? BearbeitetVon { get; set; }
    public string Kommentar { get; set; }
    public int EskalationsStufe { get; set; }
    public DateTime? NaechsteEskalation { get; set; }
}
```

### 5. Validierungsregeln
```csharp
public class AenderungsValidator : IAenderungsValidator
{
    public async Task<ValidationResult> ValidateAenderungAsync(
        Arbeitszeit original, 
        ArbeitszeitAenderung aenderung)
    {
        var errors = new List<string>();
        
        // Zeitliche Plausibilität
        if (aenderung.NeueStartzeit.HasValue && aenderung.NeueStoppzeit.HasValue)
        {
            if (aenderung.NeueStartzeit >= aenderung.NeueStoppzeit)
                errors.Add("Stoppzeit muss nach Startzeit liegen");
                
            var dauer = aenderung.NeueStoppzeit.Value - aenderung.NeueStartzeit.Value;
            if (dauer.TotalHours > 12)
                errors.Add("Arbeitszeit darf 12 Stunden nicht überschreiten");
        }
        
        // Maximale Rückwirkung (7 Tage)
        var tageZurueck = (DateTime.Today - original.Datum).Days;
        if (tageZurueck > 7)
            errors.Add("Änderungen sind nur bis 7 Tage rückwirkend möglich");
        
        // Pausenzeiten-Validierung
        if (aenderung.NeuePausenzeit.HasValue)
        {
            var arbeitszeit = CalculateArbeitszeit(aenderung);
            var minPause = GetMinPausenzeit(arbeitszeit);
            if (aenderung.NeuePausenzeit < minPause)
                errors.Add($"Mindestpausenzeit von {minPause} nicht eingehalten");
        }
        
        return new ValidationResult(errors);
    }
}
```

### 6. Benachrichtigungssystem
```csharp
public interface INotificationService
{
    Task SendeGenehmigungsanfrageAsync(
        Aenderungsprotokoll antrag, 
        Benutzer genehmiger);
        
    Task SendeGenehmigungsentscheidungAsync(
        Aenderungsprotokoll antrag, 
        bool genehmigt);
        
    Task SendeEskalationAsync(
        Aenderungsprotokoll antrag, 
        Benutzer neuerGenehmiger);
        
    Task SendeErinnerungAsync(
        IEnumerable<Aenderungsprotokoll> offeneAntraege);
}

public class NotificationService : INotificationService
{
    public async Task SendeGenehmigungsanfrageAsync(
        Aenderungsprotokoll antrag, 
        Benutzer genehmiger)
    {
        var notification = new Notification
        {
            EmpfaengerID = genehmiger.BenutzerID,
            Typ = NotificationType.Genehmigungsanfrage,
            Titel = "Neue Arbeitszeitänderung zur Genehmigung",
            Text = $"{antrag.Benutzer.Vollname} hat eine Änderung für den " +
                   $"{antrag.Datum:dd.MM.yyyy} beantragt",
            ReferenzID = antrag.AenderungID,
            ReferenzTyp = "Aenderungsprotokoll",
            Prioritaet = NotificationPriority.Normal,
            ErstelltAm = DateTime.Now
        };
        
        await _notificationRepository.AddAsync(notification);
        
        // Optional: Email versenden
        if (genehmiger.EmailBenachrichtigungen)
        {
            await _emailService.SendGenehmigungsEmail(antrag, genehmiger);
        }
    }
}
```

### 7. Eskalationsmechanismus
```csharp
public class EskalationsManager : IEskalationsManager
{
    private readonly TimeSpan _ersteStufe = TimeSpan.FromDays(2);
    private readonly TimeSpan _zweiteStufe = TimeSpan.FromDays(5);
    
    public async Task PruefeUndEskaliereAsync()
    {
        var offeneAntraege = await _unitOfWork.Aenderungsprotokolle
            .FindAsync(a => !a.IstGenehmigt && a.Aktion == "bearbeitet");
            
        foreach (var antrag in offeneAntraege)
        {
            var alter = DateTime.Now - antrag.GeaendertAm;
            
            if (alter > _zweiteStufe && antrag.EskalationsStufe < 2)
            {
                // An Bereichsleiter eskalieren
                await EskaliereAnBereichsleiterAsync(antrag);
            }
            else if (alter > _ersteStufe && antrag.EskalationsStufe < 1)
            {
                // Erinnerung an aktuellen Genehmiger
                await SendeErinnerungAsync(antrag);
            }
        }
    }
    
    private async Task EskaliereAnBereichsleiterAsync(Aenderungsprotokoll antrag)
    {
        var bereichsleiter = await _benutzerRepository.GetBereichsleiterAsync();
        
        antrag.EskalationsStufe = 2;
        antrag.AktuellerGenehmiger = bereichsleiter.BenutzerID;
        
        await _notificationService.SendeEskalationAsync(antrag, bereichsleiter);
        await _auditService.LogEskalationAsync(antrag);
    }
}
```

### 8. Genehmigungsentscheidung
```csharp
public class GenehmigungsentscheidungService
{
    public async Task<GenehmigungResult> GenehmigeAsync(
        int aenderungId, 
        int genehmigerId, 
        string kommentar)
    {
        var antrag = await _unitOfWork.Aenderungsprotokolle
            .GetByIdAsync(aenderungId);
            
        // Berechtigung prüfen
        if (!await _authService.CanApproveChangesAsync(genehmigerId, antrag.BenutzerID))
            throw new UnauthorizedException("Keine Berechtigung zur Genehmigung");
        
        // Arbeitszeit aktualisieren
        var arbeitszeit = await _unitOfWork.Arbeitszeiten
            .GetByIdAsync(antrag.OriginalID);
            
        arbeitszeit.Startzeit = antrag.Startzeit_Neu;
        arbeitszeit.Stoppzeit = antrag.Stoppzeit_Neu;
        // ... weitere Updates
        
        // Genehmigung protokollieren
        antrag.IstGenehmigt = true;
        antrag.GenehmigtVon = genehmigerId;
        antrag.GenehmigtAm = DateTime.Now;
        antrag.Genehmigungskommentar = kommentar;
        
        await _unitOfWork.SaveChangesAsync();
        
        // Benachrichtigung
        await _notificationService.SendeGenehmigungsentscheidungAsync(
            antrag, 
            true
        );
        
        return new GenehmigungResult { Erfolg = true };
    }
}
```

### 9. Dashboard für Genehmiger
```csharp
public class GenehmigungsDashboard
{
    public async Task<DashboardData> GetDashboardDataAsync(int genehmigerId)
    {
        return new DashboardData
        {
            OffeneAntraege = await GetOffeneAntraegeAsync(genehmigerId),
            AnzahlOffen = await GetAnzahlOffenAsync(genehmigerId),
            AnzahlUeberfaellig = await GetAnzahlUeberfaelligAsync(genehmigerId),
            LetzteEntscheidungen = await GetLetzteEntscheidungenAsync(genehmigerId),
            Statistik = await GetStatistikAsync(genehmigerId)
        };
    }
    
    public class DashboardData
    {
        public IEnumerable<AenderungsprotokollViewModel> OffeneAntraege { get; set; }
        public int AnzahlOffen { get; set; }
        public int AnzahlUeberfaellig { get; set; }
        public IEnumerable<EntscheidungViewModel> LetzteEntscheidungen { get; set; }
        public GenehmigungsStatistik Statistik { get; set; }
    }
}
```

### 10. Automatische Genehmigung
```csharp
public class AutoGenehmigungsService
{
    public async Task<bool> KannAutomatischGenehmigtWerdenAsync(
        Aenderungsprotokoll antrag)
    {
        // Kleine Änderungen (< 15 Minuten Differenz)
        var alteDauer = antrag.Stoppzeit_Alt - antrag.Startzeit_Alt;
        var neueDauer = antrag.Stoppzeit_Neu - antrag.Startzeit_Neu;
        var differenz = Math.Abs((neueDauer - alteDauer).TotalMinutes);
        
        if (differenz <= 15)
        {
            // Nur wenn bestimmte Gründe
            var erlaubteGruende = new[] {
                "Vergessen zu starten",
                "Vergessen zu stoppen",
                "Systemfehler"
            };
            
            return erlaubteGruende.Contains(antrag.Aenderungsgrund);
        }
        
        return false;
    }
}
```

## Benötigte Dateien
- Entity-Modelle aus Schritt 1.2
- Repository-Klassen aus Schritt 2.1
- AuthorizationService aus Schritt 3.3

## Erwartete Ausgabe
```
BLL/
├── Workflow/
│   ├── GenehmigungService.cs
│   ├── EskalationsManager.cs
│   ├── GenehmigungsentscheidungService.cs
│   ├── AutoGenehmigungsService.cs
│   └── GenehmigungsDashboard.cs
├── Validators/
│   └── AenderungsValidator.cs
├── Services/
│   └── NotificationService.cs
├── Models/
│   ├── ArbeitszeitAenderung.cs
│   ├── WorkflowStatus.cs
│   ├── GenehmigungResult.cs
│   ├── Notification.cs
│   └── DashboardData.cs
└── Interfaces/
    ├── IGenehmigungService.cs
    ├── INotificationService.cs
    ├── IEskalationsManager.cs
    └── IAenderungsValidator.cs
Common/
└── Enums/
    ├── GenehmigungStatus.cs
    └── NotificationType.cs
```

## Hinweise
- Transactional Operations für Konsistenz
- Event-basierte Architektur für Entkopplung
- Asynchrone Benachrichtigungen
- Audit-Trail für alle Entscheidungen
- Performance bei vielen offenen Anträgen beachten
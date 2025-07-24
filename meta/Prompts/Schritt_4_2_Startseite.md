---
title: Prompt für Schritt 4.2 - Startseite mit Zeiterfassung
description: Detaillierter Prompt zur Erstellung der Startseite mit Start/Stopp-Button und Quicklinks
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 4.2: Startseite mit Zeiterfassung

## Aufgabe
Erstelle die Startseite (StartPageControl) mit der zentralen Zeiterfassungsfunktion, Live-Timer und Quicklinks zu externen Systemen.

## UI-Layout

### Gesamtstruktur (von oben nach unten, zentriert):
```
┌─────────────────────────────────────────┐
│                                         │
│    [Benutzername] (Überstunden: +8.5h) │ ← Label, 16pt Bold
│                                         │
│         Montag, 26. Januar 2025        │ ← Label, 14pt
│                                         │
│      Zeiterfassung starten / stoppen    │ ← Label, 12pt
│                                         │
│    ┌─────────────────────────────┐     │
│    │        08:15:42             │     │ ← Timer Display
│    │   ╔═══════════════╗        │     │
│    │   ║     START     ║        │     │ ← Start/Stopp Button
│    │   ╚═══════════════╝        │     │
│    └─────────────────────────────┘     │
│                                         │
│         [Arbeitszeiten anzeigen]       │ ← Button
│                                         │
│            [Stammdaten]                │ ← Button (rollenabhängig)
│                                         │
│ ─────────── Quicklinks ──────────────  │
│                                         │
│  [Jobrouter]  [Ticketsystem]  [MPWeb]  │ ← Link Buttons
│  [Verbis]  [Laufwerke]  [Telefonliste] │
│                                         │
└─────────────────────────────────────────┘
```

## Zu erstellende Komponenten

### 1. StartPageControl.cs
```csharp
public partial class StartPageControl : UserControl
{
    private readonly IZeiterfassungService _zeiterfassungService;
    private readonly ISessionManager _sessionManager;
    private readonly IAuthorizationService _authService;
    
    private Timer _updateTimer;
    private Arbeitszeit _aktuelleArbeitszeit;
    private bool _isRunning;
    
    // UI Controls
    private Label lblBenutzer;
    private Label lblDatum;
    private Label lblZeitAnzeige;
    private Button btnStartStopp;
    private Button btnArbeitszeitenAnzeigen;
    private Button btnStammdaten;
    private FlowLayoutPanel pnlQuicklinks;
}
```

### 2. Zeiterfassungs-Button Design
```csharp
public class StartStoppButton : Button
{
    public bool IsRunning { get; set; }
    
    protected override void OnPaint(PaintEventArgs e)
    {
        // Runder Button mit Gradient
        using (var path = GetRoundedRectangle(ClientRectangle, 10))
        {
            // Gradient Brush
            using (var brush = new LinearGradientBrush(
                ClientRectangle,
                IsRunning ? Color.FromArgb(220, 53, 69) : Color.FromArgb(40, 167, 69),
                IsRunning ? Color.FromArgb(200, 35, 51) : Color.FromArgb(32, 134, 56),
                LinearGradientMode.Vertical))
            {
                e.Graphics.FillPath(brush, path);
            }
            
            // Text
            TextRenderer.DrawText(
                e.Graphics,
                IsRunning ? "STOPP" : "START",
                new Font("Segoe UI", 18, FontStyle.Bold),
                ClientRectangle,
                Color.White,
                TextFormatFlags.HorizontalCenter | TextFormatFlags.VerticalCenter
            );
        }
    }
}
```

### 3. Live-Timer Anzeige
```csharp
public class TimeDisplay : UserControl
{
    private Label lblTime;
    private DateTime _startTime;
    private Timer _displayTimer;
    
    public void StartTimer(DateTime startTime)
    {
        _startTime = startTime;
        _displayTimer = new Timer { Interval = 1000 };
        _displayTimer.Tick += (s, e) => UpdateDisplay();
        _displayTimer.Start();
    }
    
    private void UpdateDisplay()
    {
        var elapsed = DateTime.Now - _startTime;
        lblTime.Text = $"{elapsed:hh\\:mm\\:ss}";
        
        // Farbe ändern bei langer Arbeitszeit
        if (elapsed.TotalHours > 8)
            lblTime.ForeColor = Color.Orange;
        else if (elapsed.TotalHours > 10)
            lblTime.ForeColor = Color.Red;
    }
}
```

### 4. Quicklinks Implementation
```csharp
public class QuicklinkButton : Button
{
    public string LinkUrl { get; set; }
    public string LinkDescription { get; set; }
    
    protected override void OnClick(EventArgs e)
    {
        try
        {
            Process.Start(new ProcessStartInfo
            {
                FileName = LinkUrl,
                UseShellExecute = true
            });
        }
        catch (Exception ex)
        {
            MessageBox.Show(
                $"Fehler beim Öffnen von {LinkDescription}: {ex.Message}",
                "Fehler",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error
            );
        }
    }
}
```

### 5. StartPageControl Implementation
```csharp
public partial class StartPageControl : UserControl
{
    private async void OnLoad(object sender, EventArgs e)
    {
        // Benutzer-Info anzeigen
        var user = _sessionManager.CurrentUser;
        var status = await _zeiterfassungService.GetStatusAsync(user.BenutzerID);
        
        lblBenutzer.Text = $"{user.Vollname} (Überstunden: {status.Ueberstunden:+0.0;-0.0}h)";
        lblDatum.Text = DateTime.Now.ToString("dddd, d. MMMM yyyy", 
            new CultureInfo("de-DE"));
        
        // Zeiterfassungs-Status
        _isRunning = status.IstAktiv;
        if (_isRunning)
        {
            _aktuelleArbeitszeit = await _zeiterfassungService
                .GetAktuelleArbeitszeitAsync(user.BenutzerID);
            timeDisplay.StartTimer(_aktuelleArbeitszeit.Startzeit);
        }
        
        UpdateButtonState();
        
        // Stammdaten-Button nur für berechtigte Rollen
        btnStammdaten.Visible = await _authService.HasPermissionAsync(
            user.BenutzerID, 
            Permission.ManageUsers
        );
        
        // Update-Timer für Datum und Status
        _updateTimer = new Timer { Interval = 60000 }; // 1 Minute
        _updateTimer.Tick += async (s, ev) => await RefreshStatus();
        _updateTimer.Start();
    }
    
    private async void btnStartStopp_Click(object sender, EventArgs e)
    {
        try
        {
            btnStartStopp.Enabled = false;
            
            if (_isRunning)
            {
                // Stoppen
                var result = await _zeiterfassungService
                    .StoppArbeitszeitAsync(_sessionManager.CurrentUser.BenutzerID);
                    
                // Zusammenfassung anzeigen
                ShowZeitZusammenfassung(result);
                
                timeDisplay.StopTimer();
            }
            else
            {
                // Starten
                _aktuelleArbeitszeit = await _zeiterfassungService
                    .StartArbeitszeitAsync(_sessionManager.CurrentUser.BenutzerID);
                    
                timeDisplay.StartTimer(_aktuelleArbeitszeit.Startzeit);
                
                ShowNotification("Arbeitszeit gestartet", NotificationType.Success);
            }
            
            _isRunning = !_isRunning;
            UpdateButtonState();
        }
        catch (ValidationException vex)
        {
            ShowValidationErrors(vex.Errors);
        }
        catch (Exception ex)
        {
            ShowError($"Fehler bei der Zeiterfassung: {ex.Message}");
        }
        finally
        {
            btnStartStopp.Enabled = true;
        }
    }
}
```

### 6. Quicklinks Konfiguration
```csharp
public class QuicklinksConfig
{
    public static List<QuicklinkInfo> GetQuicklinks()
    {
        return new List<QuicklinkInfo>
        {
            new QuicklinkInfo
            {
                Name = "Jobrouter",
                Url = "https://jobrouter.mikropartner.de",
                Icon = Resources.IconJobrouter,
                Tooltip = "Jobrouter/Urlaubsworkflow öffnen"
            },
            new QuicklinkInfo
            {
                Name = "Ticketsystem",
                Url = "https://ticket.mikropartner.de",
                Icon = Resources.IconTicket,
                Tooltip = "Ticketsystem öffnen"
            },
            new QuicklinkInfo
            {
                Name = "MPWeb 3.0",
                Url = "https://mpweb.mikropartner.de",
                Icon = Resources.IconMPWeb,
                Tooltip = "MPWeb 3.0 öffnen"
            },
            new QuicklinkInfo
            {
                Name = "Verbis",
                Url = "https://jobboerse2.arbeitsagentur.de/verbis/login",
                Icon = Resources.IconVerbis,
                Tooltip = "Verbis Jobbörse öffnen"
            },
            new QuicklinkInfo
            {
                Name = "Laufwerke",
                Url = @"C:\tools\NetzLW.bat",
                Icon = Resources.IconNetwork,
                Tooltip = "MP-Laufwerke verbinden",
                IsLocal = true
            },
            new QuicklinkInfo
            {
                Name = "Telefonliste",
                Url = @"O:\Mikropartner_Allgemein\Telefonliste_13_12_2024 Änderungen vorbehalten.pdf",
                Icon = Resources.IconPhone,
                Tooltip = "Aktuelle Telefonliste öffnen",
                IsLocal = true
            }
        };
    }
}
```

### 7. Benachrichtigungen und Feedback
```csharp
public class NotificationPanel : Panel
{
    private Timer _hideTimer;
    
    public void ShowNotification(string message, NotificationType type)
    {
        BackColor = GetColorForType(type);
        
        var lblMessage = new Label
        {
            Text = message,
            ForeColor = Color.White,
            AutoSize = true,
            Padding = new Padding(10)
        };
        
        Controls.Clear();
        Controls.Add(lblMessage);
        
        Visible = true;
        BringToFront();
        
        // Auto-hide nach 5 Sekunden
        _hideTimer?.Stop();
        _hideTimer = new Timer { Interval = 5000 };
        _hideTimer.Tick += (s, e) => 
        {
            Visible = false;
            _hideTimer.Stop();
        };
        _hideTimer.Start();
    }
}
```

### 8. Zeit-Zusammenfassung Dialog
```csharp
public class ZeitZusammenfassungDialog : Form
{
    public ZeitZusammenfassungDialog(Arbeitszeit arbeitszeit)
    {
        Text = "Arbeitszeit beendet";
        Size = new Size(400, 300);
        StartPosition = FormStartPosition.CenterParent;
        
        var lblZusammenfassung = new Label
        {
            Text = $"Arbeitszeit erfolgreich beendet!\n\n" +
                   $"Start: {arbeitszeit.Startzeit:HH:mm}\n" +
                   $"Ende: {arbeitszeit.Stoppzeit:HH:mm}\n" +
                   $"Arbeitszeit: {arbeitszeit.Arbeitszeit:hh\\:mm}\n" +
                   $"Pause: {arbeitszeit.Pausenzeit:hh\\:mm}\n\n" +
                   $"Standort: {arbeitszeit.Standort.Bezeichnung}",
            AutoSize = true,
            Location = new Point(20, 20)
        };
        
        Controls.Add(lblZusammenfassung);
        
        // Warnung bei Überstunden
        if (arbeitszeit.Arbeitszeit.TotalHours > 10)
        {
            var lblWarnung = new Label
            {
                Text = "⚠ Achtung: Mehr als 10 Stunden Arbeitszeit!",
                ForeColor = Color.Red,
                AutoSize = true,
                Location = new Point(20, 180)
            };
            Controls.Add(lblWarnung);
        }
    }
}
```

### 9. Status-Aktualisierung
```csharp
public class StatusRefreshService
{
    public async Task RefreshUserStatusAsync(StartPageControl control)
    {
        var status = await _zeiterfassungService.GetStatusAsync(
            _sessionManager.CurrentUser.BenutzerID
        );
        
        // Überstunden aktualisieren
        control.UpdateUeberstundenAnzeige(status.Ueberstunden);
        
        // Datum aktualisieren (Mitternacht)
        if (DateTime.Now.Date != _lastDateCheck.Date)
        {
            control.UpdateDatumAnzeige();
            _lastDateCheck = DateTime.Now;
        }
        
        // Warnungen prüfen (Freitags)
        if (DateTime.Now.DayOfWeek == DayOfWeek.Friday && 
            DateTime.Now.Hour >= 14)
        {
            await CheckWochenarbeitszeitWarnung(status);
        }
    }
}
```

### 10. Responsive Layout
```csharp
public class ResponsiveLayoutManager
{
    public void ConfigureStartPageLayout(StartPageControl control)
    {
        // TableLayoutPanel für responsive Anordnung
        var mainLayout = new TableLayoutPanel
        {
            Dock = DockStyle.Fill,
            ColumnCount = 1,
            RowCount = 8
        };
        
        // Zeilen-Definitionen
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 60)); // Benutzer
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 40)); // Datum
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 30)); // Label
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 150)); // Timer+Button
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 50)); // Arbeitszeiten
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 50)); // Stammdaten
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Absolute, 30)); // Quicklinks Label
        mainLayout.RowStyles.Add(new RowStyle(SizeType.Percent, 100)); // Quicklinks
        
        // Alle Controls zentriert
        foreach (Control control in mainLayout.Controls)
        {
            control.Anchor = AnchorStyles.None;
        }
    }
}
```

## Benötigte Dateien
- MainForm aus Schritt 4.1
- ZeiterfassungService aus Schritt 3.2
- AuthorizationService aus Schritt 3.3
- SessionManager aus Schritt 3.1

## Erwartete Ausgabe
```
UI/
├── Controls/
│   ├── StartPageControl.cs
│   ├── StartPageControl.Designer.cs
│   ├── StartPageControl.resx
│   ├── TimeDisplay.cs
│   ├── StartStoppButton.cs
│   ├── QuicklinkButton.cs
│   └── NotificationPanel.cs
├── Dialogs/
│   └── ZeitZusammenfassungDialog.cs
├── Services/
│   └── StatusRefreshService.cs
├── Models/
│   └── QuicklinkInfo.cs
└── Resources/
    ├── Icons/
    └── Strings/
        └── StartPage.resx
```

## Hinweise
- Timer Thread-Safe implementieren
- Keine blockierenden UI-Operationen
- Fehlerbehandlung für externe Links
- Accessibility: Tab-Order und Shortcuts
- Animations für besseres UX-Feedback
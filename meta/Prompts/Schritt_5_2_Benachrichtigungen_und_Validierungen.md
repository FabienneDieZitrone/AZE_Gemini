---
title: Schritt 5.2 - Benachrichtigungen und Validierungen
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_5_2_Benachrichtigungen_und_Validierungen.md
description: Detaillierter Prompt für die Implementierung von Benachrichtigungen und erweiterten Validierungen
---

# Schritt 5.2: Benachrichtigungen und Validierungen implementieren

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Anwendung verfügt bereits über grundlegende Funktionen und einen Offline-Modus. Jetzt sollen intelligente Benachrichtigungen und erweiterte Validierungen implementiert werden, um die Benutzer bei der korrekten Zeiterfassung zu unterstützen.

## Aufgabe
Entwickle ein umfassendes Benachrichtigungssystem mit verschiedenen Benachrichtigungstypen (Toast, E-Mail, In-App) und implementiere erweiterte Validierungen wie IP-Range-Prüfung, Freitags-Check und Arbeitszeitrichtlinien.

## Anforderungen

### 1. Benachrichtigungssystem (Arbeitszeiterfassung.BLL/Services/Notifications/)
```csharp
// INotificationService.cs
public interface INotificationService
{
    Task SendNotificationAsync(NotificationRequest request);
    Task<List<UserNotification>> GetUserNotificationsAsync(int userId);
    Task MarkAsReadAsync(int notificationId);
    Task<int> GetUnreadCountAsync(int userId);
    void RegisterNotificationChannel(INotificationChannel channel);
}

// NotificationService.cs
public class NotificationService : INotificationService
{
    private readonly List<INotificationChannel> _channels = new();
    private readonly INotificationRepository _repository;
    private readonly IUserPreferenceService _preferenceService;
    private readonly ILogger<NotificationService> _logger;
    
    public async Task SendNotificationAsync(NotificationRequest request)
    {
        try
        {
            // Prüfe Benutzereinstellungen
            var preferences = await _preferenceService.GetNotificationPreferencesAsync(request.UserId);
            
            // Speichere Benachrichtigung in Datenbank
            var notification = new UserNotification
            {
                UserId = request.UserId,
                Type = request.Type,
                Title = request.Title,
                Message = request.Message,
                Priority = request.Priority,
                CreatedAt = DateTime.UtcNow,
                IsRead = false,
                Metadata = JsonSerializer.Serialize(request.Metadata)
            };
            
            await _repository.AddAsync(notification);
            
            // Sende über aktivierte Kanäle
            var tasks = new List<Task>();
            
            foreach (var channel in _channels)
            {
                if (ShouldSendViaChannel(channel, request, preferences))
                {
                    tasks.Add(channel.SendAsync(request));
                }
            }
            
            await Task.WhenAll(tasks);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fehler beim Senden der Benachrichtigung");
            throw;
        }
    }
    
    private bool ShouldSendViaChannel(INotificationChannel channel, NotificationRequest request, UserNotificationPreferences preferences)
    {
        return channel.Type switch
        {
            ChannelType.Toast => preferences.EnableToastNotifications && request.Priority >= NotificationPriority.Normal,
            ChannelType.Email => preferences.EnableEmailNotifications && request.Priority >= NotificationPriority.High,
            ChannelType.InApp => true, // In-App immer aktiv
            _ => false
        };
    }
}

// Notification Models
public class NotificationRequest
{
    public int UserId { get; set; }
    public NotificationType Type { get; set; }
    public string Title { get; set; }
    public string Message { get; set; }
    public NotificationPriority Priority { get; set; }
    public Dictionary<string, object> Metadata { get; set; }
}

public enum NotificationType
{
    FreitagsErinnerung = 1,
    FehlendePause = 2,
    Überstunden = 3,
    GenehmigungErforderlich = 4,
    GenehmigungErteilt = 5,
    GenehmigungAbgelehnt = 6,
    StandortFehler = 7,
    SyncFehler = 8,
    SystemWartung = 9
}
```

### 2. Benachrichtigungskanäle (Arbeitszeiterfassung.BLL/Services/Notifications/Channels/)
```csharp
// ToastNotificationChannel.cs
public class ToastNotificationChannel : INotificationChannel
{
    public ChannelType Type => ChannelType.Toast;
    
    public async Task SendAsync(NotificationRequest request)
    {
        await Task.Run(() =>
        {
            // Windows Toast Notification
            var toastXml = ToastNotificationManager.GetTemplateContent(ToastTemplateType.ToastImageAndText04);
            
            var stringElements = toastXml.GetElementsByTagName("text");
            stringElements[0].AppendChild(toastXml.CreateTextNode(request.Title));
            stringElements[1].AppendChild(toastXml.CreateTextNode(request.Message));
            
            // Icon basierend auf Typ
            var imagePath = GetIconPath(request.Type);
            var imageElements = toastXml.GetElementsByTagName("image");
            imageElements[0].Attributes.GetNamedItem("src").NodeValue = imagePath;
            
            var toast = new ToastNotification(toastXml)
            {
                ExpirationTime = DateTimeOffset.Now.AddMinutes(5)
            };
            
            ToastNotificationManager.CreateToastNotifier("Arbeitszeiterfassung").Show(toast);
        });
    }
}

// EmailNotificationChannel.cs
public class EmailNotificationChannel : INotificationChannel
{
    private readonly IEmailService _emailService;
    private readonly IUserService _userService;
    
    public ChannelType Type => ChannelType.Email;
    
    public async Task SendAsync(NotificationRequest request)
    {
        var user = await _userService.GetByIdAsync(request.UserId);
        if (string.IsNullOrEmpty(user.Email)) return;
        
        var emailBody = BuildEmailBody(request);
        
        await _emailService.SendAsync(new EmailMessage
        {
            To = user.Email,
            Subject = $"[Arbeitszeiterfassung] {request.Title}",
            Body = emailBody,
            IsHtml = true,
            Priority = request.Priority == NotificationPriority.Urgent ? EmailPriority.High : EmailPriority.Normal
        });
    }
    
    private string BuildEmailBody(NotificationRequest request)
    {
        return $@"
            <html>
            <body style='font-family: Segoe UI, Arial, sans-serif;'>
                <div style='background-color: #003366; color: white; padding: 20px;'>
                    <h2>Arbeitszeiterfassung - {request.Title}</h2>
                </div>
                <div style='padding: 20px;'>
                    <p>{request.Message}</p>
                    {BuildMetadataHtml(request.Metadata)}
                </div>
                <div style='background-color: #f0f0f0; padding: 10px; margin-top: 20px;'>
                    <small>Diese Nachricht wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.</small>
                </div>
            </body>
            </html>";
    }
}
```

### 3. Erweiterte Validierungen (Arbeitszeiterfassung.BLL/Validators/)
```csharp
// IValidationService.cs
public interface IValidationService
{
    Task<ValidationResult> ValidateZeiterfassungAsync(ZeiterfassungDto zeiterfassung);
    Task<ValidationResult> ValidateStandortAsync(int userId, string clientIp);
    Task<ValidationResult> ValidatePausenAsync(int userId, DateTime datum);
    Task<ValidationResult> ValidateArbeitszeitAsync(int userId, DateTime datum);
}

// ValidationService.cs
public class ValidationService : IValidationService
{
    private readonly IStandortService _standortService;
    private readonly IZeiterfassungService _zeiterfassungService;
    private readonly IConfigurationManager _config;
    
    public async Task<ValidationResult> ValidateZeiterfassungAsync(ZeiterfassungDto zeiterfassung)
    {
        var result = new ValidationResult();
        
        // 1. Basisvalidierung
        if (zeiterfassung.Zeit == default)
        {
            result.AddError("Zeit", "Zeit muss angegeben werden");
        }
        
        // 2. Chronologische Validierung
        var tageseintraege = await _zeiterfassungService.GetTageseintraegeAsync(
            zeiterfassung.BenutzerId, 
            zeiterfassung.Zeit.Date
        );
        
        if (!IstChronologischKorrekt(zeiterfassung, tageseintraege))
        {
            result.AddError("Zeit", "Die Zeitangabe verletzt die chronologische Reihenfolge");
        }
        
        // 3. Doppelte Einträge prüfen
        if (HatDoppelteEintraege(zeiterfassung, tageseintraege))
        {
            result.AddError("Typ", "Ein Eintrag dieses Typs existiert bereits zur gleichen Zeit");
        }
        
        // 4. Pausenvalidierung
        if (zeiterfassung.Typ == ErfassungsTyp.PauseBeginn || zeiterfassung.Typ == ErfassungsTyp.PauseEnde)
        {
            var pausenResult = await ValidatePausenlogikAsync(zeiterfassung, tageseintraege);
            result.Merge(pausenResult);
        }
        
        return result;
    }
    
    public async Task<ValidationResult> ValidateStandortAsync(int userId, string clientIp)
    {
        var result = new ValidationResult();
        
        try
        {
            // Lade Benutzer-Standort
            var benutzer = await _benutzerService.GetByIdAsync(userId);
            var standort = await _standortService.GetByIdAsync(benutzer.StandortId);
            
            // Prüfe IP-Range
            var ipAddress = IPAddress.Parse(clientIp);
            var isValid = false;
            
            foreach (var ipRange in standort.IpRanges)
            {
                if (IsIpInRange(ipAddress, ipRange))
                {
                    isValid = true;
                    break;
                }
            }
            
            if (!isValid)
            {
                result.AddError("Standort", 
                    $"Ihre IP-Adresse {clientIp} ist nicht für den Standort {standort.Name} autorisiert");
                
                // Sende Benachrichtigung
                await _notificationService.SendNotificationAsync(new NotificationRequest
                {
                    UserId = userId,
                    Type = NotificationType.StandortFehler,
                    Title = "Standort-Validierung fehlgeschlagen",
                    Message = $"Zeiterfassung von unbekannter IP-Adresse: {clientIp}",
                    Priority = NotificationPriority.High
                });
            }
        }
        catch (Exception ex)
        {
            result.AddError("Standort", $"Fehler bei Standortprüfung: {ex.Message}");
        }
        
        return result;
    }
    
    private bool IsIpInRange(IPAddress address, string cidrRange)
    {
        var parts = cidrRange.Split('/');
        var baseAddress = IPAddress.Parse(parts[0]);
        var prefixLength = int.Parse(parts[1]);
        
        var baseBytes = baseAddress.GetAddressBytes();
        var addressBytes = address.GetAddressBytes();
        
        if (baseBytes.Length != addressBytes.Length) return false;
        
        var bytesToCheck = prefixLength / 8;
        var bitsToCheck = prefixLength % 8;
        
        for (int i = 0; i < bytesToCheck; i++)
        {
            if (baseBytes[i] != addressBytes[i]) return false;
        }
        
        if (bitsToCheck > 0 && bytesToCheck < baseBytes.Length)
        {
            var mask = (byte)(0xFF << (8 - bitsToCheck));
            if ((baseBytes[bytesToCheck] & mask) != (addressBytes[bytesToCheck] & mask))
                return false;
        }
        
        return true;
    }
}
```

### 4. Freitags-Check und Erinnerungen (Arbeitszeiterfassung.BLL/Services/)
```csharp
// ReminderService.cs
public class ReminderService : IReminderService
{
    private readonly Timer _timer;
    private readonly INotificationService _notificationService;
    private readonly IZeiterfassungService _zeiterfassungService;
    private readonly IBenutzerService _benutzerService;
    
    public ReminderService()
    {
        // Prüfe alle 30 Minuten
        _timer = new Timer(CheckReminders, null, TimeSpan.Zero, TimeSpan.FromMinutes(30));
    }
    
    private async void CheckReminders(object state)
    {
        try
        {
            var now = DateTime.Now;
            
            // Freitags-Check (14:00 Uhr)
            if (now.DayOfWeek == DayOfWeek.Friday && now.Hour == 14 && now.Minute < 30)
            {
                await SendFreitagsErinnerungen();
            }
            
            // Tägliche Pausenerinnerung (nach 6 Stunden)
            await CheckPausenErinnerungen();
            
            // Fehlende Zeiterfassung (17:30 Uhr)
            if (now.Hour == 17 && now.Minute == 30)
            {
                await CheckFehlendeZeiterfassungen();
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fehler bei Erinnerungsprüfung");
        }
    }
    
    private async Task SendFreitagsErinnerungen()
    {
        var activeUsers = await _benutzerService.GetActiveUsersAsync();
        
        foreach (var user in activeUsers)
        {
            var wochenstunden = await _zeiterfassungService.GetWochenstundenAsync(user.Id, DateTime.Now);
            
            if (wochenstunden < user.WochenSollStunden)
            {
                var fehlstunden = user.WochenSollStunden - wochenstunden;
                
                await _notificationService.SendNotificationAsync(new NotificationRequest
                {
                    UserId = user.Id,
                    Type = NotificationType.FreitagsErinnerung,
                    Title = "Wochenstunden-Erinnerung",
                    Message = $"Sie haben diese Woche erst {wochenstunden:F1} von {user.WochenSollStunden:F1} Stunden erfasst. " +
                             $"Es fehlen noch {fehlstunden:F1} Stunden.",
                    Priority = NotificationPriority.High,
                    Metadata = new Dictionary<string, object>
                    {
                        ["Wochenstunden"] = wochenstunden,
                        ["Sollstunden"] = user.WochenSollStunden,
                        ["Fehlstunden"] = fehlstunden
                    }
                });
            }
        }
    }
    
    private async Task CheckPausenErinnerungen()
    {
        var aktiveSessions = await _zeiterfassungService.GetActiveSessions();
        
        foreach (var session in aktiveSessions)
        {
            var arbeitszeit = DateTime.Now - session.StartZeit;
            
            // Nach 6 Stunden ohne Pause
            if (arbeitszeit.TotalHours >= 6 && !session.HattePause)
            {
                await _notificationService.SendNotificationAsync(new NotificationRequest
                {
                    UserId = session.BenutzerId,
                    Type = NotificationType.FehlendePause,
                    Title = "Pausenerinnerung",
                    Message = "Sie arbeiten seit über 6 Stunden ohne Pause. Gesetzlich ist eine 30-minütige Pause vorgeschrieben.",
                    Priority = NotificationPriority.Urgent
                });
            }
        }
    }
}
```

### 5. Validierungs-UI (Arbeitszeiterfassung.UI/Controls/)
```csharp
// ValidationFeedbackControl.cs
public partial class ValidationFeedbackControl : UserControl
{
    private readonly ErrorProvider _errorProvider;
    private readonly ToolTip _toolTip;
    
    public ValidationFeedbackControl()
    {
        InitializeComponent();
        _errorProvider = new ErrorProvider();
        _toolTip = new ToolTip
        {
            IsBalloon = true,
            ToolTipIcon = ToolTipIcon.Warning
        };
    }
    
    public void ShowValidationResult(Control control, ValidationResult result)
    {
        if (result.IsValid)
        {
            _errorProvider.SetError(control, string.Empty);
            control.BackColor = SystemColors.Window;
        }
        else
        {
            var errors = string.Join("\n", result.Errors.Select(e => $"• {e.Message}"));
            _errorProvider.SetError(control, errors);
            control.BackColor = Color.MistyRose;
            
            // Zeige ersten Fehler als Tooltip
            _toolTip.Show(result.Errors.First().Message, control, 3000);
        }
    }
    
    public void ShowInlineValidation(TextBox textBox, Func<string, ValidationResult> validator)
    {
        textBox.TextChanged += (s, e) =>
        {
            var result = validator(textBox.Text);
            ShowValidationResult(textBox, result);
        };
    }
}
```

### 6. Benachrichtigungscenter (Arbeitszeiterfassung.UI/Forms/)
```csharp
// FrmNotificationCenter.cs
public partial class FrmNotificationCenter : Form
{
    private readonly INotificationService _notificationService;
    private readonly int _userId;
    
    public FrmNotificationCenter(int userId)
    {
        InitializeComponent();
        _userId = userId;
        _notificationService = ServiceLocator.Get<INotificationService>();
        
        LoadNotifications();
        StartAutoRefresh();
    }
    
    private async void LoadNotifications()
    {
        try
        {
            var notifications = await _notificationService.GetUserNotificationsAsync(_userId);
            
            lvNotifications.Items.Clear();
            
            foreach (var notification in notifications.OrderByDescending(n => n.CreatedAt))
            {
                var item = new ListViewItem
                {
                    Text = notification.Title,
                    SubItems = 
                    {
                        notification.Message,
                        notification.CreatedAt.ToString("dd.MM.yyyy HH:mm"),
                        notification.Type.ToString()
                    },
                    Tag = notification,
                    Font = notification.IsRead ? Font : new Font(Font, FontStyle.Bold),
                    ImageIndex = GetImageIndex(notification.Type)
                };
                
                lvNotifications.Items.Add(item);
            }
            
            UpdateUnreadCount();
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Fehler beim Laden der Benachrichtigungen: {ex.Message}",
                "Fehler", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }
    
    private void lvNotifications_DoubleClick(object sender, EventArgs e)
    {
        if (lvNotifications.SelectedItems.Count == 0) return;
        
        var notification = (UserNotification)lvNotifications.SelectedItems[0].Tag;
        
        if (!notification.IsRead)
        {
            _ = Task.Run(async () =>
            {
                await _notificationService.MarkAsReadAsync(notification.Id);
                this.InvokeIfRequired(() => LoadNotifications());
            });
        }
        
        // Zeige Details
        using var dlg = new FrmNotificationDetails(notification);
        dlg.ShowDialog();
    }
}
```

### 7. Konfigurierbare Validierungsregeln
```csharp
// ValidationRuleEngine.cs
public class ValidationRuleEngine
{
    private readonly List<IValidationRule> _rules = new();
    
    public ValidationRuleEngine()
    {
        // Registriere Standard-Regeln
        RegisterRule(new MaxArbeitszeitRule(10)); // Max 10 Stunden/Tag
        RegisterRule(new MinPauseRule(30, 6)); // Min 30 Min Pause nach 6 Stunden
        RegisterRule(new MaxWochenarbeitszeitRule(48)); // Max 48 Stunden/Woche
        RegisterRule(new WochenendArbeitRule()); // Warnung bei Wochenendarbeit
    }
    
    public void RegisterRule(IValidationRule rule)
    {
        _rules.Add(rule);
    }
    
    public async Task<ValidationResult> ValidateAsync(ValidationContext context)
    {
        var result = new ValidationResult();
        
        foreach (var rule in _rules.Where(r => r.IsApplicable(context)))
        {
            var ruleResult = await rule.ValidateAsync(context);
            result.Merge(ruleResult);
        }
        
        return result;
    }
}

// Beispiel-Regel
public class MaxArbeitszeitRule : IValidationRule
{
    private readonly double _maxStunden;
    
    public MaxArbeitszeitRule(double maxStunden)
    {
        _maxStunden = maxStunden;
    }
    
    public bool IsApplicable(ValidationContext context)
    {
        return context.Type == ValidationType.Tagesarbeitszeit;
    }
    
    public async Task<ValidationResult> ValidateAsync(ValidationContext context)
    {
        var result = new ValidationResult();
        var arbeitszeit = await CalculateArbeitszeitAsync(context);
        
        if (arbeitszeit.TotalHours > _maxStunden)
        {
            result.AddWarning("Arbeitszeit", 
                $"Die Tagesarbeitszeit von {arbeitszeit.TotalHours:F1} Stunden überschreitet " +
                $"das Maximum von {_maxStunden} Stunden");
        }
        
        return result;
    }
}
```

## Erwartete Ergebnisse

1. **Umfassendes Benachrichtigungssystem** mit mehreren Kanälen
2. **Intelligente Validierungen** für alle Geschäftsregeln
3. **Proaktive Erinnerungen** für Benutzer
4. **Konfigurierbare Regeln** für verschiedene Szenarien
5. **Benutzerfreundliches Feedback** bei Validierungsfehlern
6. **Benachrichtigungscenter** zur Verwaltung aller Meldungen

## Zusätzliche Hinweise
- Implementiere Throttling für Benachrichtigungen
- Berücksichtige Zeitzonen bei Erinnerungen
- Ermögliche Benutzer-spezifische Einstellungen
- Protokolliere alle Benachrichtigungen für Compliance
- Teste verschiedene Netzwerk-Konfigurationen für IP-Validierung

## Nächste Schritte
Nach erfolgreicher Implementierung der Benachrichtigungen folgt Schritt 5.3: Änderungsprotokoll und Audit-Trail.
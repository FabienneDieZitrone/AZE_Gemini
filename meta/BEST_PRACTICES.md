---
title: Best Practices - Arbeitszeiterfassung Entwicklung
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/BEST_PRACTICES.md
description: Bewährte Praktiken und Coding-Standards für die Arbeitszeiterfassung
---

# 🌟 Best Practices: Arbeitszeiterfassung

## 📐 Architektur-Prinzipien

### 1. Separation of Concerns
```csharp
// ❌ FALSCH - Alles in einem
public partial class MainForm : Form
{
    private void btnSave_Click(object sender, EventArgs e)
    {
        using var conn = new MySqlConnection("Server=...");
        conn.Open();
        var cmd = new MySqlCommand("INSERT INTO...", conn);
        cmd.ExecuteNonQuery();
    }
}

// ✅ RICHTIG - Klare Trennung
public partial class MainForm : Form
{
    private readonly IZeiterfassungService _service;
    
    private async void btnSave_Click(object sender, EventArgs e)
    {
        try
        {
            var dto = GetFormData();
            await _service.SaveZeiterfassungAsync(dto);
            ShowSuccessMessage();
        }
        catch (Exception ex)
        {
            HandleError(ex);
        }
    }
}
```

### 2. Dependency Injection
```csharp
// ✅ Constructor Injection bevorzugen
public class ZeiterfassungService : IZeiterfassungService
{
    private readonly IZeiterfassungRepository _repository;
    private readonly IValidationService _validator;
    private readonly ILogger<ZeiterfassungService> _logger;
    
    public ZeiterfassungService(
        IZeiterfassungRepository repository,
        IValidationService validator,
        ILogger<ZeiterfassungService> logger)
    {
        _repository = repository ?? throw new ArgumentNullException(nameof(repository));
        _validator = validator ?? throw new ArgumentNullException(nameof(validator));
        _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    }
}
```

### 3. Interface-basiertes Design
```csharp
// ✅ Immer gegen Interfaces programmieren
public interface IZeiterfassungRepository
{
    Task<Zeiterfassung> GetByIdAsync(int id);
    Task<IEnumerable<Zeiterfassung>> GetByDateRangeAsync(int userId, DateTime von, DateTime bis);
    Task<Zeiterfassung> CreateAsync(Zeiterfassung entity);
    Task UpdateAsync(Zeiterfassung entity);
    Task DeleteAsync(int id);
}

// Konkrete Implementierung kann gewechselt werden
public class SqlZeiterfassungRepository : IZeiterfassungRepository { }
public class MongoZeiterfassungRepository : IZeiterfassungRepository { }
```

## 🔐 Sicherheit

### 1. Niemals Passwörter im Code
```csharp
// ❌ FALSCH
private const string ConnectionString = "Server=localhost;Password=Start.321;";

// ✅ RICHTIG - Aus Konfiguration laden
public class DatabaseConfig
{
    private readonly IConfiguration _configuration;
    
    public string GetConnectionString()
    {
        var connectionString = _configuration.GetConnectionString("Default");
        return DecryptIfNeeded(connectionString);
    }
}
```

### 2. SQL Injection Prevention
```csharp
// ❌ FALSCH - SQL Injection möglich
public async Task<User> GetUserAsync(string username)
{
    var sql = $"SELECT * FROM Users WHERE Username = '{username}'";
    return await _connection.QueryFirstOrDefaultAsync<User>(sql);
}

// ✅ RICHTIG - Parametrisierte Queries
public async Task<User> GetUserAsync(string username)
{
    var sql = "SELECT * FROM Users WHERE Username = @username";
    return await _connection.QueryFirstOrDefaultAsync<User>(sql, new { username });
}

// ✅ NOCH BESSER - Entity Framework
public async Task<User> GetUserAsync(string username)
{
    return await _context.Users
        .FirstOrDefaultAsync(u => u.Username == username);
}
```

### 3. Sensible Daten verschlüsseln
```csharp
public class EncryptionService : IEncryptionService
{
    public string Encrypt(string plainText)
    {
        using var aes = Aes.Create();
        aes.Key = GetEncryptionKey();
        aes.GenerateIV();
        
        var encryptor = aes.CreateEncryptor();
        var encrypted = encryptor.TransformFinalBlock(
            Encoding.UTF8.GetBytes(plainText), 0, plainText.Length);
            
        return Convert.ToBase64String(aes.IV.Concat(encrypted).ToArray());
    }
}
```

## 🧪 Testing

### 1. Arrange-Act-Assert Pattern
```csharp
[Fact]
public async Task StartZeiterfassung_Should_Create_New_Entry_When_No_Active_Session()
{
    // Arrange
    var userId = 1;
    var startTime = new DateTime(2025, 1, 26, 8, 0, 0);
    var mockRepo = new Mock<IZeiterfassungRepository>();
    mockRepo.Setup(r => r.GetActiveSessionAsync(userId))
            .ReturnsAsync((Zeiterfassung)null);
    
    var service = new ZeiterfassungService(mockRepo.Object);
    
    // Act
    var result = await service.StartZeiterfassungAsync(userId, startTime);
    
    // Assert
    Assert.NotNull(result);
    Assert.Equal(ErfassungsTyp.Kommen, result.Typ);
    mockRepo.Verify(r => r.CreateAsync(It.IsAny<Zeiterfassung>()), Times.Once);
}
```

### 2. Test Data Builders
```csharp
public class ZeiterfassungBuilder
{
    private Zeiterfassung _zeiterfassung = new();
    
    public ZeiterfassungBuilder WithUser(int userId)
    {
        _zeiterfassung.BenutzerId = userId;
        return this;
    }
    
    public ZeiterfassungBuilder WithTyp(ErfassungsTyp typ)
    {
        _zeiterfassung.Typ = typ;
        return this;
    }
    
    public ZeiterfassungBuilder AtTime(DateTime zeit)
    {
        _zeiterfassung.Zeit = zeit;
        return this;
    }
    
    public Zeiterfassung Build() => _zeiterfassung;
}

// Verwendung
var testData = new ZeiterfassungBuilder()
    .WithUser(1)
    .WithTyp(ErfassungsTyp.Kommen)
    .AtTime(DateTime.Now)
    .Build();
```

## 🎯 Error Handling

### 1. Spezifische Exceptions
```csharp
// ✅ Business-spezifische Exceptions
public class ZeiterfassungException : Exception
{
    public ZeiterfassungException(string message) : base(message) { }
}

public class DoppelteZeiterfassungException : ZeiterfassungException
{
    public DateTime Zeitpunkt { get; }
    
    public DoppelteZeiterfassungException(DateTime zeitpunkt) 
        : base($"Es existiert bereits eine Zeiterfassung für {zeitpunkt}")
    {
        Zeitpunkt = zeitpunkt;
    }
}

// Verwendung
if (await ExistsAtTimeAsync(zeit))
{
    throw new DoppelteZeiterfassungException(zeit);
}
```

### 2. Global Exception Handler
```csharp
// In Program.cs
Application.ThreadException += new ThreadExceptionEventHandler(GlobalExceptionHandler);
AppDomain.CurrentDomain.UnhandledException += new UnhandledExceptionEventHandler(UnhandledExceptionHandler);

private static void GlobalExceptionHandler(object sender, ThreadExceptionEventArgs e)
{
    _logger.LogError(e.Exception, "Unbehandelte Exception in UI-Thread");
    
    MessageBox.Show(
        "Ein unerwarteter Fehler ist aufgetreten. Die Anwendung wird beendet.",
        "Kritischer Fehler",
        MessageBoxButtons.OK,
        MessageBoxIcon.Error
    );
    
    Application.Exit();
}
```

## 🚀 Performance

### 1. Async/Await durchgängig
```csharp
// ❌ FALSCH - Blockiert UI
private void LoadData()
{
    var data = _service.GetDataAsync().Result; // BLOCKIERT!
    dataGridView.DataSource = data;
}

// ✅ RICHTIG - Non-blocking
private async void LoadData()
{
    try
    {
        ShowLoadingIndicator();
        var data = await _service.GetDataAsync();
        dataGridView.DataSource = data;
    }
    finally
    {
        HideLoadingIndicator();
    }
}
```

### 2. Dispose Pattern korrekt implementieren
```csharp
public class ZeiterfassungService : IZeiterfassungService, IDisposable
{
    private readonly Timer _syncTimer;
    private bool _disposed;
    
    public void Dispose()
    {
        Dispose(true);
        GC.SuppressFinalize(this);
    }
    
    protected virtual void Dispose(bool disposing)
    {
        if (!_disposed)
        {
            if (disposing)
            {
                // Managed resources
                _syncTimer?.Dispose();
            }
            
            // Unmanaged resources hier freigeben
            
            _disposed = true;
        }
    }
}
```

### 3. Caching für häufige Abfragen
```csharp
public class CachedStandortService : IStandortService
{
    private readonly IMemoryCache _cache;
    private readonly IStandortRepository _repository;
    
    public async Task<Standort> GetByIdAsync(int id)
    {
        return await _cache.GetOrCreateAsync($"standort_{id}", async entry =>
        {
            entry.SlidingExpiration = TimeSpan.FromMinutes(5);
            return await _repository.GetByIdAsync(id);
        });
    }
}
```

## 📝 Code-Dokumentation

### 1. XML-Dokumentation für öffentliche APIs
```csharp
/// <summary>
/// Startet eine neue Zeiterfassung für den angegebenen Benutzer.
/// </summary>
/// <param name="userId">Die ID des Benutzers</param>
/// <param name="zeitpunkt">Der Zeitpunkt der Erfassung</param>
/// <returns>Die erstellte Zeiterfassung</returns>
/// <exception cref="ArgumentException">Wenn userId ungültig ist</exception>
/// <exception cref="DoppelteZeiterfassungException">Wenn bereits eine aktive Session existiert</exception>
public async Task<ZeiterfassungDto> StartZeiterfassungAsync(int userId, DateTime zeitpunkt)
{
    // Implementation
}
```

### 2. Aussagekräftige Variablennamen
```csharp
// ❌ FALSCH
var d = DateTime.Now - s;
if (d.TotalHours > 6 && !p)
{
    // ???
}

// ✅ RICHTIG
var arbeitszeitHeute = DateTime.Now - arbeitsbeginn;
if (arbeitszeitHeute.TotalHours > 6 && !hattePause)
{
    await SendePausenErinnerungAsync();
}
```

## 🔄 Git Best Practices

### 1. Atomic Commits
```bash
# ❌ FALSCH - Zu viele Änderungen in einem Commit
git add .
git commit -m "Änderungen"

# ✅ RICHTIG - Kleine, fokussierte Commits
git add src/Services/ZeiterfassungService.cs
git commit -m "feat: Implementiere Pausenvalidierung in ZeiterfassungService"

git add tests/ZeiterfassungServiceTests.cs
git commit -m "test: Füge Tests für Pausenvalidierung hinzu"
```

### 2. Conventional Commits
```bash
# Format: <type>(<scope>): <subject>

feat(zeiterfassung): Füge Offline-Synchronisation hinzu
fix(ui): Korrigiere Thread-Safety-Problem in DataGrid
docs(api): Aktualisiere API-Dokumentation für v1.2
test(integration): Erweitere Tests für Genehmigungsworkflow
refactor(dal): Optimiere Repository-Pattern-Implementation
```

## 🎨 UI/UX Best Practices

### 1. Responsive Feedback
```csharp
private async void btnSave_Click(object sender, EventArgs e)
{
    // Sofortiges visuelles Feedback
    btnSave.Enabled = false;
    var originalText = btnSave.Text;
    btnSave.Text = "Speichert...";
    
    try
    {
        await SaveDataAsync();
        ShowSuccessNotification("Erfolgreich gespeichert!");
    }
    catch (Exception ex)
    {
        ShowErrorNotification($"Fehler: {ex.Message}");
    }
    finally
    {
        btnSave.Text = originalText;
        btnSave.Enabled = true;
    }
}
```

### 2. Konsistente Validierung
```csharp
public class ValidationHelper
{
    public static void SetValidationState(Control control, bool isValid, string errorMessage = null)
    {
        if (isValid)
        {
            control.BackColor = SystemColors.Window;
            toolTip.SetToolTip(control, string.Empty);
        }
        else
        {
            control.BackColor = Color.MistyRose;
            toolTip.SetToolTip(control, errorMessage);
        }
    }
}
```

## 📊 Logging Best Practices

### 1. Strukturiertes Logging
```csharp
public class ZeiterfassungService
{
    private readonly ILogger<ZeiterfassungService> _logger;
    
    public async Task<ZeiterfassungDto> StartZeiterfassungAsync(int userId, DateTime zeit)
    {
        using (_logger.BeginScope(new Dictionary<string, object>
        {
            ["UserId"] = userId,
            ["Operation"] = "StartZeiterfassung"
        }))
        {
            _logger.LogInformation("Starte Zeiterfassung für Benutzer {UserId} um {Zeit}", userId, zeit);
            
            try
            {
                var result = await ProcessZeiterfassungAsync(userId, zeit);
                _logger.LogInformation("Zeiterfassung erfolgreich erstellt mit ID {ZeiterfassungId}", result.Id);
                return result;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Fehler beim Starten der Zeiterfassung");
                throw;
            }
        }
    }
}
```

## 🏁 Deployment Checkliste

### Vor jedem Release:
- [ ] Alle Tests laufen erfolgreich
- [ ] Code-Coverage > 80%
- [ ] Keine TODO-Kommentare im Code
- [ ] Alle Warnungen behoben
- [ ] Security-Scan durchgeführt
- [ ] Performance-Tests bestanden
- [ ] Dokumentation aktualisiert
- [ ] CHANGELOG.md gepflegt
- [ ] Version-Nummer erhöht

---

**Goldene Regel**: Code wird öfter gelesen als geschrieben. Schreiben Sie für den nächsten Entwickler - das könnten Sie in 6 Monaten sein! 😊
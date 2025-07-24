---
title: Schritt 6.1 - Unit-Tests erstellen
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_6_1_Unit_Tests_erstellen.md
description: Detaillierter Prompt für die Erstellung umfassender Unit-Tests
---

# Schritt 6.1: Unit-Tests erstellen

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Anwendung ist funktional vollständig implementiert. Jetzt sollen umfassende Unit-Tests erstellt werden, um eine hohe Code-Qualität und Wartbarkeit sicherzustellen.

## Aufgabe
Entwickle eine vollständige Test-Suite mit Unit-Tests für alle kritischen Komponenten der Anwendung. Die Tests sollen mindestens 80% Code-Coverage erreichen und alle wichtigen Geschäftslogik-Szenarien abdecken.

## Anforderungen

### 1. Test-Projekt Setup (Arbeitszeiterfassung.Tests/)
```csharp
// TestBase.cs - Basis-Klasse für alle Tests
public abstract class TestBase : IDisposable
{
    protected readonly IServiceProvider ServiceProvider;
    protected readonly Mock<ILogger> LoggerMock;
    protected readonly CancellationToken CancellationToken;
    
    protected TestBase()
    {
        var services = new ServiceCollection();
        ConfigureServices(services);
        ServiceProvider = services.BuildServiceProvider();
        
        LoggerMock = new Mock<ILogger>();
        CancellationToken = new CancellationTokenSource(TimeSpan.FromSeconds(30)).Token;
    }
    
    protected virtual void ConfigureServices(IServiceCollection services)
    {
        // Basis-Services für Tests
        services.AddLogging(builder => builder.AddDebug());
        services.AddAutoMapper(typeof(MappingProfile).Assembly);
    }
    
    protected T GetService<T>() => ServiceProvider.GetRequiredService<T>();
    
    public void Dispose()
    {
        Dispose(true);
        GC.SuppressFinalize(this);
    }
    
    protected virtual void Dispose(bool disposing)
    {
        if (disposing)
        {
            (ServiceProvider as IDisposable)?.Dispose();
        }
    }
}

// DatabaseTestBase.cs - Für Tests mit Datenbank
public abstract class DatabaseTestBase : TestBase
{
    protected readonly ArbeitszeitDbContext Context;
    
    protected DatabaseTestBase()
    {
        var options = new DbContextOptionsBuilder<ArbeitszeitDbContext>()
            .UseInMemoryDatabase(databaseName: Guid.NewGuid().ToString())
            .Options;
            
        Context = new ArbeitszeitDbContext(options);
        Context.Database.EnsureCreated();
        
        SeedTestData();
    }
    
    protected virtual void SeedTestData()
    {
        // Standard-Testdaten
        var standorte = new List<Standort>
        {
            new() { Id = 1, Name = "Hauptstandort", IpRanges = new[] { "192.168.1.0/24" } },
            new() { Id = 2, Name = "Zweigstelle", IpRanges = new[] { "192.168.2.0/24" } }
        };
        
        var benutzer = new List<Benutzer>
        {
            new() { Id = 1, Username = "test.user", Name = "Test User", StandortId = 1, RolleId = 1 },
            new() { Id = 2, Username = "admin.user", Name = "Admin User", StandortId = 1, RolleId = 2 }
        };
        
        Context.Standorte.AddRange(standorte);
        Context.Benutzer.AddRange(benutzer);
        Context.SaveChanges();
    }
    
    protected override void Dispose(bool disposing)
    {
        if (disposing)
        {
            Context?.Dispose();
        }
        base.Dispose(disposing);
    }
}
```

### 2. Service-Tests (Arbeitszeiterfassung.Tests/BLL/Services/)
```csharp
// ZeiterfassungServiceTests.cs
public class ZeiterfassungServiceTests : DatabaseTestBase
{
    private readonly IZeiterfassungService _service;
    private readonly Mock<INotificationService> _notificationServiceMock;
    private readonly Mock<IValidationService> _validationServiceMock;
    
    public ZeiterfassungServiceTests()
    {
        _notificationServiceMock = new Mock<INotificationService>();
        _validationServiceMock = new Mock<IValidationService>();
        
        _service = new ZeiterfassungService(
            new ZeiterfassungRepository(Context),
            _validationServiceMock.Object,
            _notificationServiceMock.Object,
            GetService<IMapper>(),
            new Mock<ILogger<ZeiterfassungService>>().Object
        );
    }
    
    [Fact]
    public async Task StartZeiterfassung_ShouldCreateNewEntry_WhenNoActiveSession()
    {
        // Arrange
        var userId = 1;
        var startTime = new DateTime(2025, 1, 26, 8, 0, 0);
        
        _validationServiceMock
            .Setup(v => v.ValidateStandortAsync(userId, It.IsAny<string>()))
            .ReturnsAsync(new ValidationResult { IsValid = true });
        
        // Act
        var result = await _service.StartZeiterfassungAsync(userId, startTime, "192.168.1.100");
        
        // Assert
        result.Should().NotBeNull();
        result.BenutzerId.Should().Be(userId);
        result.Typ.Should().Be(ErfassungsTyp.Kommen);
        result.Zeit.Should().Be(startTime);
        
        var dbEntry = await Context.Zeiterfassungen.FirstOrDefaultAsync(z => z.Id == result.Id);
        dbEntry.Should().NotBeNull();
    }
    
    [Fact]
    public async Task StartZeiterfassung_ShouldThrowException_WhenActiveSessionExists()
    {
        // Arrange
        var userId = 1;
        Context.Zeiterfassungen.Add(new Zeiterfassung
        {
            BenutzerId = userId,
            Typ = ErfassungsTyp.Kommen,
            Zeit = DateTime.Now.AddHours(-1)
        });
        await Context.SaveChangesAsync();
        
        // Act & Assert
        await _service.Invoking(s => s.StartZeiterfassungAsync(userId, DateTime.Now, "192.168.1.100"))
            .Should().ThrowAsync<BusinessException>()
            .WithMessage("Es existiert bereits eine aktive Zeiterfassung");
    }
    
    [Theory]
    [InlineData(6.0, false, true)]  // 6 Stunden ohne Pause -> Warnung
    [InlineData(5.5, false, false)] // 5.5 Stunden ohne Pause -> OK
    [InlineData(8.0, true, false)]  // 8 Stunden mit Pause -> OK
    public async Task ValidateArbeitszeit_ShouldCheckPauseRequirements(
        double arbeitsstunden, bool hattePause, bool sollteWarnungGeben)
    {
        // Arrange
        var userId = 1;
        var startTime = DateTime.Today.AddHours(8);
        var endTime = startTime.AddHours(arbeitsstunden);
        
        var eintraege = new List<ZeiterfassungDto>
        {
            new() { Typ = ErfassungsTyp.Kommen, Zeit = startTime }
        };
        
        if (hattePause)
        {
            eintraege.Add(new() { Typ = ErfassungsTyp.PauseBeginn, Zeit = startTime.AddHours(4) });
            eintraege.Add(new() { Typ = ErfassungsTyp.PauseEnde, Zeit = startTime.AddHours(4.5) });
        }
        
        eintraege.Add(new() { Typ = ErfassungsTyp.Gehen, Zeit = endTime });
        
        // Act
        var validationResult = await _service.ValidateArbeitszeitAsync(userId, eintraege);
        
        // Assert
        if (sollteWarnungGeben)
        {
            validationResult.Warnings.Should().Contain(w => w.Contains("Pause"));
        }
        else
        {
            validationResult.Warnings.Should().BeEmpty();
        }
    }
    
    [Fact]
    public async Task GetMonatsstatistik_ShouldCalculateCorrectly()
    {
        // Arrange
        var userId = 1;
        var monat = new DateTime(2025, 1, 1);
        
        // Testdaten für Januar 2025
        var testEintraege = GenerateMonatsTestdaten(userId, monat);
        Context.Zeiterfassungen.AddRange(testEintraege);
        await Context.SaveChangesAsync();
        
        // Act
        var statistik = await _service.GetMonatsstatistikAsync(userId, monat);
        
        // Assert
        statistik.Should().NotBeNull();
        statistik.Arbeitstage.Should().Be(22); // Werktage im Januar 2025
        statistik.GesamtStunden.Should().BeApproximately(176, 0.1); // 22 * 8
        statistik.Überstunden.Should().BeApproximately(0, 0.1);
        statistik.Fehlzeiten.Should().Be(0);
    }
}
```

### 3. Repository-Tests (Arbeitszeiterfassung.Tests/DAL/Repositories/)
```csharp
// ZeiterfassungRepositoryTests.cs
public class ZeiterfassungRepositoryTests : DatabaseTestBase
{
    private readonly IZeiterfassungRepository _repository;
    
    public ZeiterfassungRepositoryTests()
    {
        _repository = new ZeiterfassungRepository(Context);
    }
    
    [Fact]
    public async Task GetByDateRange_ShouldReturnCorrectEntries()
    {
        // Arrange
        var userId = 1;
        var startDate = new DateTime(2025, 1, 20);
        var endDate = new DateTime(2025, 1, 26);
        
        var entries = new List<Zeiterfassung>
        {
            new() { BenutzerId = userId, Zeit = new DateTime(2025, 1, 19), Typ = ErfassungsTyp.Kommen },
            new() { BenutzerId = userId, Zeit = new DateTime(2025, 1, 20), Typ = ErfassungsTyp.Kommen },
            new() { BenutzerId = userId, Zeit = new DateTime(2025, 1, 25), Typ = ErfassungsTyp.Kommen },
            new() { BenutzerId = userId, Zeit = new DateTime(2025, 1, 27), Typ = ErfassungsTyp.Kommen },
            new() { BenutzerId = 2, Zeit = new DateTime(2025, 1, 22), Typ = ErfassungsTyp.Kommen }
        };
        
        Context.Zeiterfassungen.AddRange(entries);
        await Context.SaveChangesAsync();
        
        // Act
        var result = await _repository.GetByDateRangeAsync(userId, startDate, endDate);
        
        // Assert
        result.Should().HaveCount(2);
        result.Should().OnlyContain(z => z.BenutzerId == userId);
        result.Should().OnlyContain(z => z.Zeit >= startDate && z.Zeit <= endDate);
    }
    
    [Fact]
    public async Task GetActiveSession_ShouldReturnLastKommenWithoutGehen()
    {
        // Arrange
        var userId = 1;
        var entries = new List<Zeiterfassung>
        {
            new() { BenutzerId = userId, Zeit = DateTime.Now.AddHours(-3), Typ = ErfassungsTyp.Kommen },
            new() { BenutzerId = userId, Zeit = DateTime.Now.AddHours(-2), Typ = ErfassungsTyp.Gehen },
            new() { BenutzerId = userId, Zeit = DateTime.Now.AddHours(-1), Typ = ErfassungsTyp.Kommen }
        };
        
        Context.Zeiterfassungen.AddRange(entries);
        await Context.SaveChangesAsync();
        
        // Act
        var activeSession = await _repository.GetActiveSessionAsync(userId);
        
        // Assert
        activeSession.Should().NotBeNull();
        activeSession.Zeit.Should().BeCloseTo(DateTime.Now.AddHours(-1), TimeSpan.FromSeconds(1));
    }
}
```

### 4. Validator-Tests (Arbeitszeiterfassung.Tests/BLL/Validators/)
```csharp
// StandortValidatorTests.cs
public class StandortValidatorTests : TestBase
{
    private readonly IStandortValidator _validator;
    
    public StandortValidatorTests()
    {
        _validator = new StandortValidator();
    }
    
    [Theory]
    [InlineData("192.168.1.100", "192.168.1.0/24", true)]
    [InlineData("192.168.2.100", "192.168.1.0/24", false)]
    [InlineData("10.0.0.50", "10.0.0.0/16", true)]
    [InlineData("172.16.0.1", "10.0.0.0/8", false)]
    public void IsIpInRange_ShouldValidateCorrectly(string ip, string cidr, bool expected)
    {
        // Act
        var result = _validator.IsIpInRange(IPAddress.Parse(ip), cidr);
        
        // Assert
        result.Should().Be(expected);
    }
    
    [Fact]
    public void ValidateMultipleRanges_ShouldAcceptIfAnyMatches()
    {
        // Arrange
        var clientIp = "192.168.2.50";
        var standort = new Standort
        {
            Name = "Test",
            IpRanges = new[] { "192.168.1.0/24", "192.168.2.0/24", "10.0.0.0/16" }
        };
        
        // Act
        var result = _validator.ValidateStandort(clientIp, standort);
        
        // Assert
        result.IsValid.Should().BeTrue();
    }
}

// ZeiterfassungValidatorTests.cs
public class ZeiterfassungValidatorTests : DatabaseTestBase
{
    private readonly IZeiterfassungValidator _validator;
    
    public ZeiterfassungValidatorTests()
    {
        _validator = new ZeiterfassungValidator(new ZeiterfassungRepository(Context));
    }
    
    [Fact]
    public async Task ValidateChronologie_ShouldRejectOutOfOrderEntries()
    {
        // Arrange
        var userId = 1;
        var datum = DateTime.Today;
        
        Context.Zeiterfassungen.AddRange(new[]
        {
            new Zeiterfassung { BenutzerId = userId, Zeit = datum.AddHours(8), Typ = ErfassungsTyp.Kommen },
            new Zeiterfassung { BenutzerId = userId, Zeit = datum.AddHours(12), Typ = ErfassungsTyp.PauseBeginn }
        });
        await Context.SaveChangesAsync();
        
        var neuerEintrag = new ZeiterfassungDto
        {
            BenutzerId = userId,
            Zeit = datum.AddHours(10), // Zwischen Kommen und Pause
            Typ = ErfassungsTyp.Gehen // Ungültig!
        };
        
        // Act
        var result = await _validator.ValidateAsync(neuerEintrag);
        
        // Assert
        result.IsValid.Should().BeFalse();
        result.Errors.Should().Contain(e => e.PropertyName == "Zeit" && e.ErrorMessage.Contains("chronologisch"));
    }
}
```

### 5. Integration-Tests für Controllers (Arbeitszeiterfassung.Tests/UI/)
```csharp
// MainFormTests.cs
[Collection("UI Tests")]
public class MainFormTests : IDisposable
{
    private readonly MainForm _form;
    private readonly Mock<IZeiterfassungService> _zeiterfassungServiceMock;
    private readonly Mock<IBenutzerService> _benutzerServiceMock;
    
    public MainFormTests()
    {
        _zeiterfassungServiceMock = new Mock<IZeiterfassungService>();
        _benutzerServiceMock = new Mock<IBenutzerService>();
        
        // Setup DI Container für Tests
        ServiceLocator.Initialize(services =>
        {
            services.AddSingleton(_zeiterfassungServiceMock.Object);
            services.AddSingleton(_benutzerServiceMock.Object);
        });
        
        _form = new MainForm();
    }
    
    [WinFormsFact]
    public void StartButton_Click_ShouldStartZeiterfassung()
    {
        // Arrange
        var startButton = _form.Controls.Find("btnStart", true).FirstOrDefault() as Button;
        startButton.Should().NotBeNull();
        
        _zeiterfassungServiceMock
            .Setup(s => s.StartZeiterfassungAsync(It.IsAny<int>(), It.IsAny<DateTime>(), It.IsAny<string>()))
            .ReturnsAsync(new ZeiterfassungDto { Id = 1, Typ = ErfassungsTyp.Kommen });
        
        // Act
        startButton.PerformClick();
        Application.DoEvents(); // Process events
        
        // Assert
        _zeiterfassungServiceMock.Verify(s => 
            s.StartZeiterfassungAsync(It.IsAny<int>(), It.IsAny<DateTime>(), It.IsAny<string>()), 
            Times.Once);
        
        var statusLabel = _form.Controls.Find("lblStatus", true).FirstOrDefault() as Label;
        statusLabel.Text.Should().Contain("Arbeitszeit läuft");
    }
    
    public void Dispose()
    {
        _form?.Dispose();
    }
}
```

### 6. Test-Utilities und Helpers (Arbeitszeiterfassung.Tests/Utilities/)
```csharp
// TestDataBuilder.cs
public class TestDataBuilder
{
    private readonly List<Zeiterfassung> _eintraege = new();
    private int _userId = 1;
    private DateTime _currentDate = DateTime.Today;
    
    public TestDataBuilder ForUser(int userId)
    {
        _userId = userId;
        return this;
    }
    
    public TestDataBuilder OnDate(DateTime date)
    {
        _currentDate = date.Date;
        return this;
    }
    
    public TestDataBuilder AddArbeitstag(TimeSpan start, TimeSpan ende, TimeSpan? pauseStart = null, TimeSpan? pauseDauer = null)
    {
        _eintraege.Add(new Zeiterfassung
        {
            BenutzerId = _userId,
            Zeit = _currentDate.Add(start),
            Typ = ErfassungsTyp.Kommen
        });
        
        if (pauseStart.HasValue && pauseDauer.HasValue)
        {
            _eintraege.Add(new Zeiterfassung
            {
                BenutzerId = _userId,
                Zeit = _currentDate.Add(pauseStart.Value),
                Typ = ErfassungsTyp.PauseBeginn
            });
            
            _eintraege.Add(new Zeiterfassung
            {
                BenutzerId = _userId,
                Zeit = _currentDate.Add(pauseStart.Value.Add(pauseDauer.Value)),
                Typ = ErfassungsTyp.PauseEnde
            });
        }
        
        _eintraege.Add(new Zeiterfassung
        {
            BenutzerId = _userId,
            Zeit = _currentDate.Add(ende),
            Typ = ErfassungsTyp.Gehen
        });
        
        return this;
    }
    
    public List<Zeiterfassung> Build() => _eintraege;
}

// AssertionExtensions.cs
public static class AssertionExtensions
{
    public static void ShouldBeValidZeiterfassung(this ZeiterfassungDto actual)
    {
        actual.Should().NotBeNull();
        actual.Id.Should().BeGreaterThan(0);
        actual.BenutzerId.Should().BeGreaterThan(0);
        actual.Zeit.Should().NotBe(default(DateTime));
        actual.Typ.Should().BeOneOf(Enum.GetValues<ErfassungsTyp>());
    }
    
    public static void ShouldHaveValidationError(this ValidationResult result, string propertyName)
    {
        result.IsValid.Should().BeFalse();
        result.Errors.Should().Contain(e => e.PropertyName == propertyName);
    }
}
```

### 7. Performance-Tests
```csharp
// PerformanceTests.cs
public class PerformanceTests : DatabaseTestBase
{
    [Fact]
    public async Task GetMonatsstatistik_ShouldCompleteWithinTimeout_ForLargeDataset()
    {
        // Arrange
        var userId = 1;
        var monat = new DateTime(2025, 1, 1);
        
        // Generiere 10.000 Einträge
        var entries = GenerateLargeDataset(userId, 10000);
        Context.Zeiterfassungen.AddRange(entries);
        await Context.SaveChangesAsync();
        
        var service = new ZeiterfassungService(
            new ZeiterfassungRepository(Context),
            Mock.Of<IValidationService>(),
            Mock.Of<INotificationService>(),
            GetService<IMapper>(),
            Mock.Of<ILogger<ZeiterfassungService>>()
        );
        
        // Act & Assert
        var stopwatch = Stopwatch.StartNew();
        var result = await service.GetMonatsstatistikAsync(userId, monat);
        stopwatch.Stop();
        
        result.Should().NotBeNull();
        stopwatch.ElapsedMilliseconds.Should().BeLessThan(1000); // Max 1 Sekunde
    }
}
```

### 8. Test-Coverage Report
```xml
<!-- coverlet.runsettings -->
<?xml version="1.0" encoding="utf-8"?>
<RunSettings>
  <DataCollectionRunSettings>
    <DataCollectors>
      <DataCollector friendlyName="XPlat Code Coverage">
        <Configuration>
          <Format>cobertura</Format>
          <Exclude>[xunit.*]*,[*.Tests]*</Exclude>
          <Include>[Arbeitszeiterfassung.*]*</Include>
          <ExcludeByAttribute>Obsolete,GeneratedCodeAttribute,CompilerGeneratedAttribute</ExcludeByAttribute>
          <ExcludeByFile>**/Migrations/*.cs,**/obj/**/*.cs</ExcludeByFile>
          <UseSourceLink>true</UseSourceLink>
        </Configuration>
      </DataCollector>
    </DataCollectors>
  </DataCollectionRunSettings>
</RunSettings>
```

### 9. Test-Execution Script
```powershell
# run-tests.ps1
param(
    [string]$Configuration = "Debug",
    [switch]$Coverage,
    [switch]$GenerateReport
)

Write-Host "Running Arbeitszeiterfassung Tests..." -ForegroundColor Cyan

# Clean previous results
Remove-Item -Path "TestResults" -Recurse -Force -ErrorAction SilentlyContinue

# Run tests
$testArgs = @(
    "test",
    "--configuration", $Configuration,
    "--logger", "console;verbosity=normal",
    "--logger", "trx;LogFileName=test-results.trx"
)

if ($Coverage) {
    $testArgs += @(
        "--collect", "XPlat Code Coverage",
        "--settings", "coverlet.runsettings"
    )
}

dotnet @testArgs

if ($LASTEXITCODE -ne 0) {
    Write-Host "Tests failed!" -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "All tests passed!" -ForegroundColor Green

if ($Coverage -and $GenerateReport) {
    Write-Host "Generating coverage report..." -ForegroundColor Yellow
    
    # Install ReportGenerator if not present
    dotnet tool install -g dotnet-reportgenerator-globaltool
    
    # Generate HTML report
    reportgenerator `
        -reports:"TestResults/**/coverage.cobertura.xml" `
        -targetdir:"TestResults/CoverageReport" `
        -reporttypes:"Html;Cobertura"
    
    # Open report
    Start-Process "TestResults/CoverageReport/index.html"
}
```

## Erwartete Ergebnisse

1. **Umfassende Test-Suite** mit >80% Code-Coverage
2. **Schnelle Test-Ausführung** (<5 Minuten für alle Tests)
3. **Isolierte Tests** ohne externe Abhängigkeiten
4. **Aussagekräftige Test-Namen** und Fehlermeldungen
5. **Performance-Tests** für kritische Operationen
6. **Automatisierte Test-Reports** mit Coverage-Analyse

## Zusätzliche Hinweise
- Verwende AAA-Pattern (Arrange, Act, Assert)
- Mocke externe Abhängigkeiten konsequent
- Teste Edge-Cases und Fehlerszenarien
- Verwende Theory-Tests für parametrisierte Szenarien
- Implementiere Test-Kategorien für selektive Ausführung

## Nächste Schritte
Nach erfolgreicher Erstellung der Unit-Tests folgt Schritt 6.2: Integrationstests.
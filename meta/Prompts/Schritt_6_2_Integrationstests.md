---
title: Schritt 6.2 - Integrationstests
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_6_2_Integrationstests.md
description: Detaillierter Prompt für die Erstellung von End-to-End Integrationstests
---

# Schritt 6.2: Integrationstests implementieren

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Unit-Tests sind bereits implementiert. Jetzt sollen Integrationstests erstellt werden, die das Zusammenspiel aller Komponenten end-to-end testen.

## Aufgabe
Entwickle umfassende Integrationstests, die reale Anwendungsszenarien simulieren und das korrekte Zusammenspiel von UI, Geschäftslogik, Datenbank und externen Services überprüfen.

## Anforderungen

### 1. Test-Infrastruktur (Arbeitszeiterfassung.IntegrationTests/)
```csharp
// IntegrationTestBase.cs
public abstract class IntegrationTestBase : IClassFixture<TestApplicationFactory>
{
    protected readonly TestApplicationFactory Factory;
    protected readonly HttpClient Client;
    protected readonly IServiceProvider ServiceProvider;
    
    protected IntegrationTestBase(TestApplicationFactory factory)
    {
        Factory = factory;
        Client = factory.CreateClient();
        ServiceProvider = factory.Services;
        
        // Reset Datenbank vor jedem Test
        ResetDatabase();
    }
    
    protected T GetService<T>() => ServiceProvider.GetRequiredService<T>();
    
    protected async Task<TResponse> PostAsJsonAsync<TRequest, TResponse>(string url, TRequest data)
    {
        var response = await Client.PostAsJsonAsync(url, data);
        response.EnsureSuccessStatusCode();
        return await response.Content.ReadFromJsonAsync<TResponse>();
    }
    
    private void ResetDatabase()
    {
        using var scope = ServiceProvider.CreateScope();
        var context = scope.ServiceProvider.GetRequiredService<ArbeitszeitDbContext>();
        
        context.Database.EnsureDeleted();
        context.Database.EnsureCreated();
        
        SeedTestData(context);
    }
    
    protected virtual void SeedTestData(ArbeitszeitDbContext context)
    {
        // Standard-Testdaten für alle Tests
        TestDataSeeder.SeedStandardData(context);
    }
}

// TestApplicationFactory.cs
public class TestApplicationFactory : WebApplicationFactory<Program>
{
    protected override void ConfigureWebHost(IWebHostBuilder builder)
    {
        builder.ConfigureServices(services =>
        {
            // Entferne echte Datenbank
            var descriptor = services.SingleOrDefault(
                d => d.ServiceType == typeof(DbContextOptions<ArbeitszeitDbContext>));
            if (descriptor != null)
                services.Remove(descriptor);
            
            // Füge Test-Datenbank hinzu
            services.AddDbContext<ArbeitszeitDbContext>(options =>
            {
                options.UseInMemoryDatabase("IntegrationTestDb");
            });
            
            // Mock externe Services
            services.AddSingleton<IEmailService, MockEmailService>();
            services.AddSingleton<INetworkService, MockNetworkService>();
            
            // Test-spezifische Konfiguration
            services.Configure<ApplicationSettings>(opts =>
            {
                opts.EnableOfflineMode = true;
                opts.SessionTimeout = 5; // Kurze Timeouts für Tests
            });
        });
        
        builder.UseEnvironment("Testing");
    }
}
```

### 2. Komplette Workflow-Tests (Arbeitszeiterfassung.IntegrationTests/Workflows/)
```csharp
// ZeiterfassungWorkflowTests.cs
public class ZeiterfassungWorkflowTests : IntegrationTestBase
{
    public ZeiterfassungWorkflowTests(TestApplicationFactory factory) : base(factory) { }
    
    [Fact]
    public async Task CompleteWorkday_Workflow_ShouldWork()
    {
        // Arrange
        var testUser = TestUsers.StandardMitarbeiter;
        var workDate = DateTime.Today;
        var clientIp = "192.168.1.100";
        
        // Act & Assert - Kompletter Arbeitstag
        
        // 1. Benutzer kommt zur Arbeit (8:00)
        var kommenTime = workDate.AddHours(8);
        var kommenResult = await SimulateZeiterfassung(testUser.Id, ErfassungsTyp.Kommen, kommenTime, clientIp);
        
        kommenResult.Should().NotBeNull();
        kommenResult.Success.Should().BeTrue();
        
        // 2. Benutzer macht Pause (12:00)
        var pauseStartTime = workDate.AddHours(12);
        var pauseStartResult = await SimulateZeiterfassung(testUser.Id, ErfassungsTyp.PauseBeginn, pauseStartTime, clientIp);
        
        pauseStartResult.Success.Should().BeTrue();
        
        // 3. Benutzer beendet Pause (12:30)
        var pauseEndTime = workDate.AddHours(12.5);
        var pauseEndResult = await SimulateZeiterfassung(testUser.Id, ErfassungsTyp.PauseEnde, pauseEndTime, clientIp);
        
        pauseEndResult.Success.Should().BeTrue();
        
        // 4. Benutzer geht nach Hause (17:00)
        var gehenTime = workDate.AddHours(17);
        var gehenResult = await SimulateZeiterfassung(testUser.Id, ErfassungsTyp.Gehen, gehenTime, clientIp);
        
        gehenResult.Success.Should().BeTrue();
        
        // 5. Verifiziere Tagesstatistik
        var tagesstatistik = await GetTagesstatistik(testUser.Id, workDate);
        
        tagesstatistik.Arbeitszeit.Should().Be(TimeSpan.FromHours(8.5)); // 9h - 0.5h Pause
        tagesstatistik.Pausenzeit.Should().Be(TimeSpan.FromMinutes(30));
        tagesstatistik.Status.Should().Be("Abgeschlossen");
    }
    
    [Fact]
    public async Task InvalidWorkflow_ShouldBeRejected()
    {
        // Arrange
        var testUser = TestUsers.StandardMitarbeiter;
        var workDate = DateTime.Today;
        
        // Act - Versuche direkt "Gehen" ohne "Kommen"
        var invalidResult = await SimulateZeiterfassung(
            testUser.Id, 
            ErfassungsTyp.Gehen, 
            workDate.AddHours(17), 
            "192.168.1.100"
        );
        
        // Assert
        invalidResult.Success.Should().BeFalse();
        invalidResult.ErrorMessage.Should().Contain("Kein aktiver Arbeitstag");
    }
    
    [Fact]
    public async Task Genehmigungsworkflow_CompleteProcess()
    {
        // Arrange
        var mitarbeiter = TestUsers.StandardMitarbeiter;
        var vorgesetzter = TestUsers.Bereichsleiter;
        var aenderungsDatum = DateTime.Today.AddDays(-7);
        
        // 1. Mitarbeiter erstellt nachträgliche Zeiterfassung
        var nachtraeglicheErfassung = new NachtraeglicheZeiterfassungDto
        {
            BenutzerId = mitarbeiter.Id,
            Datum = aenderungsDatum,
            Eintraege = new List<ZeiterfassungDto>
            {
                new() { Typ = ErfassungsTyp.Kommen, Zeit = aenderungsDatum.AddHours(9) },
                new() { Typ = ErfassungsTyp.Gehen, Zeit = aenderungsDatum.AddHours(18) }
            },
            Begruendung = "Vergessen zu erfassen - war auf Schulung"
        };
        
        var antragResult = await CreateGenehmigungsantrag(nachtraeglicheErfassung);
        antragResult.AntragId.Should().BeGreaterThan(0);
        
        // 2. Vorgesetzter sieht offene Anträge
        var offeneAntraege = await GetOffeneAntraege(vorgesetzter.Id);
        offeneAntraege.Should().Contain(a => a.Id == antragResult.AntragId);
        
        // 3. Vorgesetzter genehmigt
        var genehmigungResult = await ProcessGenehmigung(
            antragResult.AntragId, 
            vorgesetzter.Id, 
            true, 
            "Schulung bestätigt"
        );
        
        genehmigungResult.Success.Should().BeTrue();
        
        // 4. Verifiziere dass Zeiten jetzt erfasst sind
        var zeiterfassungen = await GetZeiterfassungen(mitarbeiter.Id, aenderungsDatum);
        zeiterfassungen.Should().HaveCount(2);
        zeiterfassungen.Should().Contain(z => z.IstGenehmigt && z.GenehmigtVon == vorgesetzter.Username);
    }
}
```

### 3. Offline-Synchronisations-Tests (Arbeitszeiterfassung.IntegrationTests/Sync/)
```csharp
// OfflineSyncIntegrationTests.cs
public class OfflineSyncIntegrationTests : IntegrationTestBase
{
    private readonly IOfflineService _offlineService;
    private readonly ISyncService _syncService;
    
    public OfflineSyncIntegrationTests(TestApplicationFactory factory) : base(factory)
    {
        _offlineService = GetService<IOfflineService>();
        _syncService = GetService<ISyncService>();
    }
    
    [Fact]
    public async Task OfflineToOnlineSync_ShouldMergeCorrectly()
    {
        // Arrange - Simuliere Offline-Modus
        await _offlineService.EnableOfflineModeAsync();
        
        var userId = TestUsers.StandardMitarbeiter.Id;
        var offlineDate = DateTime.Today;
        
        // Act - Erfasse Zeiten offline
        var offlineEintraege = new List<ZeiterfassungDto>
        {
            new() { BenutzerId = userId, Typ = ErfassungsTyp.Kommen, Zeit = offlineDate.AddHours(8) },
            new() { BenutzerId = userId, Typ = ErfassungsTyp.PauseBeginn, Zeit = offlineDate.AddHours(12) },
            new() { BenutzerId = userId, Typ = ErfassungsTyp.PauseEnde, Zeit = offlineDate.AddHours(12.5) },
            new() { BenutzerId = userId, Typ = ErfassungsTyp.Gehen, Zeit = offlineDate.AddHours(17) }
        };
        
        foreach (var eintrag in offlineEintraege)
        {
            await _offlineService.SaveOfflineZeiterfassungAsync(eintrag);
        }
        
        // Wechsle zu Online und synchronisiere
        await _offlineService.DisableOfflineModeAsync();
        var syncResult = await _syncService.SynchronizeAsync();
        
        // Assert
        syncResult.Success.Should().BeTrue();
        syncResult.SynchronizedCount.Should().Be(4);
        syncResult.ConflictCount.Should().Be(0);
        
        // Verifiziere in Online-Datenbank
        using var scope = ServiceProvider.CreateScope();
        var context = scope.ServiceProvider.GetRequiredService<ArbeitszeitDbContext>();
        
        var onlineEintraege = await context.Zeiterfassungen
            .Where(z => z.BenutzerId == userId && z.Zeit.Date == offlineDate)
            .OrderBy(z => z.Zeit)
            .ToListAsync();
        
        onlineEintraege.Should().HaveCount(4);
        onlineEintraege.Select(e => e.Typ).Should().ContainInOrder(
            ErfassungsTyp.Kommen,
            ErfassungsTyp.PauseBeginn,
            ErfassungsTyp.PauseEnde,
            ErfassungsTyp.Gehen
        );
    }
    
    [Fact]
    public async Task ConflictResolution_ShouldHandleCorrectly()
    {
        // Arrange - Erstelle konflikthafte Situation
        var userId = TestUsers.StandardMitarbeiter.Id;
        var conflictDate = DateTime.Today.AddDays(-1);
        var conflictTime = conflictDate.AddHours(8);
        
        // Online-Eintrag
        await CreateOnlineZeiterfassung(userId, ErfassungsTyp.Kommen, conflictTime);
        
        // Offline-Eintrag zur gleichen Zeit (aber andere ID)
        await _offlineService.EnableOfflineModeAsync();
        await _offlineService.SaveOfflineZeiterfassungAsync(new ZeiterfassungDto
        {
            BenutzerId = userId,
            Typ = ErfassungsTyp.Kommen,
            Zeit = conflictTime.AddMinutes(5), // Leicht andere Zeit
            Kommentar = "Offline erfasst"
        });
        
        // Act - Synchronisiere
        await _offlineService.DisableOfflineModeAsync();
        var syncResult = await _syncService.SynchronizeAsync();
        
        // Assert
        syncResult.ConflictCount.Should().Be(1);
        
        // Hole Konflikt
        var conflicts = await _syncService.GetUnresolvedConflictsAsync();
        conflicts.Should().HaveCount(1);
        
        var conflict = conflicts.First();
        conflict.LocalValue.Should().Contain("Offline erfasst");
        
        // Löse Konflikt (behalte Offline-Version)
        await _syncService.ResolveConflictAsync(conflict.Id, ConflictResolution.KeepLocal);
        
        // Verifiziere Ergebnis
        var finalEntries = await GetZeiterfassungen(userId, conflictDate);
        finalEntries.Should().HaveCount(1);
        finalEntries.First().Kommentar.Should().Be("Offline erfasst");
    }
}
```

### 4. Performance- und Lasttests (Arbeitszeiterfassung.IntegrationTests/Performance/)
```csharp
// PerformanceIntegrationTests.cs
public class PerformanceIntegrationTests : IntegrationTestBase
{
    [Fact]
    public async Task ConcurrentUsers_ShouldHandleLoad()
    {
        // Arrange
        const int concurrentUsers = 50;
        const int operationsPerUser = 10;
        var testUsers = GenerateTestUsers(concurrentUsers);
        
        // Act - Simuliere gleichzeitige Zugriffe
        var tasks = testUsers.Select(user => Task.Run(async () =>
        {
            for (int i = 0; i < operationsPerUser; i++)
            {
                var result = await SimulateZeiterfassung(
                    user.Id,
                    i % 2 == 0 ? ErfassungsTyp.Kommen : ErfassungsTyp.Gehen,
                    DateTime.Now.AddMinutes(i * 30),
                    "192.168.1.100"
                );
                
                result.Success.Should().BeTrue();
                
                // Kleine Verzögerung zwischen Operationen
                await Task.Delay(Random.Shared.Next(10, 50));
            }
        })).ToList();
        
        var stopwatch = Stopwatch.StartNew();
        await Task.WhenAll(tasks);
        stopwatch.Stop();
        
        // Assert
        var totalOperations = concurrentUsers * operationsPerUser;
        var operationsPerSecond = totalOperations / stopwatch.Elapsed.TotalSeconds;
        
        operationsPerSecond.Should().BeGreaterThan(10); // Mindestens 10 ops/sec
        
        // Verifiziere Datenintegrität
        using var scope = ServiceProvider.CreateScope();
        var context = scope.ServiceProvider.GetRequiredService<ArbeitszeitDbContext>();
        
        var totalEntries = await context.Zeiterfassungen.CountAsync();
        totalEntries.Should().Be(totalOperations);
    }
    
    [Fact]
    public async Task LargeDataExport_ShouldCompleteInTime()
    {
        // Arrange - Generiere große Datenmenge
        await GenerateLargeDataset(10000); // 10k Einträge
        
        var exportRequest = new ExportRequest
        {
            Format = ExportFormat.Excel,
            DateFrom = DateTime.Today.AddYears(-1),
            DateTo = DateTime.Today,
            IncludeAllUsers = true
        };
        
        // Act
        var stopwatch = Stopwatch.StartNew();
        var exportResult = await Client.PostAsJsonAsync("/api/export", exportRequest);
        stopwatch.Stop();
        
        // Assert
        exportResult.IsSuccessStatusCode.Should().BeTrue();
        stopwatch.ElapsedMilliseconds.Should().BeLessThan(5000); // Max 5 Sekunden
        
        var exportData = await exportResult.Content.ReadAsByteArrayAsync();
        exportData.Length.Should().BeGreaterThan(0);
    }
}
```

### 5. UI-Automatisierungstests (Arbeitszeiterfassung.IntegrationTests/UI/)
```csharp
// UIAutomationTests.cs
[Collection("UI Tests")]
public class UIAutomationTests : IDisposable
{
    private readonly Application _app;
    private readonly Window _mainWindow;
    
    public UIAutomationTests()
    {
        // Starte Anwendung
        _app = Application.Launch(@"..\..\..\..\Arbeitszeiterfassung.UI\bin\Debug\net8.0-windows\Arbeitszeiterfassung.UI.exe");
        _mainWindow = _app.GetMainWindow();
    }
    
    [UIFact]
    public void CompleteUserJourney_ThroughUI()
    {
        // Login (automatisch durch Windows-Auth)
        var welcomeLabel = _mainWindow.Get<Label>("lblWelcome");
        welcomeLabel.Text.Should().Contain("Willkommen");
        
        // Zeiterfassung starten
        var startButton = _mainWindow.Get<Button>("btnStart");
        startButton.Should().NotBeNull();
        startButton.Click();
        
        // Warte auf Status-Update
        Thread.Sleep(500);
        
        var statusLabel = _mainWindow.Get<Label>("lblStatus");
        statusLabel.Text.Should().Contain("Arbeitszeit läuft");
        
        // Navigiere zu Übersicht
        var menuStrip = _mainWindow.Get<MenuBar>("mainMenu");
        menuStrip.MenuItem("Ansicht", "Monatsübersicht").Click();
        
        // Verifiziere Monatsübersicht
        var monatsGrid = _mainWindow.Get<ListView>("lvMonatsuebersicht");
        monatsGrid.Rows.Should().NotBeEmpty();
        
        // Öffne Tagesdetails
        monatsGrid.Rows[0].DoubleClick();
        
        var detailsWindow = _mainWindow.ModalWindow("Tagesdetails");
        detailsWindow.Should().NotBeNull();
        
        // Schließe Details
        detailsWindow.Get<Button>("btnClose").Click();
        
        // Zeiterfassung beenden
        var stopButton = _mainWindow.Get<Button>("btnStop");
        stopButton.Click();
        
        Thread.Sleep(500);
        statusLabel.Text.Should().Contain("Keine aktive Zeiterfassung");
    }
    
    [UIFact]
    public void ValidationFeedback_ShouldShowCorrectly()
    {
        // Navigiere zu Stammdaten
        var menuStrip = _mainWindow.Get<MenuBar>("mainMenu");
        menuStrip.MenuItem("Verwaltung", "Stammdaten").Click();
        
        var stammdatenWindow = _mainWindow.ModalWindow("Stammdatenverwaltung");
        
        // Versuche ungültige Eingabe
        var emailTextBox = stammdatenWindow.Get<TextBox>("txtEmail");
        emailTextBox.Text = "ungueltige-email";
        
        var saveButton = stammdatenWindow.Get<Button>("btnSave");
        saveButton.Click();
        
        // Verifiziere Validierungsfehler
        var errorProvider = stammdatenWindow.Get<Label>("lblEmailError");
        errorProvider.Text.Should().Contain("Ungültige E-Mail");
        
        // Korrigiere und speichere
        emailTextBox.Text = "test@example.com";
        saveButton.Click();
        
        // Window sollte sich schließen
        Thread.Sleep(500);
        _mainWindow.ModalWindows().Should().BeEmpty();
    }
    
    public void Dispose()
    {
        _app?.Close();
        _app?.Dispose();
    }
}
```

### 6. Sicherheits- und Berechtigungstests (Arbeitszeiterfassung.IntegrationTests/Security/)
```csharp
// SecurityIntegrationTests.cs
public class SecurityIntegrationTests : IntegrationTestBase
{
    [Theory]
    [InlineData("mitarbeiter", "/api/admin/users", false)]
    [InlineData("mitarbeiter", "/api/zeiterfassung/own", true)]
    [InlineData("admin", "/api/admin/users", true)]
    [InlineData("honorarkraft", "/api/genehmigung/create", false)]
    public async Task Authorization_ShouldEnforceRoles(string rolle, string endpoint, bool shouldSucceed)
    {
        // Arrange
        var user = GetTestUserByRole(rolle);
        AuthenticateAs(user);
        
        // Act
        var response = await Client.GetAsync(endpoint);
        
        // Assert
        if (shouldSucceed)
        {
            response.StatusCode.Should().Be(HttpStatusCode.OK);
        }
        else
        {
            response.StatusCode.Should().BeOneOf(
                HttpStatusCode.Forbidden,
                HttpStatusCode.Unauthorized
            );
        }
    }
    
    [Fact]
    public async Task StandortValidation_ShouldRejectInvalidIP()
    {
        // Arrange
        var user = TestUsers.StandardMitarbeiter;
        var invalidIp = "10.0.0.1"; // Nicht in erlaubtem Range
        
        // Überschreibe Client-IP
        Client.DefaultRequestHeaders.Add("X-Forwarded-For", invalidIp);
        
        // Act
        var result = await SimulateZeiterfassung(
            user.Id,
            ErfassungsTyp.Kommen,
            DateTime.Now,
            invalidIp
        );
        
        // Assert
        result.Success.Should().BeFalse();
        result.ErrorMessage.Should().Contain("IP-Adresse");
        result.ErrorMessage.Should().Contain("nicht autorisiert");
        
        // Verifiziere Audit-Log
        var auditLogs = await GetAuditLogs(
            filter: l => l.EntityType == "Zeiterfassung" && 
                        l.Username == user.Username
        );
        
        auditLogs.Should().Contain(l => 
            l.AdditionalInfo.Contains("StandortValidierungFehlgeschlagen")
        );
    }
}
```

### 7. Test-Reporting und CI/CD Integration
```yaml
# azure-pipelines.yml
trigger:
  branches:
    include:
    - main
    - develop

pool:
  vmImage: 'windows-latest'

variables:
  buildConfiguration: 'Release'
  
stages:
- stage: Build
  jobs:
  - job: BuildAndTest
    steps:
    - task: UseDotNet@2
      inputs:
        version: '8.0.x'
    
    - task: DotNetCoreCLI@2
      displayName: 'Restore packages'
      inputs:
        command: 'restore'
        projects: '**/*.csproj'
    
    - task: DotNetCoreCLI@2
      displayName: 'Build solution'
      inputs:
        command: 'build'
        projects: '**/*.sln'
        arguments: '--configuration $(buildConfiguration)'
    
    - task: DotNetCoreCLI@2
      displayName: 'Run Unit Tests'
      inputs:
        command: 'test'
        projects: '**/Arbeitszeiterfassung.Tests.csproj'
        arguments: '--configuration $(buildConfiguration) --collect:"XPlat Code Coverage"'
    
    - task: DotNetCoreCLI@2
      displayName: 'Run Integration Tests'
      inputs:
        command: 'test'
        projects: '**/Arbeitszeiterfassung.IntegrationTests.csproj'
        arguments: '--configuration $(buildConfiguration) --logger trx'
    
    - task: PublishTestResults@2
      inputs:
        testResultsFormat: 'VSTest'
        testResultsFiles: '**/*.trx'
        
    - task: PublishCodeCoverageResults@1
      inputs:
        codeCoverageTool: 'Cobertura'
        summaryFileLocation: '$(Agent.TempDirectory)/**/coverage.cobertura.xml'
```

### 8. Test-Daten-Management
```csharp
// TestDataManager.cs
public static class TestDataManager
{
    public static class TestUsers
    {
        public static readonly TestUser StandardMitarbeiter = new()
        {
            Id = 1,
            Username = "max.mustermann",
            Name = "Max Mustermann",
            RolleId = (int)Rolle.Mitarbeiter,
            StandortId = 1
        };
        
        public static readonly TestUser Bereichsleiter = new()
        {
            Id = 2,
            Username = "anna.admin",
            Name = "Anna Admin",
            RolleId = (int)Rolle.Bereichsleiter,
            StandortId = 1
        };
    }
    
    public static void ResetToCleanState(ArbeitszeitDbContext context)
    {
        // Lösche alle transakionalen Daten
        context.Database.ExecuteSqlRaw("DELETE FROM Zeiterfassungen");
        context.Database.ExecuteSqlRaw("DELETE FROM Genehmigungen");
        context.Database.ExecuteSqlRaw("DELETE FROM AuditLogs");
        
        // Setze Sequences zurück
        context.Database.ExecuteSqlRaw("DBCC CHECKIDENT ('Zeiterfassungen', RESEED, 0)");
        
        // Lade Stammdaten neu
        SeedMasterData(context);
    }
    
    public static async Task<List<Zeiterfassung>> GenerateRealisticMonth(
        int userId, 
        DateTime month)
    {
        var entries = new List<Zeiterfassung>();
        var workdays = GetWorkdaysInMonth(month);
        
        foreach (var day in workdays)
        {
            // Variiere Arbeitszeiten realistisch
            var startTime = day.AddHours(7.5 + Random.Shared.NextDouble());
            var lunchStart = startTime.AddHours(4 + Random.Shared.NextDouble());
            var lunchEnd = lunchStart.AddMinutes(30 + Random.Shared.Next(0, 30));
            var endTime = startTime.AddHours(8 + Random.Shared.NextDouble());
            
            entries.AddRange(new[]
            {
                new Zeiterfassung { BenutzerId = userId, Zeit = startTime, Typ = ErfassungsTyp.Kommen },
                new Zeiterfassung { BenutzerId = userId, Zeit = lunchStart, Typ = ErfassungsTyp.PauseBeginn },
                new Zeiterfassung { BenutzerId = userId, Zeit = lunchEnd, Typ = ErfassungsTyp.PauseEnde },
                new Zeiterfassung { BenutzerId = userId, Zeit = endTime, Typ = ErfassungsTyp.Gehen }
            });
        }
        
        return entries;
    }
}
```

## Erwartete Ergebnisse

1. **End-to-End Tests** für alle Hauptworkflows
2. **Automatisierte UI-Tests** für kritische Benutzerpfade
3. **Performance-Tests** mit realistischen Lastszenarien
4. **Sicherheitstests** für Berechtigungen und Validierungen
5. **Offline/Online-Synchronisationstests**
6. **CI/CD-Integration** mit automatisierter Testausführung

## Zusätzliche Hinweise
- Verwende realistische Testdaten
- Teste auch Fehlerfälle und Edge-Cases
- Implementiere Cleanup nach jedem Test
- Nutze Parallelisierung wo möglich
- Dokumentiere besondere Test-Setups

## Nächste Schritte
Nach erfolgreicher Implementierung der Integrationstests folgt Schritt 6.3: Deployment-Paket erstellen.
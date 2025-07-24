---
title: Schritt 5.1 - Offline-Modus und Synchronisation
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_5_1_Offline_Modus_und_Synchronisation.md
description: Detaillierter Prompt für die Implementierung des Offline-Modus mit automatischer Synchronisation
---

# Schritt 5.1: Offline-Modus und Synchronisation implementieren

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Grundfunktionalität der Anwendung ist bereits implementiert. Jetzt soll ein robuster Offline-Modus mit automatischer Synchronisation hinzugefügt werden, damit Mitarbeiter auch ohne Netzwerkverbindung arbeiten können.

## Aufgabe
Implementiere einen vollständigen Offline-Modus mit SQLite als lokaler Datenbank und einem intelligenten Synchronisationsmechanismus, der Konflikte erkennt und löst.

## Anforderungen

### 1. Offline-Datenbank-Setup (Arbeitszeiterfassung.DAL/Offline/)
```csharp
// OfflineDbContext.cs
public class OfflineDbContext : DbContext
{
    private readonly string _dbPath;
    
    public OfflineDbContext()
    {
        var folder = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        _dbPath = Path.Combine(folder, "Arbeitszeiterfassung", "offline.db");
        
        // Stelle sicher, dass Verzeichnis existiert
        Directory.CreateDirectory(Path.GetDirectoryName(_dbPath));
    }
    
    // Gleiche DbSets wie HauptDbContext
    public DbSet<Benutzer> Benutzer { get; set; }
    public DbSet<Zeiterfassung> Zeiterfassungen { get; set; }
    public DbSet<SyncQueue> SyncQueue { get; set; }
    
    protected override void OnConfiguring(DbContextOptionsBuilder optionsBuilder)
    {
        optionsBuilder.UseSqlite($"Data Source={_dbPath}");
        optionsBuilder.EnableSensitiveDataLogging(false);
    }
    
    protected override void OnModelCreating(ModelBuilder modelBuilder)
    {
        // Identische Konfiguration wie HauptDbContext
        base.OnModelCreating(modelBuilder);
        
        // Zusätzliche Sync-Tabellen
        modelBuilder.Entity<SyncQueue>(entity =>
        {
            entity.HasKey(e => e.Id);
            entity.Property(e => e.EntityType).IsRequired();
            entity.Property(e => e.Operation).IsRequired();
            entity.Property(e => e.CreatedAt).IsRequired();
            entity.HasIndex(e => new { e.Status, e.CreatedAt });
        });
        
        modelBuilder.Entity<SyncConflict>(entity =>
        {
            entity.HasKey(e => e.Id);
            entity.Property(e => e.ConflictData).HasColumnType("TEXT");
        });
    }
}

// SyncQueue.cs - Warteschlange für Synchronisation
public class SyncQueue
{
    public int Id { get; set; }
    public string EntityType { get; set; }
    public int EntityId { get; set; }
    public SyncOperation Operation { get; set; }
    public string SerializedData { get; set; }
    public SyncStatus Status { get; set; }
    public DateTime CreatedAt { get; set; }
    public DateTime? ProcessedAt { get; set; }
    public int RetryCount { get; set; }
    public string LastError { get; set; }
}

public enum SyncOperation
{
    Create = 1,
    Update = 2,
    Delete = 3
}

public enum SyncStatus
{
    Pending = 1,
    Processing = 2,
    Completed = 3,
    Failed = 4,
    Conflict = 5
}
```

### 2. Offline-Repository-Pattern (Arbeitszeiterfassung.DAL/Repositories/Offline/)
```csharp
// IOfflineRepository.cs
public interface IOfflineRepository<T> where T : class
{
    Task<T> GetByIdAsync(int id);
    Task<IEnumerable<T>> GetAllAsync();
    Task<T> AddAsync(T entity);
    Task UpdateAsync(T entity);
    Task DeleteAsync(int id);
    Task<bool> ExistsAsync(int id);
    Task AddToSyncQueueAsync(T entity, SyncOperation operation);
}

// OfflineRepository.cs
public class OfflineRepository<T> : IOfflineRepository<T> where T : class, IEntity
{
    private readonly OfflineDbContext _context;
    private readonly DbSet<T> _dbSet;
    
    public OfflineRepository(OfflineDbContext context)
    {
        _context = context;
        _dbSet = context.Set<T>();
    }
    
    public async Task<T> AddAsync(T entity)
    {
        await _dbSet.AddAsync(entity);
        await _context.SaveChangesAsync();
        
        // Zur Sync-Queue hinzufügen
        await AddToSyncQueueAsync(entity, SyncOperation.Create);
        
        return entity;
    }
    
    public async Task AddToSyncQueueAsync(T entity, SyncOperation operation)
    {
        var syncItem = new SyncQueue
        {
            EntityType = typeof(T).Name,
            EntityId = entity.Id,
            Operation = operation,
            SerializedData = JsonSerializer.Serialize(entity),
            Status = SyncStatus.Pending,
            CreatedAt = DateTime.UtcNow,
            RetryCount = 0
        };
        
        await _context.SyncQueue.AddAsync(syncItem);
        await _context.SaveChangesAsync();
    }
}
```

### 3. Synchronisations-Service (Arbeitszeiterfassung.BLL/Services/)
```csharp
// ISyncService.cs
public interface ISyncService
{
    Task<SyncResult> SynchronizeAsync();
    Task<bool> IsOnlineAsync();
    Task<int> GetPendingSyncCountAsync();
    Task<List<SyncConflict>> GetConflictsAsync();
    Task ResolveConflictAsync(int conflictId, ConflictResolution resolution);
    event EventHandler<SyncProgressEventArgs> SyncProgress;
}

// SyncService.cs
public class SyncService : ISyncService
{
    private readonly IOnlineRepository _onlineRepo;
    private readonly IOfflineRepository _offlineRepo;
    private readonly INetworkService _networkService;
    private readonly ILogger<SyncService> _logger;
    private readonly SemaphoreSlim _syncLock = new(1, 1);
    
    public event EventHandler<SyncProgressEventArgs> SyncProgress;
    
    public async Task<SyncResult> SynchronizeAsync()
    {
        if (!await IsOnlineAsync())
            return new SyncResult { Success = false, Message = "Keine Netzwerkverbindung" };
        
        await _syncLock.WaitAsync();
        try
        {
            var result = new SyncResult();
            
            // 1. Lade ausstehende Sync-Items
            var pendingItems = await GetPendingSyncItemsAsync();
            var totalItems = pendingItems.Count;
            var processed = 0;
            
            // 2. Verarbeite jedes Item
            foreach (var item in pendingItems)
            {
                try
                {
                    OnSyncProgress(++processed, totalItems, $"Synchronisiere {item.EntityType} #{item.EntityId}");
                    
                    switch (item.Operation)
                    {
                        case SyncOperation.Create:
                            await SyncCreateAsync(item);
                            break;
                        case SyncOperation.Update:
                            await SyncUpdateAsync(item);
                            break;
                        case SyncOperation.Delete:
                            await SyncDeleteAsync(item);
                            break;
                    }
                    
                    // Markiere als erfolgreich
                    item.Status = SyncStatus.Completed;
                    item.ProcessedAt = DateTime.UtcNow;
                    result.SuccessCount++;
                }
                catch (SyncConflictException ex)
                {
                    await HandleConflictAsync(item, ex);
                    result.ConflictCount++;
                }
                catch (Exception ex)
                {
                    await HandleSyncErrorAsync(item, ex);
                    result.ErrorCount++;
                }
            }
            
            // 3. Lade neue Daten vom Server
            await DownloadServerChangesAsync();
            
            result.Success = result.ErrorCount == 0;
            result.Message = $"Sync abgeschlossen: {result.SuccessCount} erfolgreich, {result.ConflictCount} Konflikte, {result.ErrorCount} Fehler";
            
            return result;
        }
        finally
        {
            _syncLock.Release();
        }
    }
    
    private async Task SyncUpdateAsync(SyncQueue item)
    {
        var entityType = Type.GetType($"Arbeitszeiterfassung.DAL.Entities.{item.EntityType}");
        var localEntity = JsonSerializer.Deserialize(item.SerializedData, entityType);
        
        // Prüfe auf Konflikte
        var serverEntity = await _onlineRepo.GetByIdAsync(entityType, item.EntityId);
        if (serverEntity != null)
        {
            var serverVersion = GetEntityVersion(serverEntity);
            var localVersion = GetEntityVersion(localEntity);
            
            if (serverVersion > localVersion)
            {
                // Server hat neuere Version - Konflikt!
                throw new SyncConflictException(
                    "Server-Version ist neuer als lokale Version",
                    localEntity,
                    serverEntity
                );
            }
        }
        
        // Update durchführen
        await _onlineRepo.UpdateAsync(localEntity);
    }
    
    private async Task HandleConflictAsync(SyncQueue item, SyncConflictException ex)
    {
        var conflict = new SyncConflict
        {
            SyncQueueId = item.Id,
            EntityType = item.EntityType,
            EntityId = item.EntityId,
            LocalData = JsonSerializer.Serialize(ex.LocalEntity),
            ServerData = JsonSerializer.Serialize(ex.ServerEntity),
            ConflictReason = ex.Message,
            CreatedAt = DateTime.UtcNow,
            Status = ConflictStatus.Unresolved
        };
        
        await _offlineRepo.AddConflictAsync(conflict);
        
        item.Status = SyncStatus.Conflict;
        _logger.LogWarning($"Sync-Konflikt für {item.EntityType} #{item.EntityId}: {ex.Message}");
    }
}
```

### 4. Netzwerk-Überwachung (Arbeitszeiterfassung.BLL/Services/)
```csharp
// NetworkMonitorService.cs
public class NetworkMonitorService : INetworkService
{
    private readonly Timer _checkTimer;
    private bool _isOnline;
    private DateTime _lastOnlineCheck;
    
    public event EventHandler<NetworkStatusChangedEventArgs> NetworkStatusChanged;
    
    public NetworkMonitorService()
    {
        _checkTimer = new Timer(CheckNetworkStatus, null, TimeSpan.Zero, TimeSpan.FromSeconds(30));
        NetworkChange.NetworkAvailabilityChanged += OnNetworkAvailabilityChanged;
    }
    
    private async void CheckNetworkStatus(object state)
    {
        var wasOnline = _isOnline;
        _isOnline = await TestConnectivityAsync();
        
        if (wasOnline != _isOnline)
        {
            NetworkStatusChanged?.Invoke(this, new NetworkStatusChangedEventArgs
            {
                IsOnline = _isOnline,
                Timestamp = DateTime.Now
            });
        }
    }
    
    private async Task<bool> TestConnectivityAsync()
    {
        try
        {
            // Teste Verbindung zum API-Endpoint
            using var client = new HttpClient { Timeout = TimeSpan.FromSeconds(5) };
            var response = await client.GetAsync($"{_apiBaseUrl}/health");
            return response.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }
}
```

### 5. UI-Integration (Arbeitszeiterfassung.UI/Controls/)
```csharp
// SyncStatusControl.cs - Statusanzeige in der Statusleiste
public partial class SyncStatusControl : UserControl
{
    private readonly ISyncService _syncService;
    private readonly INetworkService _networkService;
    
    public SyncStatusControl()
    {
        InitializeComponent();
        
        _syncService = ServiceLocator.Get<ISyncService>();
        _networkService = ServiceLocator.Get<INetworkService>();
        
        _networkService.NetworkStatusChanged += OnNetworkStatusChanged;
        _syncService.SyncProgress += OnSyncProgress;
        
        InitializeStatusDisplay();
    }
    
    private void OnNetworkStatusChanged(object sender, NetworkStatusChangedEventArgs e)
    {
        this.InvokeIfRequired(() =>
        {
            if (e.IsOnline)
            {
                lblStatus.Text = "Online";
                lblStatus.ForeColor = Color.Green;
                picStatus.Image = Resources.OnlineIcon;
                
                // Starte automatische Synchronisation
                _ = Task.Run(async () => await TryAutoSyncAsync());
            }
            else
            {
                lblStatus.Text = "Offline";
                lblStatus.ForeColor = Color.Orange;
                picStatus.Image = Resources.OfflineIcon;
            }
        });
    }
    
    private async Task TryAutoSyncAsync()
    {
        var pendingCount = await _syncService.GetPendingSyncCountAsync();
        if (pendingCount > 0)
        {
            var result = MessageBox.Show(
                $"Es gibt {pendingCount} ausstehende Änderungen. Jetzt synchronisieren?",
                "Synchronisation",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question
            );
            
            if (result == DialogResult.Yes)
            {
                await PerformSyncAsync();
            }
        }
    }
}
```

### 6. Konfliktlösung UI (Arbeitszeiterfassung.UI/Forms/)
```csharp
// FrmConflictResolution.cs
public partial class FrmConflictResolution : Form
{
    private readonly List<SyncConflict> _conflicts;
    private readonly ISyncService _syncService;
    
    public FrmConflictResolution(List<SyncConflict> conflicts)
    {
        InitializeComponent();
        _conflicts = conflicts;
        _syncService = ServiceLocator.Get<ISyncService>();
        
        LoadConflicts();
    }
    
    private void LoadConflicts()
    {
        foreach (var conflict in _conflicts)
        {
            var item = new ListViewItem(conflict.EntityType);
            item.SubItems.Add(conflict.EntityId.ToString());
            item.SubItems.Add(conflict.ConflictReason);
            item.SubItems.Add(conflict.CreatedAt.ToString("dd.MM.yyyy HH:mm"));
            item.Tag = conflict;
            
            lvConflicts.Items.Add(item);
        }
    }
    
    private void btnResolve_Click(object sender, EventArgs e)
    {
        if (lvConflicts.SelectedItems.Count == 0) return;
        
        var conflict = (SyncConflict)lvConflicts.SelectedItems[0].Tag;
        
        // Zeige Vergleichsdialog
        using var dlg = new FrmConflictComparison(conflict);
        if (dlg.ShowDialog() == DialogResult.OK)
        {
            _ = Task.Run(async () =>
            {
                await _syncService.ResolveConflictAsync(conflict.Id, dlg.SelectedResolution);
                this.InvokeIfRequired(() => LoadConflicts());
            });
        }
    }
}
```

### 7. Datenbank-Migration für Offline-Support
```csharp
// OfflineDatabaseInitializer.cs
public class OfflineDatabaseInitializer
{
    public static async Task InitializeAsync()
    {
        using var context = new OfflineDbContext();
        
        // Erstelle Datenbank falls nicht vorhanden
        await context.Database.EnsureCreatedAsync();
        
        // Führe Migrationen aus
        if ((await context.Database.GetPendingMigrationsAsync()).Any())
        {
            await context.Database.MigrateAsync();
        }
        
        // Initialisiere Stammdaten
        await SeedOfflineDataAsync(context);
    }
    
    private static async Task SeedOfflineDataAsync(OfflineDbContext context)
    {
        // Kopiere wichtige Stammdaten
        if (!await context.Standorte.AnyAsync())
        {
            // Lade Standorte aus der Hauptdatenbank
            var standorte = await mainContext.Standorte.ToListAsync();

            await context.Standorte.AddRangeAsync(standorte);
            await context.SaveChangesAsync();
        }
    }
}
```

### 8. Fehlerbehandlung und Wiederherstellung
```csharp
// SyncRecoveryService.cs
public class SyncRecoveryService
{
    private readonly ILogger<SyncRecoveryService> _logger;
    
    public async Task<bool> TryRecoverFailedSyncsAsync()
    {
        try
        {
            using var context = new OfflineDbContext();
            
            // Finde fehlgeschlagene Sync-Einträge
            var failedSyncs = await context.SyncQueue
                .Where(s => s.Status == SyncStatus.Failed && s.RetryCount < 3)
                .OrderBy(s => s.CreatedAt)
                .ToListAsync();
            
            foreach (var sync in failedSyncs)
            {
                sync.RetryCount++;
                sync.Status = SyncStatus.Pending;
                _logger.LogInformation($"Wiederhole Sync für {sync.EntityType} #{sync.EntityId} (Versuch {sync.RetryCount})");
            }
            
            await context.SaveChangesAsync();
            return true;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fehler bei Sync-Wiederherstellung");
            return false;
        }
    }
}
```

## Erwartete Ergebnisse

1. **Vollständiger Offline-Modus** mit SQLite-Datenbank
2. **Automatische Synchronisation** bei Netzwerkverfügbarkeit
3. **Intelligente Konflikterkennung** und -lösung
4. **Benutzerfreundliche UI** für Sync-Status und Konflikte
5. **Robuste Fehlerbehandlung** mit Retry-Mechanismus
6. **Performante Synchronisation** auch bei vielen Datensätzen

## Zusätzliche Hinweise
- Verwende optimistische Nebenläufigkeitskontrolle
- Implementiere Batch-Synchronisation für bessere Performance
- Berücksichtige Zeitzonendifferenzen
- Stelle sicher, dass sensible Daten auch offline verschlüsselt sind
- Implementiere eine Fortschrittsanzeige für lange Sync-Vorgänge

## Nächste Schritte
Nach erfolgreicher Implementierung des Offline-Modus folgt Schritt 5.2: Benachrichtigungen und Validierungen.
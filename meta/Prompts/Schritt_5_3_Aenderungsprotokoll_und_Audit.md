---
title: Schritt 5.3 - Änderungsprotokoll und Audit
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_5_3_Aenderungsprotokoll_und_Audit.md
description: Detaillierter Prompt für die Implementierung eines vollständigen Audit-Trail-Systems
---

# Schritt 5.3: Änderungsprotokoll und Audit-Trail implementieren

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Anwendung verfügt bereits über alle Hauptfunktionen. Jetzt soll ein vollständiges Audit-Trail-System implementiert werden, das alle Änderungen protokolliert und eine lückenlose Nachvollziehbarkeit gewährleistet.

## Aufgabe
Entwickle ein umfassendes Audit-System, das automatisch alle Änderungen an relevanten Entitäten verfolgt, detaillierte Änderungsprotokolle erstellt und eine benutzerfreundliche Oberfläche zur Anzeige der Audit-Historie bietet.

## Anforderungen

### 1. Audit-Infrastruktur (Arbeitszeiterfassung.DAL/Audit/)
```csharp
// IAuditable.cs
public interface IAuditable
{
    DateTime CreatedAt { get; set; }
    string CreatedBy { get; set; }
    DateTime? ModifiedAt { get; set; }
    string ModifiedBy { get; set; }
    int Version { get; set; }
}

// AuditLog.cs
public class AuditLog
{
    public long Id { get; set; }
    public string EntityType { get; set; }
    public int EntityId { get; set; }
    public string Action { get; set; } // Create, Update, Delete
    public string Username { get; set; }
    public DateTime Timestamp { get; set; }
    public string IpAddress { get; set; }
    public string UserAgent { get; set; }
    public string OldValues { get; set; } // JSON
    public string NewValues { get; set; } // JSON
    public string ChangedProperties { get; set; } // Komma-getrennt
    public string AdditionalInfo { get; set; } // JSON für zusätzliche Metadaten
}

// AuditInterceptor.cs - Entity Framework Interceptor
public class AuditInterceptor : SaveChangesInterceptor
{
    private readonly ICurrentUserService _currentUserService;
    private readonly IHttpContextAccessor _httpContextAccessor;
    
    public override async ValueTask<InterceptionResult<int>> SavingChangesAsync(
        DbContextEventData eventData,
        InterceptionResult<int> result,
        CancellationToken cancellationToken = default)
    {
        var context = eventData.Context;
        var auditEntries = new List<AuditEntry>();
        
        foreach (var entry in context.ChangeTracker.Entries())
        {
            if (entry.Entity is IAuditable || ShouldAudit(entry.Entity))
            {
                var auditEntry = CreateAuditEntry(entry);
                if (auditEntry != null)
                {
                    auditEntries.Add(auditEntry);
                }
            }
        }
        
        // Speichere Audit-Logs
        if (auditEntries.Any())
        {
            var auditLogs = auditEntries.Select(ae => ae.ToAuditLog()).ToList();
            await context.Set<AuditLog>().AddRangeAsync(auditLogs);
        }
        
        return result;
    }
    
    private AuditEntry CreateAuditEntry(EntityEntry entry)
    {
        var auditEntry = new AuditEntry
        {
            EntityType = entry.Entity.GetType().Name,
            Action = entry.State.ToString(),
            Username = _currentUserService.Username,
            Timestamp = DateTime.UtcNow,
            IpAddress = _httpContextAccessor?.HttpContext?.Connection?.RemoteIpAddress?.ToString(),
            UserAgent = _httpContextAccessor?.HttpContext?.Request?.Headers["User-Agent"].ToString()
        };
        
        switch (entry.State)
        {
            case EntityState.Added:
                auditEntry.NewValues = GetEntityValues(entry.CurrentValues);
                auditEntry.EntityId = GetEntityId(entry.Entity);
                break;
                
            case EntityState.Modified:
                auditEntry.OldValues = GetEntityValues(entry.OriginalValues);
                auditEntry.NewValues = GetEntityValues(entry.CurrentValues);
                auditEntry.ChangedProperties = GetChangedProperties(entry);
                auditEntry.EntityId = GetEntityId(entry.Entity);
                break;
                
            case EntityState.Deleted:
                auditEntry.OldValues = GetEntityValues(entry.OriginalValues);
                auditEntry.EntityId = GetEntityId(entry.Entity);
                break;
                
            default:
                return null;
        }
        
        return auditEntry;
    }
    
    private string GetChangedProperties(EntityEntry entry)
    {
        var changedProps = entry.Properties
            .Where(p => p.IsModified && !IsIgnoredProperty(p.Metadata.Name))
            .Select(p => p.Metadata.Name)
            .ToList();
            
        return string.Join(", ", changedProps);
    }
}
```

### 2. Audit-Service (Arbeitszeiterfassung.BLL/Services/)
```csharp
// IAuditService.cs
public interface IAuditService
{
    Task<PagedResult<AuditLogDto>> GetAuditLogsAsync(AuditFilterDto filter);
    Task<List<AuditLogDto>> GetEntityHistoryAsync(string entityType, int entityId);
    Task<AuditStatisticsDto> GetAuditStatisticsAsync(DateTime from, DateTime to);
    Task<byte[]> ExportAuditLogsAsync(AuditExportRequest request);
    Task<DiffResult> CompareVersionsAsync(string entityType, int entityId, long auditId1, long auditId2);
}

// AuditService.cs
public class AuditService : IAuditService
{
    private readonly IAuditRepository _auditRepository;
    private readonly IMapper _mapper;
    
    public async Task<PagedResult<AuditLogDto>> GetAuditLogsAsync(AuditFilterDto filter)
    {
        var query = _auditRepository.Query();
        
        // Anwenden von Filtern
        if (!string.IsNullOrEmpty(filter.EntityType))
            query = query.Where(a => a.EntityType == filter.EntityType);
            
        if (!string.IsNullOrEmpty(filter.Username))
            query = query.Where(a => a.Username.Contains(filter.Username));
            
        if (filter.DateFrom.HasValue)
            query = query.Where(a => a.Timestamp >= filter.DateFrom.Value);
            
        if (filter.DateTo.HasValue)
            query = query.Where(a => a.Timestamp <= filter.DateTo.Value);
            
        if (!string.IsNullOrEmpty(filter.Action))
            query = query.Where(a => a.Action == filter.Action);
        
        // Sortierung
        query = filter.SortBy switch
        {
            "timestamp" => filter.SortDescending ? 
                query.OrderByDescending(a => a.Timestamp) : 
                query.OrderBy(a => a.Timestamp),
            "username" => filter.SortDescending ? 
                query.OrderByDescending(a => a.Username) : 
                query.OrderBy(a => a.Username),
            _ => query.OrderByDescending(a => a.Timestamp)
        };
        
        // Paginierung
        var totalCount = await query.CountAsync();
        var items = await query
            .Skip((filter.Page - 1) * filter.PageSize)
            .Take(filter.PageSize)
            .ToListAsync();
        
        return new PagedResult<AuditLogDto>
        {
            Items = _mapper.Map<List<AuditLogDto>>(items),
            TotalCount = totalCount,
            Page = filter.Page,
            PageSize = filter.PageSize
        };
    }
    
    public async Task<DiffResult> CompareVersionsAsync(string entityType, int entityId, long auditId1, long auditId2)
    {
        var audit1 = await _auditRepository.GetByIdAsync(auditId1);
        var audit2 = await _auditRepository.GetByIdAsync(auditId2);
        
        if (audit1.EntityType != entityType || audit1.EntityId != entityId ||
            audit2.EntityType != entityType || audit2.EntityId != entityId)
        {
            throw new InvalidOperationException("Audit-Einträge gehören nicht zur gleichen Entität");
        }
        
        var values1 = JsonSerializer.Deserialize<Dictionary<string, object>>(audit1.NewValues ?? audit1.OldValues);
        var values2 = JsonSerializer.Deserialize<Dictionary<string, object>>(audit2.NewValues ?? audit2.OldValues);
        
        var differences = new List<PropertyDifference>();
        
        // Vergleiche alle Properties
        var allKeys = values1.Keys.Union(values2.Keys).Distinct();
        
        foreach (var key in allKeys)
        {
            var value1 = values1.TryGetValue(key, out var v1) ? v1?.ToString() : null;
            var value2 = values2.TryGetValue(key, out var v2) ? v2?.ToString() : null;
            
            if (value1 != value2)
            {
                differences.Add(new PropertyDifference
                {
                    PropertyName = key,
                    OldValue = value1,
                    NewValue = value2,
                    ChangeType = GetChangeType(value1, value2)
                });
            }
        }
        
        return new DiffResult
        {
            EntityType = entityType,
            EntityId = entityId,
            Version1 = new VersionInfo
            {
                AuditId = audit1.Id,
                Timestamp = audit1.Timestamp,
                Username = audit1.Username
            },
            Version2 = new VersionInfo
            {
                AuditId = audit2.Id,
                Timestamp = audit2.Timestamp,
                Username = audit2.Username
            },
            Differences = differences
        };
    }
}
```

### 3. Audit-Trail UI (Arbeitszeiterfassung.UI/Forms/)
```csharp
// FrmAuditTrail.cs
public partial class FrmAuditTrail : Form
{
    private readonly IAuditService _auditService;
    private AuditFilterDto _currentFilter;
    private int _currentPage = 1;
    
    public FrmAuditTrail()
    {
        InitializeComponent();
        _auditService = ServiceLocator.Get<IAuditService>();
        _currentFilter = new AuditFilterDto { Page = 1, PageSize = 50 };
        
        InitializeFilters();
        LoadAuditLogs();
    }
    
    private void InitializeFilters()
    {
        // Entity Type Dropdown
        cmbEntityType.Items.Add("Alle");
        cmbEntityType.Items.AddRange(new[] 
        { 
            "Zeiterfassung", 
            "Benutzer", 
            "Genehmigung", 
            "Standort" 
        });
        cmbEntityType.SelectedIndex = 0;
        
        // Action Dropdown
        cmbAction.Items.AddRange(new[] 
        { 
            "Alle", 
            "Added", 
            "Modified", 
            "Deleted" 
        });
        cmbAction.SelectedIndex = 0;
        
        // Datum-Filter
        dtpVon.Value = DateTime.Today.AddDays(-7);
        dtpBis.Value = DateTime.Today.AddDays(1);
    }
    
    private async void LoadAuditLogs()
    {
        try
        {
            SetLoadingState(true);
            
            // Filter aktualisieren
            _currentFilter.EntityType = cmbEntityType.SelectedIndex > 0 ? 
                cmbEntityType.SelectedItem.ToString() : null;
            _currentFilter.Action = cmbAction.SelectedIndex > 0 ? 
                cmbAction.SelectedItem.ToString() : null;
            _currentFilter.DateFrom = dtpVon.Value.Date;
            _currentFilter.DateTo = dtpBis.Value.Date.AddDays(1).AddSeconds(-1);
            _currentFilter.Username = txtBenutzerFilter.Text;
            
            var result = await _auditService.GetAuditLogsAsync(_currentFilter);
            
            DisplayAuditLogs(result);
            UpdatePagination(result);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Fehler beim Laden der Audit-Logs: {ex.Message}",
                "Fehler", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
        finally
        {
            SetLoadingState(false);
        }
    }
    
    private void DisplayAuditLogs(PagedResult<AuditLogDto> result)
    {
        dgvAuditLogs.Rows.Clear();
        
        foreach (var log in result.Items)
        {
            var row = dgvAuditLogs.Rows.Add(
                log.Timestamp.ToString("dd.MM.yyyy HH:mm:ss"),
                log.Username,
                log.EntityType,
                log.EntityId,
                GetActionDisplay(log.Action),
                log.ChangedProperties,
                log.IpAddress
            );
            
            dgvAuditLogs.Rows[row].Tag = log;
            
            // Farbcodierung nach Aktion
            dgvAuditLogs.Rows[row].DefaultCellStyle.BackColor = log.Action switch
            {
                "Added" => Color.LightGreen,
                "Modified" => Color.LightBlue,
                "Deleted" => Color.MistyRose,
                _ => Color.White
            };
        }
    }
    
    private void dgvAuditLogs_CellDoubleClick(object sender, DataGridViewCellEventArgs e)
    {
        if (e.RowIndex < 0) return;
        
        var auditLog = (AuditLogDto)dgvAuditLogs.Rows[e.RowIndex].Tag;
        
        using var dlg = new FrmAuditDetails(auditLog);
        dlg.ShowDialog();
    }
}

// FrmAuditDetails.cs - Detailansicht
public partial class FrmAuditDetails : Form
{
    private readonly AuditLogDto _auditLog;
    
    public FrmAuditDetails(AuditLogDto auditLog)
    {
        InitializeComponent();
        _auditLog = auditLog;
        
        DisplayAuditDetails();
    }
    
    private void DisplayAuditDetails()
    {
        // Basis-Informationen
        lblTimestamp.Text = _auditLog.Timestamp.ToString("dd.MM.yyyy HH:mm:ss");
        lblUsername.Text = _auditLog.Username;
        lblEntityType.Text = _auditLog.EntityType;
        lblEntityId.Text = _auditLog.EntityId.ToString();
        lblAction.Text = _auditLog.Action;
        lblIpAddress.Text = _auditLog.IpAddress;
        
        // Änderungen visualisieren
        if (!string.IsNullOrEmpty(_auditLog.OldValues) && !string.IsNullOrEmpty(_auditLog.NewValues))
        {
            var oldValues = JsonSerializer.Deserialize<Dictionary<string, object>>(_auditLog.OldValues);
            var newValues = JsonSerializer.Deserialize<Dictionary<string, object>>(_auditLog.NewValues);
            
            DisplayValueComparison(oldValues, newValues);
        }
        else if (!string.IsNullOrEmpty(_auditLog.NewValues))
        {
            var values = JsonSerializer.Deserialize<Dictionary<string, object>>(_auditLog.NewValues);
            DisplaySingleValues(values, "Neue Werte");
        }
        else if (!string.IsNullOrEmpty(_auditLog.OldValues))
        {
            var values = JsonSerializer.Deserialize<Dictionary<string, object>>(_auditLog.OldValues);
            DisplaySingleValues(values, "Gelöschte Werte");
        }
    }
    
    private void DisplayValueComparison(Dictionary<string, object> oldValues, Dictionary<string, object> newValues)
    {
        dgvChanges.Rows.Clear();
        
        var allKeys = oldValues.Keys.Union(newValues.Keys).OrderBy(k => k);
        
        foreach (var key in allKeys)
        {
            var oldValue = oldValues.TryGetValue(key, out var ov) ? ov?.ToString() : "-";
            var newValue = newValues.TryGetValue(key, out var nv) ? nv?.ToString() : "-";
            
            if (oldValue != newValue)
            {
                var row = dgvChanges.Rows.Add(key, oldValue, "→", newValue);
                dgvChanges.Rows[row].DefaultCellStyle.BackColor = Color.LightYellow;
            }
        }
    }
}
```

### 4. Entity History View (Arbeitszeiterfassung.UI/Controls/)
```csharp
// EntityHistoryControl.cs
public partial class EntityHistoryControl : UserControl
{
    private readonly IAuditService _auditService;
    private string _entityType;
    private int _entityId;
    
    public async Task LoadEntityHistoryAsync(string entityType, int entityId)
    {
        _entityType = entityType;
        _entityId = entityId;
        
        try
        {
            var history = await _auditService.GetEntityHistoryAsync(entityType, entityId);
            DisplayTimeline(history);
        }
        catch (Exception ex)
        {
            MessageBox.Show($"Fehler beim Laden der Historie: {ex.Message}",
                "Fehler", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }
    
    private void DisplayTimeline(List<AuditLogDto> history)
    {
        flowTimeline.Controls.Clear();
        
        foreach (var entry in history.OrderByDescending(h => h.Timestamp))
        {
            var timelineItem = new TimelineItemControl
            {
                Timestamp = entry.Timestamp,
                Username = entry.Username,
                Action = entry.Action,
                Description = GenerateDescription(entry),
                Icon = GetActionIcon(entry.Action)
            };
            
            timelineItem.DetailsClicked += (s, e) =>
            {
                ShowAuditDetails(entry);
            };
            
            flowTimeline.Controls.Add(timelineItem);
        }
    }
    
    private string GenerateDescription(AuditLogDto entry)
    {
        var changes = string.IsNullOrEmpty(entry.ChangedProperties) ? 
            "Alle Felder" : entry.ChangedProperties;
            
        return entry.Action switch
        {
            "Added" => $"{entry.EntityType} erstellt",
            "Modified" => $"Geändert: {changes}",
            "Deleted" => $"{entry.EntityType} gelöscht",
            _ => entry.Action
        };
    }
}
```

### 5. Audit-Reports (Arbeitszeiterfassung.BLL/Services/)
```csharp
// AuditReportService.cs
public class AuditReportService : IAuditReportService
{
    private readonly IAuditRepository _auditRepository;
    
    public async Task<AuditStatisticsDto> GenerateStatisticsAsync(DateTime from, DateTime to)
    {
        var logs = await _auditRepository.Query()
            .Where(a => a.Timestamp >= from && a.Timestamp <= to)
            .ToListAsync();
        
        return new AuditStatisticsDto
        {
            TotalChanges = logs.Count,
            ChangesByType = logs.GroupBy(l => l.EntityType)
                .ToDictionary(g => g.Key, g => g.Count()),
            ChangesByAction = logs.GroupBy(l => l.Action)
                .ToDictionary(g => g.Key, g => g.Count()),
            ChangesByUser = logs.GroupBy(l => l.Username)
                .OrderByDescending(g => g.Count())
                .Take(10)
                .ToDictionary(g => g.Key, g => g.Count()),
            ChangesByDay = logs.GroupBy(l => l.Timestamp.Date)
                .ToDictionary(g => g.Key, g => g.Count()),
            MostChangedEntities = logs.GroupBy(l => new { l.EntityType, l.EntityId })
                .OrderByDescending(g => g.Count())
                .Take(20)
                .Select(g => new MostChangedEntity
                {
                    EntityType = g.Key.EntityType,
                    EntityId = g.Key.EntityId,
                    ChangeCount = g.Count()
                })
                .ToList()
        };
    }
    
    public async Task<byte[]> ExportAuditReportAsync(AuditExportRequest request)
    {
        var logs = await GetFilteredLogsAsync(request);
        
        return request.Format switch
        {
            ExportFormat.Excel => GenerateExcelReport(logs, request),
            ExportFormat.PDF => GeneratePdfReport(logs, request),
            ExportFormat.CSV => GenerateCsvReport(logs, request),
            _ => throw new NotSupportedException($"Format {request.Format} nicht unterstützt")
        };
    }
    
    private byte[] GenerateExcelReport(List<AuditLog> logs, AuditExportRequest request)
    {
        using var package = new ExcelPackage();
        var worksheet = package.Workbook.Worksheets.Add("Audit-Trail");
        
        // Header
        worksheet.Cells[1, 1].Value = "Zeitstempel";
        worksheet.Cells[1, 2].Value = "Benutzer";
        worksheet.Cells[1, 3].Value = "Entität";
        worksheet.Cells[1, 4].Value = "ID";
        worksheet.Cells[1, 5].Value = "Aktion";
        worksheet.Cells[1, 6].Value = "Geänderte Felder";
        worksheet.Cells[1, 7].Value = "IP-Adresse";
        
        // Daten
        var row = 2;
        foreach (var log in logs)
        {
            worksheet.Cells[row, 1].Value = log.Timestamp;
            worksheet.Cells[row, 2].Value = log.Username;
            worksheet.Cells[row, 3].Value = log.EntityType;
            worksheet.Cells[row, 4].Value = log.EntityId;
            worksheet.Cells[row, 5].Value = log.Action;
            worksheet.Cells[row, 6].Value = log.ChangedProperties;
            worksheet.Cells[row, 7].Value = log.IpAddress;
            row++;
        }
        
        // Formatierung
        worksheet.Cells[1, 1, 1, 7].Style.Font.Bold = true;
        worksheet.Cells[1, 1, row - 1, 7].AutoFitColumns();
        
        return package.GetAsByteArray();
    }
}
```

### 6. Compliance und Archivierung
```csharp
// AuditArchiveService.cs
public class AuditArchiveService : IAuditArchiveService
{
    private readonly IAuditRepository _auditRepository;
    private readonly IConfiguration _configuration;
    
    public async Task ArchiveOldAuditLogsAsync()
    {
        var retentionDays = _configuration.GetValue<int>("Audit:RetentionDays", 365);
        var archiveDate = DateTime.UtcNow.AddDays(-retentionDays);
        
        var logsToArchive = await _auditRepository.Query()
            .Where(a => a.Timestamp < archiveDate)
            .ToListAsync();
        
        if (logsToArchive.Any())
        {
            // Erstelle Archiv
            var archiveFileName = $"audit_archive_{DateTime.Now:yyyyMMdd}.json";
            var archivePath = Path.Combine(_configuration["Audit:ArchivePath"], archiveFileName);
            
            var archiveData = new AuditArchive
            {
                ArchiveDate = DateTime.UtcNow,
                FromDate = logsToArchive.Min(l => l.Timestamp),
                ToDate = logsToArchive.Max(l => l.Timestamp),
                RecordCount = logsToArchive.Count,
                Logs = logsToArchive
            };
            
            // Komprimiere und verschlüssele
            var json = JsonSerializer.Serialize(archiveData);
            var compressed = Compress(json);
            var encrypted = Encrypt(compressed);
            
            await File.WriteAllBytesAsync(archivePath, encrypted);
            
            // Lösche archivierte Logs
            _auditRepository.RemoveRange(logsToArchive);
            await _auditRepository.SaveChangesAsync();
            
            _logger.LogInformation($"Archiviert {logsToArchive.Count} Audit-Logs in {archiveFileName}");
        }
    }
}
```

## Erwartete Ergebnisse

1. **Vollständiges Audit-Trail-System** mit automatischer Protokollierung
2. **Detaillierte Änderungsverfolgung** für alle relevanten Entitäten
3. **Benutzerfreundliche UI** zur Anzeige und Suche von Audit-Logs
4. **Versions-Vergleich** zwischen verschiedenen Zuständen
5. **Export-Funktionen** für Compliance-Reports
6. **Archivierung** alter Audit-Einträge

## Zusätzliche Hinweise
- Implementiere Index auf häufig genutzte Spalten (Timestamp, EntityType)
- Berücksichtige DSGVO bei der Speicherdauer
- Verschlüssele sensible Daten in Audit-Logs
- Implementiere Batch-Insert für bessere Performance
- Teste mit großen Datenmengen

## Nächste Schritte
Nach erfolgreicher Implementierung des Audit-Systems folgt Schritt 6.1: Unit-Tests erstellen.
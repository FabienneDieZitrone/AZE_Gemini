---
title: Prompt für Schritt 4.4 - Arbeitszeitanzeige mit Filtern
description: Detaillierter Prompt zur Erstellung der tabellarischen Arbeitszeitanzeige mit erweiterten Filteroptionen
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 4.4: Arbeitszeitanzeige mit Filtern

## Aufgabe
Erstelle eine umfassende Arbeitszeitanzeige mit erweiterten Filter- und Exportfunktionen, Gruppierung und Statistiken.

## UI-Layout

### Gesamtstruktur:
```
┌─────────────────────────────────────────────────────────────┐
│  Arbeitszeiten                                              │
├─────────────────────────────────────────────────────────────┤
│ ┌─ Filter ──────────────────────┐ ┌─ Anzeige ─────────────┐│
│ │ Zeitraum: [Diese Woche    ▼] │ │ Wochenarbeitszeit: 40h ││
│ │ Von: [01.01.2025] [Kalender] │ │ Wochenarbeitstage: 5   ││
│ │ Bis: [31.01.2025] [Kalender] │ │ Rolle: Mitarbeiter     ││
│ │ Standort: [Alle         ▼]   │ │ Überstunden: +12.5h    ││
│ │ Benutzer: [Eigener      ▼]   │ │         [Zurück]       ││
│ │ Status: [Alle           ▼]   │ └────────────────────────┘│
│ │        [Filter anwenden]      │                           │
│ └───────────────────────────────┘                           │
│                                                              │
│ Toolbar: [Exportieren ▼] [Drucken] [Gruppieren ▼] [Aktualisieren]│
│                                                              │
│ ┌────┬──────────┬────────┬──────┬──────┬──────┬─────────┐ │
│ │ 📋 │Username  │Datum   │Start │Stopp │Zeit  │Standort │ │
│ ├────┼──────────┼────────┼──────┼──────┼──────┼─────────┤ │
│ │[▼] │jdoe      │26.01.25│08:15 │17:30 │08:15 │Berlin   │ │
│ │[▼] │jdoe      │25.01.25│08:00 │16:45 │07:45 │Berlin   │ │
│ │    │          │        │      │      │      │         │ │
│ │    │Wochensumme:       │      │      │39:30 │         │ │
│ └────┴──────────┴────────┴──────┴──────┴──────┴─────────┘ │
│                                                              │
│ Statusleiste: 15 Einträge | Summe: 156:30h | Ø 7:49h/Tag  │
└─────────────────────────────────────────────────────────────┘
```

## Zu erstellende Komponenten

### 1. ArbeitszeitAnzeigeControl.cs
```csharp
public partial class ArbeitszeitAnzeigeControl : UserControl
{
    private readonly IArbeitszeitService _arbeitszeitService;
    private readonly IAuthorizationService _authService;
    private readonly IExportService _exportService;
    
    // UI Components
    private FilterPanel filterPanel;
    private AnzeigePanel anzeigePanel;
    private ArbeitszeitDataGridView dgvArbeitszeiten;
    private ToolStrip toolStrip;
    private StatusStrip statusStrip;
    
    // Data
    private List<ArbeitszeitViewModel> _arbeitszeiten;
    private ArbeitszeitFilter _currentFilter;
}
```

### 2. FilterPanel Implementation
```csharp
public class FilterPanel : Panel
{
    private ComboBox cmbZeitraum;
    private DateTimePicker dtpVon;
    private DateTimePicker dtpBis;
    private ComboBox cmbStandort;
    private ComboBox cmbBenutzer;
    private ComboBox cmbStatus;
    private Button btnApplyFilter;
    
    public event EventHandler<FilterChangedEventArgs> FilterChanged;
    
    private void InitializeZeitraumCombo()
    {
        cmbZeitraum.Items.AddRange(new object[]
        {
            new ZeitraumItem("Heute", () => DateTime.Today, () => DateTime.Today),
            new ZeitraumItem("Diese Woche", () => GetWochenstart(), () => GetWochenende()),
            new ZeitraumItem("Letzte Woche", () => GetWochenstart(-1), () => GetWochenende(-1)),
            new ZeitraumItem("Dieser Monat", () => GetMonatsanfang(), () => GetMonatsende()),
            new ZeitraumItem("Letzter Monat", () => GetMonatsanfang(-1), () => GetMonatsende(-1)),
            new ZeitraumItem("Dieses Jahr", () => new DateTime(DateTime.Today.Year, 1, 1), 
                           () => new DateTime(DateTime.Today.Year, 12, 31)),
            new ZeitraumItem("Benutzerdefiniert", null, null)
        });
        
        cmbZeitraum.SelectedIndexChanged += (s, e) =>
        {
            var item = (ZeitraumItem)cmbZeitraum.SelectedItem;
            if (item.StartFunc != null)
            {
                dtpVon.Value = item.StartFunc();
                dtpBis.Value = item.EndFunc();
                dtpVon.Enabled = dtpBis.Enabled = false;
            }
            else
            {
                dtpVon.Enabled = dtpBis.Enabled = true;
            }
        };
    }
    
    public ArbeitszeitFilter GetFilter()
    {
        return new ArbeitszeitFilter
        {
            Von = dtpVon.Value.Date,
            Bis = dtpBis.Value.Date.AddDays(1).AddSeconds(-1),
            StandortId = (int?)cmbStandort.SelectedValue,
            BenutzerId = (int?)cmbBenutzer.SelectedValue,
            Status = (FilterStatus)cmbStatus.SelectedValue
        };
    }
}
```

### 3. ArbeitszeitDataGridView mit erweiterten Features
```csharp
public class ArbeitszeitDataGridView : DataGridView
{
    private DataGridViewButtonColumn colDetails;
    private DataGridViewTextBoxColumn colUsername;
    private DataGridViewTextBoxColumn colDatum;
    private DataGridViewTextBoxColumn colStartzeit;
    private DataGridViewTextBoxColumn colStoppzeit;
    private DataGridViewTextBoxColumn colGesamtzeit;
    private DataGridViewTextBoxColumn colPause;
    private DataGridViewTextBoxColumn colArbeitszeit;
    private DataGridViewTextBoxColumn colStandort;
    private DataGridViewTextBoxColumn colStatus;
    
    public ArbeitszeitDataGridView()
    {
        InitializeColumns();
        ConfigureAppearance();
        EnableGrouping();
    }
    
    private void InitializeColumns()
    {
        colDetails = new DataGridViewButtonColumn
        {
            Name = "Details",
            HeaderText = "",
            Text = "📋",
            UseColumnTextForButtonValue = true,
            Width = 40,
            Frozen = true
        };
        
        colDatum = new DataGridViewTextBoxColumn
        {
            Name = "Datum",
            HeaderText = "Datum",
            Width = 100,
            DefaultCellStyle = new DataGridViewCellStyle 
            { 
                Format = "ddd, dd.MM.yy",
                FormatProvider = new CultureInfo("de-DE")
            }
        };
        
        // Bedingte Formatierung für Überstunden
        colArbeitszeit = new DataGridViewTextBoxColumn
        {
            Name = "Arbeitszeit",
            HeaderText = "Zeit",
            Width = 80
        };
    }
    
    protected override void OnCellFormatting(DataGridViewCellFormattingEventArgs e)
    {
        if (e.ColumnIndex == colArbeitszeit.Index)
        {
            var arbeitszeit = (TimeSpan)e.Value;
            if (arbeitszeit.TotalHours > 10)
                e.CellStyle.ForeColor = Color.Red;
            else if (arbeitszeit.TotalHours > 8)
                e.CellStyle.ForeColor = Color.Orange;
        }
        
        // Wochenenden hervorheben
        if (e.ColumnIndex == colDatum.Index)
        {
            var datum = (DateTime)e.Value;
            if (datum.DayOfWeek == DayOfWeek.Saturday || 
                datum.DayOfWeek == DayOfWeek.Sunday)
            {
                e.CellStyle.BackColor = Color.FromArgb(240, 240, 240);
            }
        }
        
        base.OnCellFormatting(e);
    }
}
```

### 4. Gruppierung und Summierung
```csharp
public class ArbeitszeitGruppierung
{
    public enum GruppierungsModus
    {
        Keine,
        NachWoche,
        NachMonat,
        NachBenutzer,
        NachStandort
    }
    
    public void ApplyGrouping(
        DataGridView dgv, 
        List<ArbeitszeitViewModel> data, 
        GruppierungsModus modus)
    {
        dgv.Rows.Clear();
        
        switch (modus)
        {
            case GruppierungsModus.NachWoche:
                var wochenGruppen = data
                    .GroupBy(a => new { 
                        Jahr = a.Datum.Year, 
                        Woche = GetKalenderwoche(a.Datum) 
                    })
                    .OrderBy(g => g.Key.Jahr)
                    .ThenBy(g => g.Key.Woche);
                    
                foreach (var gruppe in wochenGruppen)
                {
                    // Kopfzeile
                    var headerRow = dgv.Rows.Add();
                    dgv.Rows[headerRow].DefaultCellStyle.BackColor = Color.LightGray;
                    dgv.Rows[headerRow].DefaultCellStyle.Font = new Font(dgv.Font, FontStyle.Bold);
                    dgv[1, headerRow].Value = $"KW {gruppe.Key.Woche}/{gruppe.Key.Jahr}";
                    
                    // Datenzeilen
                    foreach (var item in gruppe)
                    {
                        AddDataRow(dgv, item);
                    }
                    
                    // Summenzeile
                    var sumRow = dgv.Rows.Add();
                    dgv.Rows[sumRow].DefaultCellStyle.Font = new Font(dgv.Font, FontStyle.Bold);
                    dgv[1, sumRow].Value = "Wochensumme:";
                    dgv[6, sumRow].Value = FormatTimeSpan(
                        TimeSpan.FromHours(gruppe.Sum(a => a.Arbeitszeit.TotalHours))
                    );
                }
                break;
        }
    }
}
```

### 5. Export-Funktionalität
```csharp
public interface IExportService
{
    Task ExportToExcelAsync(List<ArbeitszeitViewModel> data, string filename);
    Task ExportToPdfAsync(List<ArbeitszeitViewModel> data, string filename);
    Task ExportToCsvAsync(List<ArbeitszeitViewModel> data, string filename);
}

public class ExportService : IExportService
{
    public async Task ExportToExcelAsync(
        List<ArbeitszeitViewModel> data, 
        string filename)
    {
        using (var package = new ExcelPackage())
        {
            var worksheet = package.Workbook.Worksheets.Add("Arbeitszeiten");
            
            // Header
            worksheet.Cells[1, 1].Value = "Arbeitszeiten Export";
            worksheet.Cells[1, 1, 1, 10].Merge = true;
            worksheet.Cells[1, 1].Style.Font.Size = 16;
            worksheet.Cells[1, 1].Style.Font.Bold = true;
            
            // Metadaten
            worksheet.Cells[3, 1].Value = "Erstellt am:";
            worksheet.Cells[3, 2].Value = DateTime.Now.ToString("dd.MM.yyyy HH:mm");
            worksheet.Cells[4, 1].Value = "Zeitraum:";
            worksheet.Cells[4, 2].Value = $"{data.Min(d => d.Datum):dd.MM.yyyy} - " +
                                        $"{data.Max(d => d.Datum):dd.MM.yyyy}";
            
            // Spaltenüberschriften
            var row = 6;
            worksheet.Cells[row, 1].Value = "Benutzer";
            worksheet.Cells[row, 2].Value = "Datum";
            worksheet.Cells[row, 3].Value = "Wochentag";
            worksheet.Cells[row, 4].Value = "Startzeit";
            worksheet.Cells[row, 5].Value = "Endzeit";
            worksheet.Cells[row, 6].Value = "Pause";
            worksheet.Cells[row, 7].Value = "Arbeitszeit";
            worksheet.Cells[row, 8].Value = "Standort";
            worksheet.Cells[row, 9].Value = "Status";
            
            // Formatierung Header
            using (var range = worksheet.Cells[row, 1, row, 9])
            {
                range.Style.Font.Bold = true;
                range.Style.Fill.PatternType = ExcelFillStyle.Solid;
                range.Style.Fill.BackgroundColor.SetColor(Color.LightGray);
            }
            
            // Daten
            foreach (var item in data)
            {
                row++;
                worksheet.Cells[row, 1].Value = item.Username;
                worksheet.Cells[row, 2].Value = item.Datum;
                worksheet.Cells[row, 3].Value = item.Datum.ToString("dddd", new CultureInfo("de-DE"));
                worksheet.Cells[row, 4].Value = item.Startzeit.ToString("HH:mm");
                worksheet.Cells[row, 5].Value = item.Stoppzeit?.ToString("HH:mm");
                worksheet.Cells[row, 6].Value = item.Pausenzeit.ToString(@"hh\:mm");
                worksheet.Cells[row, 7].Value = item.Arbeitszeit.ToString(@"hh\:mm");
                worksheet.Cells[row, 8].Value = item.Standort;
                worksheet.Cells[row, 9].Value = item.Status;
            }
            
            // Summenzeile
            row += 2;
            worksheet.Cells[row, 6].Value = "Summe:";
            worksheet.Cells[row, 7].Formula = $"SUM(G7:G{row-2})";
            worksheet.Cells[row, 7].Style.Font.Bold = true;
            
            // Auto-fit Spalten
            worksheet.Cells.AutoFitColumns();
            
            // Speichern
            await package.SaveAsAsync(new FileInfo(filename));
        }
    }
}
```

### 6. Druckvorschau
```csharp
public class ArbeitszeitDruckvorschau : PrintPreviewDialog
{
    private PrintDocument _printDocument;
    private List<ArbeitszeitViewModel> _data;
    private int _currentPage;
    private int _rowsPerPage = 40;
    
    public ArbeitszeitDruckvorschau(List<ArbeitszeitViewModel> data)
    {
        _data = data;
        _printDocument = new PrintDocument();
        _printDocument.PrintPage += PrintDocument_PrintPage;
        
        Document = _printDocument;
    }
    
    private void PrintDocument_PrintPage(object sender, PrintPageEventArgs e)
    {
        var g = e.Graphics;
        var font = new Font("Arial", 10);
        var headerFont = new Font("Arial", 12, FontStyle.Bold);
        
        // Kopfzeile
        g.DrawString("Arbeitszeitübersicht", headerFont, Brushes.Black, 50, 50);
        g.DrawString($"Seite {_currentPage + 1}", font, Brushes.Black, 700, 50);
        
        // Tabellenkopf
        var y = 100;
        DrawTableHeader(g, font, y);
        
        // Daten
        y += 30;
        var startIndex = _currentPage * _rowsPerPage;
        var endIndex = Math.Min(startIndex + _rowsPerPage, _data.Count);
        
        for (int i = startIndex; i < endIndex; i++)
        {
            DrawDataRow(g, font, _data[i], y);
            y += 20;
        }
        
        // Weitere Seiten?
        e.HasMorePages = endIndex < _data.Count;
        if (e.HasMorePages) _currentPage++;
    }
}
```

### 7. Statistik-Panel
```csharp
public class ArbeitszeitStatistikPanel : Panel
{
    private Label lblGesamtstunden;
    private Label lblDurchschnitt;
    private Label lblUeberstunden;
    private ProgressBar pbWochensoll;
    
    public void UpdateStatistik(List<ArbeitszeitViewModel> data)
    {
        var gesamtStunden = data.Sum(a => a.Arbeitszeit.TotalHours);
        var durchschnitt = data.Any() ? gesamtStunden / data.Count : 0;
        
        lblGesamtstunden.Text = $"Gesamt: {gesamtStunden:F1}h";
        lblDurchschnitt.Text = $"Ø {durchschnitt:F1}h/Tag";
        
        // Wochensoll-Progress
        var wochenStunden = data
            .Where(a => a.Datum >= GetWochenstart())
            .Sum(a => a.Arbeitszeit.TotalHours);
            
        pbWochensoll.Maximum = 40; // Soll-Wochenarbeitszeit
        pbWochensoll.Value = Math.Min((int)wochenStunden, pbWochensoll.Maximum);
        
        // Farbe basierend auf Fortschritt
        if (wochenStunden > 40)
            pbWochensoll.ForeColor = Color.Red;
        else if (wochenStunden > 35)
            pbWochensoll.ForeColor = Color.Orange;
        else
            pbWochensoll.ForeColor = Color.Green;
    }
}
```

### 8. Kontext-Menü für Zeilen
```csharp
public class ArbeitszeitKontextMenu : ContextMenuStrip
{
    public ArbeitszeitKontextMenu(bool canEdit, bool canDelete, bool canApprove)
    {
        Items.Add("Details anzeigen", null, OnDetailsClick);
        Items.Add(new ToolStripSeparator());
        
        if (canEdit)
        {
            Items.Add("Bearbeiten", null, OnEditClick);
            Items.Add("Duplizieren", null, OnDuplicateClick);
        }
        
        if (canDelete)
        {
            Items.Add("Löschen", null, OnDeleteClick);
        }
        
        if (canApprove)
        {
            Items.Add(new ToolStripSeparator());
            Items.Add("Genehmigen", null, OnApproveClick);
            Items.Add("Ablehnen", null, OnRejectClick);
        }
        
        Items.Add(new ToolStripSeparator());
        Items.Add("In Zwischenablage kopieren", null, OnCopyClick);
    }
}
```

### 9. Live-Aktualisierung
```csharp
public class AutoRefreshService
{
    private Timer _refreshTimer;
    private readonly TimeSpan _refreshInterval = TimeSpan.FromMinutes(1);
    
    public event EventHandler RefreshRequired;
    
    public void StartAutoRefresh()
    {
        _refreshTimer = new Timer(_refreshInterval.TotalMilliseconds);
        _refreshTimer.Elapsed += (s, e) => RefreshRequired?.Invoke(this, EventArgs.Empty);
        _refreshTimer.Start();
    }
    
    public void StopAutoRefresh()
    {
        _refreshTimer?.Stop();
        _refreshTimer?.Dispose();
    }
}
```

### 10. Performance-Optimierung
```csharp
public class ArbeitszeitDataCache
{
    private readonly MemoryCache _cache;
    private readonly TimeSpan _cacheExpiration = TimeSpan.FromMinutes(5);
    
    public async Task<List<ArbeitszeitViewModel>> GetArbeitszeitenAsync(
        ArbeitszeitFilter filter)
    {
        var cacheKey = GetCacheKey(filter);
        
        if (_cache.TryGetValue<List<ArbeitszeitViewModel>>(cacheKey, out var cached))
            return cached;
            
        var data = await LoadFromDatabaseAsync(filter);
        
        _cache.Set(cacheKey, data, _cacheExpiration);
        
        return data;
    }
    
    public void InvalidateCache(int? benutzerId = null)
    {
        if (benutzerId.HasValue)
        {
            // Nur Benutzer-spezifische Einträge löschen
            InvalidateUserCache(benutzerId.Value);
        }
        else
        {
            // Kompletten Cache leeren
            _cache.Clear();
        }
    }
}
```

## Benötigte Dateien
- ArbeitszeitService
- AuthorizationService aus Schritt 3.3
- Entity-Modelle aus Schritt 1.2
- ExportService-Bibliotheken (EPPlus, iTextSharp)

## Erwartete Ausgabe
```
UI/
├── Controls/
│   ├── ArbeitszeitAnzeigeControl.cs
│   ├── ArbeitszeitAnzeigeControl.Designer.cs
│   ├── FilterPanel.cs
│   ├── AnzeigePanel.cs
│   ├── ArbeitszeitDataGridView.cs
│   └── ArbeitszeitStatistikPanel.cs
├── Services/
│   ├── ExportService.cs
│   ├── ArbeitszeitGruppierung.cs
│   ├── AutoRefreshService.cs
│   └── ArbeitszeitDataCache.cs
├── Dialogs/
│   └── ArbeitszeitDruckvorschau.cs
├── Models/
│   ├── ArbeitszeitViewModel.cs
│   ├── ArbeitszeitFilter.cs
│   └── ZeitraumItem.cs
└── Resources/
    └── ExportTemplates/
```

## Hinweise
- Virtual Mode für große Datenmengen
- Lazy Loading bei Gruppierung
- Background Worker für Export
- Responsive UI während Datenladung
- Lokalisierung für Export-Formate
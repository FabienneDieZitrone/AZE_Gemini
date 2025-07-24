---
title: Prompt für Schritt 4.3 - Stammdatenverwaltung
description: Detaillierter Prompt zur Erstellung des Formulars für Benutzerstammdaten
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 4.3: Stammdatenverwaltung

## Aufgabe
Erstelle ein umfassendes Stammdaten-Verwaltungsformular mit rollenabhängigen Bearbeitungsmöglichkeiten und Validierung.

## UI-Layout

### Formular-Struktur:
```
┌─────────────────────────────────────────────────┐
│  Stammdatenverwaltung                          │
├─────────────────────────────────────────────────┤
│                                                 │
│  Benutzerauswahl: [Dropdown] 🔍  [Neu] [Löschen]│ ← Nur für Berechtigte
│                                                 │
│  ┌─ Persönliche Daten ─────────────────────┐  │
│  │ Benutzername:    [___________] (readonly)│  │
│  │ Vorname:        [___________]           │  │
│  │ Nachname:       [___________]           │  │
│  │ E-Mail:         [___________]           │  │
│  │ Rolle:          [Dropdown   ▼]          │  │
│  │ Status:         ☑ Aktiv                 │  │
│  └─────────────────────────────────────────┘  │
│                                                 │
│  ┌─ Arbeitszeit-Einstellungen ─────────────┐  │
│  │ Wochenarbeitszeit: [40.0] Stunden       │  │
│  │                                          │  │
│  │ Arbeitstage:                            │  │
│  │ ☑ Montag  ☑ Dienstag  ☑ Mittwoch       │  │
│  │ ☑ Donnerstag  ☑ Freitag                 │  │
│  │                                          │  │
│  │ ☐ Home Office erlaubt                   │  │
│  │ Max. Home Office Tage/Woche: [3]        │  │
│  └─────────────────────────────────────────┘  │
│                                                 │
│  ┌─ Standortzuordnung ─────────────────────┐  │
│  │ Verfügbare Standorte    Zugeordnete     │  │
│  │ ┌─────────────────┐    ┌──────────────┐│  │
│  │ │ Hamburg         │ >> │ Berlin (H)   ││  │
│  │ │ München         │    │ Frankfurt    ││  │
│  │ │ Köln            │ << │              ││  │
│  │ └─────────────────┘    └──────────────┘│  │
│  │        [Hauptstandort festlegen]         │  │
│  └─────────────────────────────────────────┘  │
│                                                 │
│  [Speichern] [Abbrechen] [Änderungen anzeigen] │
└─────────────────────────────────────────────────┘
```

## Zu erstellende Komponenten

### 1. StammdatenControl.cs
```csharp
public partial class StammdatenControl : UserControl
{
    private readonly IBenutzerService _benutzerService;
    private readonly IStandortService _standortService;
    private readonly IAuthorizationService _authService;
    private readonly IValidationService _validationService;
    
    private Benutzer _currentBenutzer;
    private Stammdaten _currentStammdaten;
    private bool _hasChanges;
    
    // UI Controls
    private ComboBox cmbBenutzerauswahl;
    private GroupBox grpPersonalData;
    private GroupBox grpArbeitszeitSettings;
    private GroupBox grpStandortAssignment;
    private DualListBox dlbStandorte;
}
```

### 2. DualListBox für Standortzuordnung
```csharp
public class DualListBox : UserControl
{
    private ListBox lstAvailable;
    private ListBox lstAssigned;
    private Button btnAdd;
    private Button btnRemove;
    private Button btnAddAll;
    private Button btnRemoveAll;
    
    public event EventHandler SelectionChanged;
    
    public List<StandortItem> GetAssignedItems()
    {
        return lstAssigned.Items.Cast<StandortItem>().ToList();
    }
    
    public void SetItems(
        IEnumerable<StandortItem> available, 
        IEnumerable<StandortItem> assigned)
    {
        lstAvailable.Items.Clear();
        lstAssigned.Items.Clear();
        
        foreach (var item in available.Except(assigned))
            lstAvailable.Items.Add(item);
            
        foreach (var item in assigned)
            lstAssigned.Items.Add(item);
    }
    
    private void btnAdd_Click(object sender, EventArgs e)
    {
        MoveSelectedItems(lstAvailable, lstAssigned);
    }
}

public class StandortItem
{
    public int StandortID { get; set; }
    public string Bezeichnung { get; set; }
    public bool IstHauptstandort { get; set; }
    
    public override string ToString()
    {
        return IstHauptstandort ? $"{Bezeichnung} (H)" : Bezeichnung;
    }
}
```

### 3. Benutzerauswahl mit Suche
```csharp
public class BenutzerSuchControl : UserControl
{
    private ComboBox cmbBenutzer;
    private TextBox txtSuche;
    private Timer _searchTimer;
    
    public event EventHandler<BenutzerSelectedEventArgs> BenutzerSelected;
    
    private async void txtSuche_TextChanged(object sender, EventArgs e)
    {
        // Debounce search
        _searchTimer?.Stop();
        _searchTimer = new Timer { Interval = 300 };
        _searchTimer.Tick += async (s, ev) =>
        {
            _searchTimer.Stop();
            await SearchUsers(txtSuche.Text);
        };
        _searchTimer.Start();
    }
    
    private async Task SearchUsers(string searchTerm)
    {
        var users = await _benutzerService.SearchUsersAsync(searchTerm);
        
        cmbBenutzer.DataSource = users;
        cmbBenutzer.DisplayMember = "AnzeigeName";
        cmbBenutzer.ValueMember = "BenutzerID";
    }
}
```

### 4. Validierung und Fehleranzeige
```csharp
public class StammdatenValidator : IStammdatenValidator
{
    private ErrorProvider _errorProvider;
    
    public ValidationResult ValidateStammdaten(Stammdaten stammdaten)
    {
        var errors = new List<ValidationError>();
        
        // Wochenarbeitszeit
        if (stammdaten.Wochenarbeitszeit < 0 || stammdaten.Wochenarbeitszeit > 60)
        {
            errors.Add(new ValidationError
            {
                Field = "Wochenarbeitszeit",
                Message = "Wochenarbeitszeit muss zwischen 0 und 60 Stunden liegen"
            });
        }
        
        // Mindestens ein Arbeitstag
        if (!stammdaten.Arbeitstag_Mo && !stammdaten.Arbeitstag_Di && 
            !stammdaten.Arbeitstag_Mi && !stammdaten.Arbeitstag_Do && 
            !stammdaten.Arbeitstag_Fr)
        {
            errors.Add(new ValidationError
            {
                Field = "Arbeitstage",
                Message = "Mindestens ein Arbeitstag muss ausgewählt sein"
            });
        }
        
        // E-Mail Format
        if (!string.IsNullOrEmpty(stammdaten.Benutzer.Email) && 
            !IsValidEmail(stammdaten.Benutzer.Email))
        {
            errors.Add(new ValidationError
            {
                Field = "Email",
                Message = "Ungültiges E-Mail-Format"
            });
        }
        
        return new ValidationResult(errors);
    }
}
```

### 5. Rollenbasierte UI-Anpassung
```csharp
public class StammdatenUIConfigurator
{
    public void ConfigureForRole(StammdatenControl control, Benutzer currentUser)
    {
        var rolle = currentUser.Rolle.Berechtigungsstufe;
        
        // Benutzerauswahl
        control.ShowBenutzerauswahl = rolle >= Berechtigungsstufe.Standortleiter;
        
        // Editierbare Felder
        control.EnablePersonalDataEdit = rolle >= Berechtigungsstufe.Standortleiter;
        control.EnableRoleEdit = rolle >= Berechtigungsstufe.Bereichsleiter &&
                                rolle > Berechtigungsstufe.Admin; // Admin nur durch Admin
        
        // Lösch-Funktionen
        control.ShowDeleteButton = rolle >= Berechtigungsstufe.Bereichsleiter;
        
        // Standortzuordnung
        control.EnableStandortEdit = rolle >= Berechtigungsstufe.Standortleiter;
        
        // Für normale Mitarbeiter: Nur eigene Daten, nur Lesen
        if (rolle <= Berechtigungsstufe.Honorarkraft)
        {
            control.SetReadOnlyMode(true);
            control.LoadBenutzer(currentUser.BenutzerID);
        }
    }
}
```

### 6. Änderungsverfolgung
```csharp
public class ChangeTracker
{
    private Dictionary<string, object> _originalValues;
    private Dictionary<string, object> _currentValues;
    
    public void StartTracking(object entity)
    {
        _originalValues = GetPropertyValues(entity);
        _currentValues = new Dictionary<string, object>(_originalValues);
    }
    
    public bool HasChanges()
    {
        return !_originalValues.SequenceEqual(_currentValues);
    }
    
    public List<PropertyChange> GetChanges()
    {
        var changes = new List<PropertyChange>();
        
        foreach (var kvp in _currentValues)
        {
            if (!Equals(kvp.Value, _originalValues[kvp.Key]))
            {
                changes.Add(new PropertyChange
                {
                    PropertyName = kvp.Key,
                    OldValue = _originalValues[kvp.Key],
                    NewValue = kvp.Value
                });
            }
        }
        
        return changes;
    }
}

public class PropertyChange
{
    public string PropertyName { get; set; }
    public object OldValue { get; set; }
    public object NewValue { get; set; }
    public string DisplayName => GetDisplayName(PropertyName);
}
```

### 7. Speichern mit Genehmigung
```csharp
public class StammdatenSaveService
{
    public async Task<SaveResult> SaveStammdatenAsync(
        Stammdaten stammdaten, 
        List<PropertyChange> changes)
    {
        // Prüfen ob Genehmigung erforderlich
        var requiresApproval = RequiresApproval(changes);
        
        if (requiresApproval)
        {
            // Änderungsantrag erstellen
            var antrag = new StammdatenAenderungsantrag
            {
                BenutzerID = stammdaten.BenutzerID,
                Changes = SerializeChanges(changes),
                Grund = "Stammdatenänderung",
                BeantragtVon = _sessionManager.CurrentUser.BenutzerID,
                BeantragtAm = DateTime.Now
            };
            
            await _genehmigungService.CreateStammdatenAntragAsync(antrag);
            
            return new SaveResult
            {
                Success = true,
                RequiresApproval = true,
                Message = "Änderungen wurden zur Genehmigung eingereicht"
            };
        }
        else
        {
            // Direkt speichern
            await _benutzerService.UpdateStammdatenAsync(stammdaten);
            
            return new SaveResult
            {
                Success = true,
                Message = "Stammdaten wurden erfolgreich gespeichert"
            };
        }
    }
    
    private bool RequiresApproval(List<PropertyChange> changes)
    {
        // Kritische Felder die Genehmigung erfordern
        var criticalFields = new[] { "Rolle", "Wochenarbeitszeit", "Aktiv" };
        
        return changes.Any(c => criticalFields.Contains(c.PropertyName));
    }
}
```

### 8. Arbeitstage-Auswahl
```csharp
public class ArbeitstageControl : UserControl
{
    private CheckBox[] _tageCheckBoxes;
    private Label lblGesamtstunden;
    
    public event EventHandler ArbeitstageChanged;
    
    public ArbeitstageControl()
    {
        var tage = new[] { "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag" };
        _tageCheckBoxes = new CheckBox[5];
        
        var panel = new FlowLayoutPanel { FlowDirection = FlowDirection.LeftToRight };
        
        for (int i = 0; i < tage.Length; i++)
        {
            _tageCheckBoxes[i] = new CheckBox
            {
                Text = tage[i],
                Checked = true,
                AutoSize = true,
                Margin = new Padding(5)
            };
            _tageCheckBoxes[i].CheckedChanged += (s, e) => 
            {
                UpdateGesamtstunden();
                ArbeitstageChanged?.Invoke(this, EventArgs.Empty);
            };
            panel.Controls.Add(_tageCheckBoxes[i]);
        }
    }
    
    private void UpdateGesamtstunden()
    {
        var anzahlTage = _tageCheckBoxes.Count(cb => cb.Checked);
        var wochenStunden = GetWochenarbeitszeit();
        var tagesStunden = anzahlTage > 0 ? wochenStunden / anzahlTage : 0;
        
        lblGesamtstunden.Text = $"Ø {tagesStunden:F1} Stunden pro Tag";
    }
}
```

### 9. Home Office Einstellungen
```csharp
public class HomeOfficeSettings : UserControl
{
    private CheckBox chkHomeOfficeErlaubt;
    private NumericUpDown nudMaxTageProWoche;
    private Panel pnlHomeOfficeDetails;
    
    public HomeOfficeSettings()
    {
        chkHomeOfficeErlaubt.CheckedChanged += (s, e) =>
        {
            pnlHomeOfficeDetails.Enabled = chkHomeOfficeErlaubt.Checked;
            
            if (!chkHomeOfficeErlaubt.Checked)
                nudMaxTageProWoche.Value = 0;
        };
        
        nudMaxTageProWoche.Minimum = 1;
        nudMaxTageProWoche.Maximum = 5;
        nudMaxTageProWoche.Value = 3;
    }
}
```

### 10. Änderungshistorie anzeigen
```csharp
public class StammdatenHistorieDialog : Form
{
    private DataGridView dgvHistorie;
    
    public StammdatenHistorieDialog(int benutzerId)
    {
        Text = "Änderungshistorie Stammdaten";
        Size = new Size(800, 600);
        
        dgvHistorie = new DataGridView
        {
            Dock = DockStyle.Fill,
            ReadOnly = true,
            AllowUserToAddRows = false,
            AutoSizeColumnsMode = DataGridViewAutoSizeColumnsMode.Fill
        };
        
        LoadHistorie(benutzerId);
    }
    
    private async void LoadHistorie(int benutzerId)
    {
        var historie = await _auditService.GetStammdatenHistorieAsync(benutzerId);
        
        dgvHistorie.DataSource = historie.Select(h => new
        {
            Datum = h.GeaendertAm,
            Bearbeiter = h.GeaendertVonBenutzer.Vollname,
            Feld = h.PropertyName,
            AlterWert = h.OldValue,
            NeuerWert = h.NewValue,
            Status = h.IstGenehmigt ? "Genehmigt" : "Ausstehend"
        }).ToList();
    }
}
```

## Benötigte Dateien
- Entity-Modelle aus Schritt 1.2
- BenutzerService und StandortService
- AuthorizationService aus Schritt 3.3
- ValidationService

## Erwartete Ausgabe
```
UI/
├── Controls/
│   ├── StammdatenControl.cs
│   ├── StammdatenControl.Designer.cs
│   ├── StammdatenControl.resx
│   ├── DualListBox.cs
│   ├── BenutzerSuchControl.cs
│   ├── ArbeitstageControl.cs
│   └── HomeOfficeSettings.cs
├── Dialogs/
│   ├── StammdatenHistorieDialog.cs
│   └── BenutzerAnlegenDialog.cs
├── Services/
│   ├── StammdatenUIConfigurator.cs
│   ├── StammdatenSaveService.cs
│   └── ChangeTracker.cs
├── Validators/
│   └── StammdatenValidator.cs
└── Models/
    ├── StandortItem.cs
    ├── PropertyChange.cs
    └── SaveResult.cs
```

## Hinweise
- Transactional Save für Konsistenz
- Optimistic Locking bei gleichzeitigen Änderungen
- Keyboard Navigation (Tab-Order)
- Undo/Redo Funktionalität vorbereiten
- Performance bei vielen Benutzern (Lazy Loading)
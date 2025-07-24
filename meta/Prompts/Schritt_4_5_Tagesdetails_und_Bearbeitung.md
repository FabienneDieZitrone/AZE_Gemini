---
title: Schritt 4.5 - Tagesdetails und Bearbeitung
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_4_5_Tagesdetails_und_Bearbeitung.md
description: Detaillierter Prompt für die Implementierung der Tagesdetailansicht mit Bearbeitungsfunktion
---

# Schritt 4.5: Tagesdetails und Bearbeitung implementieren

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Grundfunktionalitäten der Anwendung sind bereits implementiert:
- Hauptfenster mit Navigation (Schritt 4.1)
- Startseite mit Zeiterfassung (Schritt 4.2)
- Stammdatenverwaltung (Schritt 4.3)
- Arbeitszeitanzeige mit Filtern (Schritt 4.4)

Jetzt soll eine Detailansicht für einzelne Arbeitstage mit Bearbeitungsmöglichkeit implementiert werden.

## Aufgabe
Entwickle ein Formular zur detaillierten Anzeige und Bearbeitung von Arbeitstagen. Das Formular soll alle Zeiteinträge eines Tages anzeigen und die Möglichkeit bieten, diese zu bearbeiten, wobei der Genehmigungsworkflow beachtet werden muss.

## Anforderungen

### 1. Formular-Design (FrmTagesdetails)
```csharp
public partial class FrmTagesdetails : Form
{
    // Eigenschaften
    private readonly IZeiterfassungService _zeiterfassungService;
    private readonly IGenehmigungService _genehmigungService;
    private readonly IBenutzerService _benutzerService;
    private DateTime _selectedDate;
    private int _benutzerId;
    private List<ZeiterfassungDto> _tagesEintraege;
    private bool _hasUnsavedChanges;
}
```

### 2. UI-Layout
```
+----------------------------------------------------------+
| Tagesdetails - [Datum] - [Benutzername]           [X] |
+----------------------------------------------------------+
| Toolbar: [Speichern] [Genehmigung anfordern] [Drucken]  |
+----------------------------------------------------------+
| Zusammenfassung:                                         |
| ┌------------------------------------------------------┐ |
| | Datum: 26.01.2025  Status: [Genehmigt/Offen/...]    | |
| | Sollzeit: 8:00 h   Istzeit: 8:15 h   Diff: +0:15 h | |
| | Standort: Hauptstandort                             | |
| └------------------------------------------------------┘ |
|                                                          |
| Zeiteinträge:                                            |
| ┌------------------------------------------------------┐ |
| | Zeit    | Typ      | Dauer | Projekt | Kommentar |▲| |
| |---------|----------|-------|---------|-----------|─| |
| | 08:00   | Kommen   | -     | -       | -         | | |
| | 12:00   | Pause B. | 0:30  | -       | Mittag    | | |
| | 12:30   | Pause E. | -     | -       | -         | | |
| | 17:15   | Gehen    | -     | -       | -         |▼| |
| └------------------------------------------------------┘ |
|                                                          |
| Bearbeitungsbereich:                                     |
| ┌------------------------------------------------------┐ |
| | Neuer Eintrag / Eintrag bearbeiten:                  | |
| | Zeit: [__:__] Typ: [Dropdown] Projekt: [Dropdown]    | |
| | Kommentar: [________________________]                | |
| | [Hinzufügen/Aktualisieren] [Löschen] [Abbrechen]    | |
| └------------------------------------------------------┘ |
|                                                          |
| Änderungsgrund (bei nachträglicher Bearbeitung):        |
| [________________________________________________]      |
|                                                          |
| [Schließen]                                              |
+----------------------------------------------------------+
```

### 3. Datenmodell
```csharp
public class TagesdetailViewModel
{
    public DateTime Datum { get; set; }
    public int BenutzerId { get; set; }
    public string BenutzerName { get; set; }
    public TimeSpan Sollzeit { get; set; }
    public TimeSpan Istzeit { get; set; }
    public TimeSpan Differenz { get; set; }
    public string Standort { get; set; }
    public GenehmigungStatus Status { get; set; }
    public List<ZeiterfassungDetailDto> Eintraege { get; set; }
    public bool IstBearbeitbar { get; set; }
    public bool BenoetigtGenehmigung { get; set; }
}

public class ZeiterfassungDetailDto
{
    public int Id { get; set; }
    public TimeSpan Zeit { get; set; }
    public ErfassungsTyp Typ { get; set; }
    public TimeSpan? Dauer { get; set; }
    public string Projekt { get; set; }
    public string Kommentar { get; set; }
    public bool IstManuell { get; set; }
    public DateTime ErstelltAm { get; set; }
    public string ErstelltVon { get; set; }
}
```

### 4. Funktionalitäten

#### 4.1 Daten laden
```csharp
private async Task LadeTagesdaten()
{
    try
    {
        ShowLoadingIndicator();
        
        // Lade alle Einträge des Tages
        _tagesEintraege = await _zeiterfassungService
            .GetTageseintraegeAsync(_benutzerId, _selectedDate);
        
        // Berechne Zusammenfassung
        var zusammenfassung = await _zeiterfassungService
            .BerechneTagesZusammenfassungAsync(_benutzerId, _selectedDate);
        
        // Prüfe Bearbeitbarkeit
        var kannBearbeiten = await PruefeBearbeitungsrechte();
        
        // Update UI
        AktualisiereAnzeige(zusammenfassung, kannBearbeiten);
    }
    catch (Exception ex)
    {
        MessageBox.Show($"Fehler beim Laden: {ex.Message}", 
            "Fehler", MessageBoxButtons.OK, MessageBoxIcon.Error);
    }
    finally
    {
        HideLoadingIndicator();
    }
}
```

#### 4.2 Eintrag bearbeiten
```csharp
private async Task BearbeiteEintrag(ZeiterfassungDetailDto eintrag)
{
    // Prüfe ob Bearbeitung erlaubt
    if (!await IstBearbeitungErlaubt(eintrag))
    {
        MessageBox.Show("Dieser Eintrag kann nicht bearbeitet werden.", 
            "Hinweis", MessageBoxButtons.OK, MessageBoxIcon.Information);
        return;
    }
    
    // Lade Eintrag in Bearbeitungsbereich
    txtZeit.Text = eintrag.Zeit.ToString(@"hh\:mm");
    cmbTyp.SelectedValue = eintrag.Typ;
    cmbProjekt.SelectedValue = eintrag.Projekt;
    txtKommentar.Text = eintrag.Kommentar;
    
    // Aktiviere Bearbeitungsmodus
    _currentEditingId = eintrag.Id;
    btnHinzufuegen.Text = "Aktualisieren";
    btnLoeschen.Enabled = true;
}
```

#### 4.3 Validierung
```csharp
private bool ValidiereEintrag(out string fehlerMeldung)
{
    fehlerMeldung = string.Empty;
    
    // Zeit validieren
    if (!TimeSpan.TryParse(txtZeit.Text, out var zeit))
    {
        fehlerMeldung = "Ungültige Zeitangabe.";
        return false;
    }
    
    // Typ validieren
    if (cmbTyp.SelectedValue == null)
    {
        fehlerMeldung = "Bitte wählen Sie einen Erfassungstyp.";
        return false;
    }
    
    // Logische Validierung
    var typ = (ErfassungsTyp)cmbTyp.SelectedValue;
    
    // Prüfe chronologische Reihenfolge
    if (!IstChronologischKorrekt(zeit, typ))
    {
        fehlerMeldung = "Die Zeitangabe passt nicht zur chronologischen Reihenfolge.";
        return false;
    }
    
    // Prüfe Pausen
    if (typ == ErfassungsTyp.PauseBeginn || typ == ErfassungsTyp.PauseEnde)
    {
        if (!IstPauseGueltig(zeit, typ))
        {
            fehlerMeldung = "Ungültige Pausenzeiten.";
            return false;
        }
    }
    
    return true;
}
```

#### 4.4 Genehmigungsworkflow
```csharp
private async Task GenehmigungAnfordern()
{
    // Prüfe ob Änderungen vorhanden
    if (!_hasUnsavedChanges)
    {
        MessageBox.Show("Keine Änderungen vorhanden.", 
            "Information", MessageBoxButtons.OK, MessageBoxIcon.Information);
        return;
    }
    
    // Änderungsgrund abfragen
    if (string.IsNullOrWhiteSpace(txtAenderungsgrund.Text))
    {
        MessageBox.Show("Bitte geben Sie einen Änderungsgrund an.", 
            "Hinweis", MessageBoxButtons.OK, MessageBoxIcon.Warning);
        txtAenderungsgrund.Focus();
        return;
    }
    
    try
    {
        // Erstelle Genehmigungsantrag
        var antrag = new GenehmigungAntragDto
        {
            BenutzerId = _benutzerId,
            Datum = _selectedDate,
            Aenderungsgrund = txtAenderungsgrund.Text,
            GeaenderteEintraege = GetGeaenderteEintraege()
        };
        
        await _genehmigungService.ErstelleAntragAsync(antrag);
        
        MessageBox.Show("Genehmigungsantrag wurde erstellt.", 
            "Erfolg", MessageBoxButtons.OK, MessageBoxIcon.Information);
        
        // Aktualisiere Status
        await LadeTagesdaten();
    }
    catch (Exception ex)
    {
        MessageBox.Show($"Fehler beim Erstellen des Antrags: {ex.Message}", 
            "Fehler", MessageBoxButtons.OK, MessageBoxIcon.Error);
    }
}
```

### 5. Zusätzliche Features

#### 5.1 Tastaturkürzel
- F2: Ausgewählten Eintrag bearbeiten
- Entf: Ausgewählten Eintrag löschen
- Strg+S: Speichern
- Strg+P: Drucken

#### 5.2 Kontextmenü für Einträge
```csharp
private void InitializeContextMenu()
{
    var contextMenu = new ContextMenuStrip();
    
    contextMenu.Items.Add("Bearbeiten", null, (s, e) => BearbeiteAusgewaehltenEintrag());
    contextMenu.Items.Add("Löschen", null, (s, e) => LoescheAusgewaehltenEintrag());
    contextMenu.Items.Add("-");
    contextMenu.Items.Add("Kopieren", null, (s, e) => KopiereEintrag());
    contextMenu.Items.Add("Als Vorlage speichern", null, (s, e) => SpeichereAlsVorlage());
    
    dgvZeiteintraege.ContextMenuStrip = contextMenu;
}
```

#### 5.3 Druckfunktion
```csharp
private void DruckeTagesdetails()
{
    var printDialog = new PrintDialog();
    var printDocument = new PrintDocument();
    
    printDocument.PrintPage += (sender, e) =>
    {
        // Erstelle Druckansicht
        var g = e.Graphics;
        var font = new Font("Segoe UI", 10);
        var y = 50;
        
        // Kopfzeile
        g.DrawString($"Tagesdetails - {_selectedDate:dd.MM.yyyy}", 
            new Font("Segoe UI", 14, FontStyle.Bold), 
            Brushes.Black, 50, y);
        
        // Details drucken...
    };
    
    if (printDialog.ShowDialog() == DialogResult.OK)
    {
        printDocument.Print();
    }
}
```

### 6. Fehlerbehandlung
- Netzwerkfehler bei der Synchronisation
- Validierungsfehler bei der Eingabe
- Berechtigungsfehler bei der Bearbeitung
- Konflikte bei gleichzeitiger Bearbeitung

## Erwartete Ergebnisse

1. **Vollständiges Tagesdetail-Formular** mit allen UI-Komponenten
2. **Robuste Bearbeitungsfunktion** mit Validierung
3. **Integration des Genehmigungsworkflows**
4. **Benutzerfreundliche Fehlerbehandlung**
5. **Druckfunktion** für Tagesberichte
6. **Kontextmenü** für schnelle Aktionen

## Zusätzliche Hinweise
- Beachte die Berechtigungen basierend auf Benutzerrollen
- Implementiere optimistische Sperrung zur Konfliktvermeidung
- Zeige deutlich an, wenn eine Genehmigung erforderlich ist
- Protokolliere alle Änderungen für das Audit-Trail

## Nächste Schritte
Nach erfolgreicher Implementierung der Tagesdetails folgt Schritt 5.1: Offline-Modus und Synchronisation.
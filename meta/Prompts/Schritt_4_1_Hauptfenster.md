---
title: Prompt für Schritt 4.1 - Hauptfenster und Navigation
description: Detaillierter Prompt zur Erstellung des Hauptfensters mit Navigation
author: Tanja Trella
version: 1.0
lastUpdated: 26.01.2025
category: Entwicklung
---

# Prompt für Schritt 4.1: Hauptfenster und Navigation

## Aufgabe
Erstelle das Hauptfenster (MainForm) der Arbeitszeiterfassungsanwendung mit MP-Logo, Navigation und Grundlayout. Das Logo wird beim Start aus der Datenbank geladen.

## UI-Komponenten

### 1. MainForm Design
- **Fenster-Eigenschaften**:
  - Titel: "Arbeitszeiterfassung - [Benutzername]"
  - Icon: MP-Logo (aus der Datenbank geladen)
  - Größe: 1024x768 (resizable)
  - MinimumSize: 800x600
  - StartPosition: CenterScreen

### 2. Layout-Struktur
```
┌─────────────────────────────────────────┐
│ MP-Logo │ Arbeitszeiterfassung         │ (TitleBar, Logo aus DB)
├─────────────────────────────────────────┤
│ Benutzer: [Name] ([Rolle])    [Status] │ (StatusBar)
├─────────────────────────────────────────┤
│                                         │
│           Content Panel                 │ (UserControl Host)
│                                         │
├─────────────────────────────────────────┤
│ © 2025 Mikropartner | Version 1.0.0    │ (Footer)
└─────────────────────────────────────────┘
```

### 3. Zu erstellende Forms/UserControls

#### MainForm.cs
- Hauptcontainer mit Panel-Layout
- UserControl-Hosting Mechanismus
- Navigation zwischen Views
- Globale Shortcuts (F1=Hilfe, F5=Refresh)

#### StartPageControl.cs
- Begrüßung mit Benutzername
- Große Start/Stopp Taste
- Quicklinks-Panel
- Aktuelle Arbeitszeit-Anzeige

#### NavigationHelper.cs
- View-Switching Logic
- History-Management
- Breadcrumb-Unterstützung

#### StatusBarControl.cs
- Benutzerinformationen
- Online/Offline Status
- Sync-Status Anzeige
- Uhrzeit

### 4. Design-Anforderungen

#### Farbschema:
- Primär: #003366 (Dunkelblau)
- Sekundär: #0066CC (Hellblau)
- Akzent: #FF6600 (Orange)
- Hintergrund: #F5F5F5
- Text: #333333

#### Schriftarten:
- Headers: Segoe UI, 14pt, Bold
- Body: Segoe UI, 10pt
- Buttons: Segoe UI, 11pt

#### Icons:
- Material Design Icons
- 24x24 für Toolbar
- 16x16 für Buttons
- MP-Logo: 48x48 (aus der Datenbank geladen)

### 5. Controls und Events

#### MainForm Controls:
```csharp
- ToolStrip mainToolStrip
- Panel contentPanel
- StatusStrip statusStrip
- Timer sessionTimer
- NotifyIcon trayIcon
```

#### Key Events:
- Form_Load: Authentifizierung, UI-Setup
- Form_Closing: Daten speichern, Cleanup
- Timer_Tick: Session-Check, Zeit-Update
- Navigation_Changed: View wechseln

### 6. Helper-Klassen

#### UIHelper.cs
- Control-Factory Methoden
- Standard-Styles anwenden
- Responsive Layout Helpers

#### ResourceManager.cs
- Bilder und Icons laden
- Strings für Mehrsprachigkeit
- Cache-Management

## Benötigte Dateien
- AuthenticationService aus Schritt 3.1
- SessionManager
- MP-Logo aus Datenbank

## Erwartete Ausgabe
```
UI/
├── Forms/
│   ├── MainForm.cs
│   ├── MainForm.Designer.cs
│   └── MainForm.resx
├── Controls/
│   ├── StartPageControl.cs
│   ├── StartPageControl.Designer.cs
│   ├── StatusBarControl.cs
│   └── StatusBarControl.Designer.cs
├── Helpers/
│   ├── NavigationHelper.cs
│   ├── UIHelper.cs
│   └── ResourceManager.cs
└── Resources/
    ├── [Logo wird aus Datenbank geladen]
    ├── icons/
    └── strings.resx
```

## Beispiel-Code Struktur
```csharp
public partial class MainForm : Form
{
    private IAuthenticationService _authService;
    private ISessionManager _sessionManager;
    private UserControl _currentView;
    
    protected override void OnLoad(EventArgs e)
    {
        // Authentifizierung
        var user = await _authService.AuthenticateAsync();
        
        // UI Setup
        InitializeStatusBar(user);
        LoadStartPage();
        StartSessionTimer();
    }
    
    public void NavigateTo<T>() where T : UserControl, new()
    {
        // View wechseln mit Animation
    }
}
```

## Hinweise
- Verwende DataBinding wo möglich
- Implementiere Keyboard-Navigation
- Responsive Design für verschiedene Auflösungen
- Accessibility-Features (Tab-Order, ToolTips)
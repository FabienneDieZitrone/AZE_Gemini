---
title: Troubleshooting-Guide - Arbeitszeiterfassung
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/TROUBLESHOOTING_GUIDE.md
description: Lösungen für häufige Probleme bei der Entwicklung und im Betrieb der Arbeitszeiterfassung
---

# 🔧 Troubleshooting-Guide: Arbeitszeiterfassung

## 📋 Inhaltsverzeichnis

1. [Entwicklungsprobleme](#entwicklungsprobleme)
2. [Datenbankprobleme](#datenbankprobleme)
3. [UI-Probleme](#ui-probleme)
4. [Synchronisationsprobleme](#synchronisationsprobleme)
5. [Deployment-Probleme](#deployment-probleme)
6. [Laufzeitfehler](#laufzeitfehler)

---

## 🛠️ Entwicklungsprobleme

### Problem: Build fehlschlägt mit "SDK not found"
**Symptome**: 
```
error MSB3644: The reference assemblies for .NETCore,Version=v8.0 were not found
```

**Lösung**:
1. Prüfen Sie die installierte .NET Version:
   ```bash
   dotnet --list-sdks
   ```
2. Installieren Sie .NET 8.0 SDK:
   ```bash
   winget install Microsoft.DotNet.SDK.8
   ```
3. Starten Sie Visual Studio/VS Code neu

### Problem: NuGet-Pakete können nicht wiederhergestellt werden
**Symptome**: 
```
Unable to load the service index for source https://api.nuget.org/v3/index.json
```

**Lösung**:
1. Proxy-Einstellungen prüfen:
   ```bash
   dotnet nuget list source
   ```
2. NuGet-Cache leeren:
   ```bash
   dotnet nuget locals all --clear
   ```
3. Offline-Paketquelle hinzufügen:
   ```bash
   dotnet nuget add source C:\NuGetPackages -n LocalPackages
   ```

### Problem: Entity Framework Migrations schlagen fehl
**Symptome**:
```
Unable to create an object of type 'ArbeitszeitDbContext'
```

**Lösung**:
1. Design-time DbContext Factory hinzufügen:
```csharp
public class DesignTimeDbContextFactory : IDesignTimeDbContextFactory<ArbeitszeitDbContext>
{
    public ArbeitszeitDbContext CreateDbContext(string[] args)
    {
        var optionsBuilder = new DbContextOptionsBuilder<ArbeitszeitDbContext>();
        optionsBuilder.UseMySql("Server=localhost;Database=design;", ServerVersion.AutoDetect("Server=localhost;Database=design;"));
        return new ArbeitszeitDbContext(optionsBuilder.Options);
    }
}
```
2. Migration mit explizitem Projekt:
   ```bash
   dotnet ef migrations add InitialCreate --project Arbeitszeiterfassung.DAL --startup-project Arbeitszeiterfassung.UI
   ```

---

## 🗄️ Datenbankprobleme

### Problem: "Access denied for user" bei MySQL-Verbindung
**Symptome**:
```
MySqlException: Access denied for user 'root'@'localhost' (using password: YES)
```

**Lösung**:
1. MySQL-Benutzerrechte prüfen:
   ```sql
   mysql -u root -p
   SHOW GRANTS FOR 'root'@'localhost';
   ```
2. Benutzer neu anlegen:
   ```sql
   CREATE USER 'aze_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
   GRANT ALL PRIVILEGES ON db10454681_aze.* TO 'aze_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Connection String anpassen in appsettings.json

### Problem: "Too many connections" Fehler
**Symptome**:
```
MySqlException: Too many connections
```

**Lösung**:
1. Connection Pooling in Connection String:
   ```json
   "ConnectionString": "server=wp10454681.Server-he.de;database=db10454681-aze;uid=db10454681-aze;pwd=Start.321;MaximumPoolSize=50;ConnectionLifeTime=300;"
   ```
2. Dispose Pattern korrekt implementieren:
   ```csharp
   using (var context = new ArbeitszeitDbContext())
   {
       // Operationen
   } // Automatisches Dispose
   ```

### Problem: SQLite "database is locked"
**Symptome**:
```
SqliteException: database is locked
```

**Lösung**:
1. Write-Ahead Logging aktivieren:
   ```csharp
   optionsBuilder.UseSqlite($"Data Source={dbPath};Mode=ReadWriteCreate;Cache=Shared;");
   ```
2. Connection String erweitern:
   ```
   Data Source=offline.db;Mode=ReadWriteCreate;Cache=Shared;Journal Mode=WAL;
   ```

---

## 🖥️ UI-Probleme

### Problem: Windows Forms Designer lädt nicht
**Symptome**: "The designer could not be shown for this file"

**Lösung**:
1. Projekt neu bauen:
   ```bash
   dotnet clean
   dotnet build
   ```
2. Designer-generierte Datei prüfen (*.Designer.cs)
3. Visual Studio-Cache leeren:
   - Schließen Sie VS
   - Löschen Sie `.vs` Ordner
   - Löschen Sie `bin` und `obj` Ordner

### Problem: DataGridView zeigt keine Daten
**Symptome**: Grid bleibt leer trotz vorhandener Daten

**Lösung**:
```csharp
// Falsch - UI-Thread-Problem
private async void LoadData()
{
    var data = await _service.GetDataAsync();
    dataGridView1.DataSource = data; // Kann fehlschlagen
}

// Richtig - Thread-safe
private async void LoadData()
{
    var data = await _service.GetDataAsync();
    this.Invoke(new Action(() =>
    {
        dataGridView1.DataSource = data;
    }));
}
```

### Problem: "Cross-thread operation not valid"
**Symptome**: InvalidOperationException beim UI-Update

**Lösung**:
```csharp
// Extension Method für thread-safe UI Updates
public static class ControlExtensions
{
    public static void InvokeIfRequired(this Control control, Action action)
    {
        if (control.InvokeRequired)
            control.Invoke(action);
        else
            action();
    }
}

// Verwendung
lblStatus.InvokeIfRequired(() => lblStatus.Text = "Aktualisiert");
```

### Problem: "SDK 'Microsoft.NET.Sdk.WindowsDesktop' not found"
**Symptome**: Der Build bricht ab mit
```
error MSB4236: The SDK 'Microsoft.NET.Sdk.WindowsDesktop' specified could not be found.
```

**Lösung**:
1. Diese Workload ist nur unter Windows verfügbar. Führe auf einem Windows-System folgendes aus:
   ```cmd
   dotnet workload install windowsdesktop
   ```
   Alternativ kannst du `build-windows.cmd` verwenden, das die Workload automatisch installiert.
2. Danach das Projekt neu bauen:
   ```cmd
   dotnet build Arbeitszeiterfassung.sln
   ```

---

## 🔄 Synchronisationsprobleme

### Problem: Offline-Sync erstellt Duplikate
**Symptome**: Nach Synchronisation existieren Einträge doppelt

**Lösung**:
1. Unique-Constraints hinzufügen:
   ```csharp
   modelBuilder.Entity<Zeiterfassung>()
       .HasIndex(z => new { z.BenutzerId, z.Zeit, z.Typ })
       .IsUnique();
   ```
2. Sync-Logic anpassen:
   ```csharp
   // Prüfe Existenz vor Insert
   var exists = await context.Zeiterfassungen
       .AnyAsync(z => z.BenutzerId == entity.BenutzerId 
                   && z.Zeit == entity.Zeit 
                   && z.Typ == entity.Typ);
   
   if (!exists)
       await context.Zeiterfassungen.AddAsync(entity);
   ```

### Problem: Sync-Konflikte werden nicht erkannt
**Symptome**: Überschreibungen ohne Warnung

**Lösung**:
1. Timestamp-basierte Konflikterkennung:
   ```csharp
   public class SyncConflictDetector
   {
       public bool HasConflict(Entity local, Entity remote)
       {
           return local.ModifiedAt != remote.ModifiedAt
               && local.Version != remote.Version;
       }
   }
   ```
2. Optimistic Concurrency implementieren

---

## 📦 Deployment-Probleme

### Problem: Single-File Executable startet nicht
**Symptome**: "The application to execute does not exist"

**Lösung**:
1. Publish-Befehl korrigieren:
   ```bash
   dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true
   ```
2. Antivirus-Ausnahme hinzufügen
3. Signatur prüfen:
   ```powershell
   Get-AuthenticodeSignature "Arbeitszeiterfassung.exe"
   ```

### Problem: "Missing .NET Runtime"
**Symptome**: Trotz self-contained wird Runtime verlangt

**Lösung**:
1. Runtime-Identifier explizit setzen:
   ```xml
   <PropertyGroup>
     <RuntimeIdentifier>win-x64</RuntimeIdentifier>
     <SelfContained>true</SelfContained>
     <PublishSingleFile>true</PublishSingleFile>
     <IncludeAllContentForSelfExtract>true</IncludeAllContentForSelfExtract>
   </PropertyGroup>
   ```

### Problem: Installer schlägt fehl mit "2869 Error"
**Symptome**: MSI-Installation bricht ab

**Lösung**:
1. Als Administrator ausführen
2. Windows Installer neu registrieren:
   ```cmd
   msiexec /unregister
   msiexec /regserver
   ```
3. Installer-Log prüfen:
   ```cmd
   msiexec /i "Setup.msi" /l*v install.log
   ```

---

## ⚠️ Laufzeitfehler

### Problem: "Configuration section not found"
**Symptome**: ConfigurationException beim Start

**Lösung**:
1. appsettings.json als "Copy Always" markieren:
   ```xml
   <Content Include="appsettings.json">
     <CopyToOutputDirectory>Always</CopyToOutputDirectory>
   </Content>
   ```
2. Embedded Resource Alternative:
   ```csharp
   var assembly = Assembly.GetExecutingAssembly();
   using var stream = assembly.GetManifestResourceStream("Arbeitszeiterfassung.UI.appsettings.json");
   ```

### Problem: IP-Validierung schlägt immer fehl
**Symptome**: "IP nicht autorisiert" trotz korrekter Konfiguration

**Lösung**:
1. IP-Range-Berechnung debuggen:
   ```csharp
   _logger.LogDebug($"Client IP: {clientIp}");
   _logger.LogDebug($"Checking against ranges: {string.Join(", ", ipRanges)}");
   ```
2. IPv4/IPv6 Kompatibilität:
   ```csharp
   var clientAddress = IPAddress.Parse(clientIp);
   if (clientAddress.IsIPv4MappedToIPv6)
       clientAddress = clientAddress.MapToIPv4();
   ```

### Problem: Memory Leak bei langer Laufzeit
**Symptome**: Speicherverbrauch steigt kontinuierlich

**Lösung**:
1. Event-Handler korrekt entfernen:
   ```csharp
   public void Dispose()
   {
       timer.Tick -= Timer_Tick; // Wichtig!
       timer.Dispose();
   }
   ```
2. DbContext-Lifetime begrenzen:
   ```csharp
   services.AddDbContext<ArbeitszeitDbContext>(options =>
   {
       options.UseMySql(connectionString);
   }, ServiceLifetime.Scoped); // Nicht Singleton!
   ```

---

## 🆘 Notfall-Checkliste

### Wenn gar nichts mehr geht:

1. **Logs prüfen**:
   - Windows Event Log
   - `%LOCALAPPDATA%\Arbeitszeiterfassung\logs\`
   - MySQL Error Log

2. **Clean Rebuild**:
   ```bash
   dotnet clean
   rd /s /q bin obj
   dotnet restore
   dotnet build
   ```

3. **Datenbank zurücksetzen**:
   ```sql
   DROP DATABASE IF EXISTS db10454681_aze;
   CREATE DATABASE db10454681_aze;
   -- Migrations neu ausführen
   ```

4. **Support kontaktieren** mit:
   - Vollständiger Fehlermeldung
   - Stack Trace
   - Reproduktionsschritte
   - Systemumgebung (OS, .NET Version)

---

## 📞 Support-Kontakte

- **Entwickler-Forum**: [Link zum internen Forum]
- **E-Mail**: entwicklung@mikropartner.de
- **Ticket-System**: [Link zum Ticket-System]
- **Notfall-Hotline**: [Nummer nur für kritische Produktionsfehler]

---

**Tipp**: Führen Sie ein Fehlerprotokoll! Dokumentierte Lösungen helfen dem ganzen Team.
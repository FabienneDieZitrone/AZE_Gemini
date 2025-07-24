---
title: Schritt 6.3 - Deployment-Paket erstellen
version: 1.0
lastUpdated: 26.01.2025
author: Tanja Trella
status: Final
file: /app/AZE/Prompts/Schritt_6_3_Deployment_Paket_erstellen.md
description: Detaillierter Prompt für die Erstellung eines produktionsreifen Deployment-Pakets
---

# Schritt 6.3: Deployment-Paket erstellen

## Kontext
Du bist mein erfahrener C#/.NET-Entwickler und arbeitest an einem Arbeitszeiterfassungssystem. Die Anwendung ist vollständig entwickelt und getestet. Jetzt soll ein produktionsreifes Deployment-Paket erstellt werden, das eine einfache Installation beim Kunden ermöglicht.

## Aufgabe
Erstelle ein vollständiges Deployment-Paket mit Single-File-Executable, automatisiertem Installer, Dokumentation und allen notwendigen Konfigurationsdateien für eine reibungslose Installation und Inbetriebnahme.

## Anforderungen

### 1. Build-Konfiguration (Arbeitszeiterfassung.UI/)
```xml
<!-- Arbeitszeiterfassung.UI.csproj -->
<Project Sdk="Microsoft.NET.Sdk">
  <PropertyGroup>
    <OutputType>WinExe</OutputType>
    <TargetFramework>net8.0-windows</TargetFramework>
    <UseWindowsForms>true</UseWindowsForms>
    <ApplicationIcon>Resources\app.ico</ApplicationIcon>
    
    <!-- Single-File Publishing -->
    <PublishSingleFile>true</PublishSingleFile>
    <SelfContained>true</SelfContained>
    <RuntimeIdentifier>win-x64</RuntimeIdentifier>
    <PublishReadyToRun>true</PublishReadyToRun>
    <PublishTrimmed>true</PublishTrimmed>
    <IncludeNativeLibrariesForSelfExtract>true</IncludeNativeLibrariesForSelfExtract>
    
    <!-- Versionierung -->
    <AssemblyVersion>1.0.0.0</AssemblyVersion>
    <FileVersion>1.0.0.0</FileVersion>
    <ProductVersion>1.0.0</ProductVersion>
    
    <!-- Produktinformationen -->
    <Product>Arbeitszeiterfassung</Product>
    <Company>Mikropartner</Company>
    <Copyright>Copyright © Mikropartner 2025</Copyright>
    <Description>Arbeitszeiterfassungssystem für Bildungsträger</Description>
  </PropertyGroup>

  <!-- Embedded Resources -->
  <ItemGroup>
    <EmbeddedResource Include="Resources\**\*" />
    <Content Include="appsettings.json">
      <CopyToOutputDirectory>Always</CopyToOutputDirectory>
    </Content>
  </ItemGroup>

  <!-- Code-Signing -->
  <PropertyGroup Condition="'$(Configuration)' == 'Release'">
    <SignAssembly>true</SignAssembly>
    <AssemblyOriginatorKeyFile>..\..\signing\Arbeitszeiterfassung.snk</AssemblyOriginatorKeyFile>
    <DelaySign>false</DelaySign>
  </PropertyGroup>
</Project>
```

### 2. Publish-Profile (Properties/PublishProfiles/)
```xml
<!-- Production.pubxml -->
<?xml version="1.0" encoding="utf-8"?>
<Project ToolsVersion="4.0" xmlns="http://schemas.microsoft.com/developer/msbuild/2003">
  <PropertyGroup>
    <Configuration>Release</Configuration>
    <Platform>x64</Platform>
    <PublishDir>..\..\publish\</PublishDir>
    <PublishProtocol>FileSystem</PublishProtocol>
    <TargetFramework>net8.0-windows</TargetFramework>
    <RuntimeIdentifier>win-x64</RuntimeIdentifier>
    <SelfContained>true</SelfContained>
    <PublishSingleFile>true</PublishSingleFile>
    <PublishReadyToRun>true</PublishReadyToRun>
    <PublishTrimmed>true</PublishTrimmed>
    
    <!-- Optimierungen -->
    <DebugType>none</DebugType>
    <DebugSymbols>false</DebugSymbols>
    <EnableCompressionInSingleFile>true</EnableCompressionInSingleFile>
    
    <!-- Trimming-Optionen -->
    <TrimMode>link</TrimMode>
    <TrimmerRootAssembly Include="Arbeitszeiterfassung.UI" />
    <TrimmerRootAssembly Include="Arbeitszeiterfassung.BLL" />
    <TrimmerRootAssembly Include="Arbeitszeiterfassung.DAL" />
  </PropertyGroup>
</Project>
```

### 3. Build-Script (build-release.ps1)
```powershell
# build-release.ps1
param(
    [Parameter(Mandatory=$true)]
    [string]$Version,
    
    [string]$CertificatePath = "",
    [string]$CertificatePassword = "",
    [switch]$SkipTests
)

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Arbeitszeiterfassung Release Build" -ForegroundColor Cyan
Write-Host "Version: $Version" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan

# Setze Arbeitsverzeichnis
$rootDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $rootDir

# Clean previous builds
Write-Host "`nCleaning previous builds..." -ForegroundColor Yellow
Remove-Item -Path "publish" -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item -Path "release" -Recurse -Force -ErrorAction SilentlyContinue

# Update version numbers
Write-Host "`nUpdating version to $Version..." -ForegroundColor Yellow
$csprojFiles = Get-ChildItem -Recurse -Filter "*.csproj"
foreach ($file in $csprojFiles) {
    $content = Get-Content $file.FullName -Raw
    $content = $content -replace '<AssemblyVersion>.*</AssemblyVersion>', "<AssemblyVersion>$Version.0</AssemblyVersion>"
    $content = $content -replace '<FileVersion>.*</FileVersion>', "<FileVersion>$Version.0</FileVersion>"
    $content = $content -replace '<ProductVersion>.*</ProductVersion>', "<ProductVersion>$Version</ProductVersion>"
    Set-Content -Path $file.FullName -Value $content
}

# Run tests (unless skipped)
if (-not $SkipTests) {
    Write-Host "`nRunning tests..." -ForegroundColor Yellow
    dotnet test --configuration Release --no-build
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Tests failed! Aborting build." -ForegroundColor Red
        exit 1
    }
    Write-Host "All tests passed!" -ForegroundColor Green
}

# Build release
Write-Host "`nBuilding release..." -ForegroundColor Yellow
dotnet publish Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj `
    --configuration Release `
    --runtime win-x64 `
    --self-contained true `
    -p:PublishSingleFile=true `
    -p:PublishReadyToRun=true `
    -p:PublishTrimmed=true `
    -p:IncludeNativeLibrariesForSelfExtract=true `
    -p:EnableCompressionInSingleFile=true `
    --output publish

if ($LASTEXITCODE -ne 0) {
    Write-Host "Build failed!" -ForegroundColor Red
    exit 1
}

# Sign executable (if certificate provided)
if ($CertificatePath) {
    Write-Host "`nSigning executable..." -ForegroundColor Yellow
    & signtool sign /f "$CertificatePath" /p "$CertificatePassword" `
        /t http://timestamp.digicert.com `
        /d "Arbeitszeiterfassung" `
        "publish\Arbeitszeiterfassung.UI.exe"
}

# Create release directory structure
Write-Host "`nCreating release package..." -ForegroundColor Yellow
$releaseDir = "release\Arbeitszeiterfassung_v$Version"
New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null
New-Item -ItemType Directory -Path "$releaseDir\Config" -Force | Out-Null
New-Item -ItemType Directory -Path "$releaseDir\Docs" -Force | Out-Null

# Copy files
Copy-Item "publish\Arbeitszeiterfassung.UI.exe" "$releaseDir\Arbeitszeiterfassung.exe"
Copy-Item "publish\appsettings.json" "$releaseDir\Config\appsettings.template.json"

# Copy documentation
Copy-Item "docs\Benutzerhandbuch.pdf" "$releaseDir\Docs\"
Copy-Item "docs\Administratorhandbuch.pdf" "$releaseDir\Docs\"
Copy-Item "docs\Installationsanleitung.pdf" "$releaseDir\Docs\"
Copy-Item "LICENSE.txt" "$releaseDir\"
Copy-Item "CHANGELOG.md" "$releaseDir\"

# Create installer config
@"
{
  "ProductName": "Arbeitszeiterfassung",
  "Version": "$Version",
  "Publisher": "Mikropartner",
  "InstallPath": "%ProgramFiles%\\Mikropartner\\Arbeitszeiterfassung",
  "CreateDesktopShortcut": true,
  "CreateStartMenuShortcut": true,
  "RequiredDotNetVersion": "8.0",
  "MinimumOSVersion": "10.0.17763.0"
}
"@ | Out-File "$releaseDir\installer-config.json"

Write-Host "`nRelease build completed successfully!" -ForegroundColor Green
Write-Host "Output: $releaseDir" -ForegroundColor Cyan
```

### 4. Installer-Erstellung (Installer/)
```csharp
// InstallerProject.wixproj - WiX Toolset v4
<?xml version="1.0" encoding="utf-8"?>
<Project Sdk="WixToolset.Sdk/4.0.0">
  <PropertyGroup>
    <OutputType>Package</OutputType>
    <DefineSolutionProperties>false</DefineSolutionProperties>
    <ProductVersion>1.0.0</ProductVersion>
    <OutputName>Arbeitszeiterfassung_Setup_$(ProductVersion)</OutputName>
  </PropertyGroup>
</Project>

// Product.wxs
<?xml version="1.0" encoding="UTF-8"?>
<Wix xmlns="http://wixtoolset.org/schemas/v4/wxs">
  <Product Id="*" 
           Name="Arbeitszeiterfassung" 
           Language="1031" 
           Version="$(var.ProductVersion)" 
           Manufacturer="Mikropartner" 
           UpgradeCode="12345678-1234-1234-1234-123456789012">
    
    <Package InstallerVersion="500" 
             Compressed="yes" 
             InstallScope="perMachine" 
             Platform="x64" />

    <MajorUpgrade DowngradeErrorMessage="Eine neuere Version ist bereits installiert." />
    
    <MediaTemplate EmbedCab="yes" />

    <!-- Features -->
    <Feature Id="ProductFeature" Title="Arbeitszeiterfassung" Level="1">
      <ComponentGroupRef Id="ProductComponents" />
      <ComponentGroupRef Id="ConfigComponents" />
      <ComponentGroupRef Id="DocumentationComponents" />
    </Feature>

    <!-- UI -->
    <UIRef Id="WixUI_InstallDir" />
    <Property Id="WIXUI_INSTALLDIR" Value="INSTALLFOLDER" />
    
    <!-- License -->
    <WixVariable Id="WixUILicenseRtf" Value="$(var.ProjectDir)License.rtf" />
    <WixVariable Id="WixUIBannerBmp" Value="$(var.ProjectDir)Resources\banner.bmp" />
    <WixVariable Id="WixUIDialogBmp" Value="$(var.ProjectDir)Resources\dialog.bmp" />

    <!-- Prerequisites -->
    <PropertyRef Id="NETFRAMEWORK45" />
    <Condition Message="Diese Anwendung benötigt .NET Framework 4.5 oder höher.">
      <![CDATA[Installed OR NETFRAMEWORK45]]>
    </Condition>
    
    <!-- Custom Actions -->
    <CustomAction Id="LaunchApplication" 
                  Directory="INSTALLFOLDER" 
                  ExeCommand="[INSTALLFOLDER]Arbeitszeiterfassung.exe" 
                  Execute="immediate" 
                  Impersonate="yes" 
                  Return="asyncNoWait" />
                  
    <InstallExecuteSequence>
      <Custom Action="LaunchApplication" After="InstallFinalize">NOT Installed</Custom>
    </InstallExecuteSequence>
  </Product>

  <!-- Directory Structure -->
  <Fragment>
    <Directory Id="TARGETDIR" Name="SourceDir">
      <Directory Id="ProgramFiles64Folder">
        <Directory Id="CompanyFolder" Name="Mikropartner">
          <Directory Id="INSTALLFOLDER" Name="Arbeitszeiterfassung">
            <Directory Id="ConfigFolder" Name="Config" />
            <Directory Id="DocsFolder" Name="Docs" />
          </Directory>
        </Directory>
      </Directory>
      
      <Directory Id="ProgramMenuFolder">
        <Directory Id="ApplicationProgramsFolder" Name="Arbeitszeiterfassung" />
      </Directory>
      
      <Directory Id="DesktopFolder" />
    </Directory>
  </Fragment>

  <!-- Components -->
  <Fragment>
    <ComponentGroup Id="ProductComponents" Directory="INSTALLFOLDER">
      <Component Id="MainExecutable" Guid="*">
        <File Id="ArbeitszeiterfassungExe" 
              Source="$(var.PublishDir)Arbeitszeiterfassung.exe" 
              KeyPath="yes">
          <Shortcut Id="ApplicationStartMenuShortcut" 
                    Directory="ApplicationProgramsFolder"
                    Name="Arbeitszeiterfassung"
                    Description="Arbeitszeiterfassung starten"
                    WorkingDirectory="INSTALLFOLDER"
                    Icon="AppIcon.exe" 
                    IconIndex="0" 
                    Advertise="yes" />
                    
          <Shortcut Id="DesktopShortcut"
                    Directory="DesktopFolder"
                    Name="Arbeitszeiterfassung"
                    Description="Arbeitszeiterfassung starten"
                    WorkingDirectory="INSTALLFOLDER"
                    Icon="AppIcon.exe" 
                    IconIndex="0" 
                    Advertise="yes" />
        </File>
      </Component>
      
      <Component Id="ApplicationShortcutCleanup" Guid="*">
        <RemoveFolder Id="ApplicationProgramsFolder" On="uninstall" />
        <RegistryValue Root="HKCU" 
                       Key="Software\Mikropartner\Arbeitszeiterfassung" 
                       Name="installed" 
                       Type="integer" 
                       Value="1" 
                       KeyPath="yes" />
      </Component>
    </ComponentGroup>
  </Fragment>
</Wix>
```

### 5. Konfigurations-Verwaltung (Config/)
```csharp
// ConfigurationInstaller.cs
public class ConfigurationInstaller
{
    private readonly string _installPath;
    
    public ConfigurationInstaller(string installPath)
    {
        _installPath = installPath;
    }
    
    public async Task ConfigureFirstRunAsync()
    {
        var configPath = Path.Combine(_installPath, "Config");
        
        // Kopiere Templates wenn keine Configs existieren
        if (!File.Exists(Path.Combine(configPath, "appsettings.json")))
        {
            File.Copy(
                Path.Combine(configPath, "appsettings.template.json"),
                Path.Combine(configPath, "appsettings.json")
            );
        }
        
        // Konfigurationsassistent starten
        var configWizard = new ConfigurationWizard();
        var config = await configWizard.RunAsync();
        
        // Speichere Konfiguration
        await SaveConfigurationAsync(config);
        
        // Erstelle Datenbankschema
        if (config.CreateDatabase)
        {
            await CreateDatabaseSchemaAsync(config.DatabaseConnection);
        }
    }
    
    private async Task CreateDatabaseSchemaAsync(string connectionString)
    {
        var optionsBuilder = new DbContextOptionsBuilder<ArbeitszeitDbContext>();
        
        if (connectionString.Contains("Server=", StringComparison.OrdinalIgnoreCase))
        {
            optionsBuilder.UseMySql(connectionString, ServerVersion.AutoDetect(connectionString));
        }
        else
        {
            optionsBuilder.UseSqlite(connectionString);
        }
        
        using var context = new ArbeitszeitDbContext(optionsBuilder.Options);
        await context.Database.MigrateAsync();
        
        // Seed initial data
        await SeedInitialDataAsync(context);
    }
}

// ConfigurationWizard.cs - WinForms Wizard
public partial class ConfigurationWizard : Form
{
    private int _currentStep = 0;
    private readonly List<IWizardStep> _steps;
    
    public ConfigurationWizard()
    {
        InitializeComponent();
        
        _steps = new List<IWizardStep>
        {
            new WelcomeStep(),
            new DatabaseConfigStep(),
            new StandortConfigStep(),
            new AdminUserStep(),
            new SummaryStep()
        };
        
        LoadStep(0);
    }
    
    private void btnNext_Click(object sender, EventArgs e)
    {
        if (!_steps[_currentStep].Validate())
            return;
        
        if (_currentStep < _steps.Count - 1)
        {
            _currentStep++;
            LoadStep(_currentStep);
        }
        else
        {
            // Finish
            DialogResult = DialogResult.OK;
            Close();
        }
    }
}
```

### 6. Update-Mechanismus (Updater/)
```csharp
// AutoUpdater.cs
public class AutoUpdater
{
    private readonly string _updateUrl;
    private readonly Version _currentVersion;
    
    public async Task<UpdateInfo> CheckForUpdatesAsync()
    {
        try
        {
            using var client = new HttpClient();
            var response = await client.GetStringAsync($"{_updateUrl}/version.json");
            var latestVersion = JsonSerializer.Deserialize<UpdateInfo>(response);
            
            if (Version.Parse(latestVersion.Version) > _currentVersion)
            {
                return latestVersion;
            }
            
            return null;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Fehler beim Prüfen auf Updates");
            return null;
        }
    }
    
    public async Task DownloadAndInstallUpdateAsync(UpdateInfo updateInfo)
    {
        var tempPath = Path.Combine(Path.GetTempPath(), "AZE_Update");
        Directory.CreateDirectory(tempPath);
        
        try
        {
            // Download
            using var client = new HttpClient();
            var data = await client.GetByteArrayAsync(updateInfo.DownloadUrl);
            
            var setupPath = Path.Combine(tempPath, "setup.exe");
            await File.WriteAllBytesAsync(setupPath, data);
            
            // Verify signature
            if (!VerifySignature(setupPath, updateInfo.Signature))
            {
                throw new SecurityException("Update-Signatur ungültig!");
            }
            
            // Start installer
            Process.Start(new ProcessStartInfo
            {
                FileName = setupPath,
                Arguments = "/silent /update",
                UseShellExecute = true
            });
            
            // Close current application
            Application.Exit();
        }
        finally
        {
            Directory.Delete(tempPath, true);
        }
    }
}
```

### 7. Deployment-Dokumentation (Docs/)
```markdown
# Installationsanleitung

## Systemanforderungen

### Minimum:
- Windows 10 Version 1809 (Build 17763) oder höher
- 4 GB RAM
- 500 MB freier Festplattenspeicher
- .NET 8.0 Runtime (wird automatisch installiert)
- Bildschirmauflösung: 1024x768

### Empfohlen:
- Windows 11
- 8 GB RAM
- 1 GB freier Festplattenspeicher
- Bildschirmauflösung: 1920x1080

### Netzwerk:
- MySQL/MariaDB Server (Version 5.7+ / 10.3+)
- Stabile Netzwerkverbindung für Online-Funktionen
- Firewall-Freigabe für MySQL-Port (Standard: 3306)

## Installation

### 1. Vorbereitung
1. Stellen Sie sicher, dass alle Systemanforderungen erfüllt sind
2. Sichern Sie ggf. vorhandene Daten einer älteren Version
3. Schließen Sie alle laufenden Instanzen der Anwendung

### 2. Installation durchführen
1. Führen Sie `Arbeitszeiterfassung_Setup_1.0.0.exe` als Administrator aus
2. Folgen Sie dem Installationsassistenten:
   - Akzeptieren Sie die Lizenzbedingungen
   - Wählen Sie das Installationsverzeichnis
   - Wählen Sie die zu installierenden Komponenten

### 3. Erstkonfiguration
Nach der Installation startet automatisch der Konfigurationsassistent:

1. **Datenbankverbindung**
   - Server: Hostname oder IP des MySQL-Servers
   - Port: Standard 3306
   - Datenbank: Name der Datenbank (wird erstellt falls nicht vorhanden)
   - Benutzer/Passwort: MySQL-Zugangsdaten

2. **Standorte konfigurieren**
   - Definieren Sie die Standorte Ihrer Organisation
   - Geben Sie die erlaubten IP-Bereiche an

3. **Administrator-Account**
   - Erstellen Sie den ersten Administrator-Benutzer
   - Merken Sie sich die Zugangsdaten!

### 4. Firewall-Konfiguration
Die Anwendung benötigt folgende Firewall-Freigaben:
- Ausgehend: MySQL-Port (Standard: 3306)
- Ausgehend: HTTPS (Port 443) für Updates

## Silent Installation

Für automatisierte Deployments:

```cmd
Arbeitszeiterfassung_Setup_1.0.0.exe /S /CONFIG="config.json"
```

Beispiel config.json:
```json
{
  "InstallPath": "C:\\Program Files\\Mikropartner\\Arbeitszeiterfassung",
  "CreateDesktopShortcut": true,
  "Database": {
    "Server": "db-server.local",
    "Database": "arbeitszeiterfassung",
    "Username": "aze_user",
    "Password": "encrypted:..."
  }
}
```
```

### 8. Post-Deployment Scripts
```powershell
# post-deploy-checks.ps1
param(
    [Parameter(Mandatory=$true)]
    [string]$InstallPath
)

Write-Host "Running post-deployment checks..." -ForegroundColor Cyan

$errors = @()

# Check 1: Executable exists
if (-not (Test-Path "$InstallPath\Arbeitszeiterfassung.exe")) {
    $errors += "Executable not found"
}

# Check 2: Config files exist
$requiredConfigs = @("appsettings.json")
foreach ($config in $requiredConfigs) {
    if (-not (Test-Path "$InstallPath\Config\$config")) {
        $errors += "Config file missing: $config"
    }
}

# Check 3: Database connectivity
try {
    $configJson = Get-Content "$InstallPath\Config\appsettings.json" | ConvertFrom-Json
    $connectionString = $configJson.DatabaseSettings.ConnectionString
    
    # Test connection (simplified)
    $testResult = & "$InstallPath\Arbeitszeiterfassung.exe" --test-db
    if ($LASTEXITCODE -ne 0) {
        $errors += "Database connection failed"
    }
}
catch {
    $errors += "Failed to test database: $_"
}

# Check 4: Required permissions
$requiredPaths = @(
    "$env:LOCALAPPDATA\Arbeitszeiterfassung",
    "$env:APPDATA\Arbeitszeiterfassung"
)

foreach ($path in $requiredPaths) {
    try {
        New-Item -ItemType Directory -Path $path -Force | Out-Null
        $testFile = "$path\test.tmp"
        "test" | Out-File $testFile
        Remove-Item $testFile
    }
    catch {
        $errors += "No write permission: $path"
    }
}

# Report results
if ($errors.Count -eq 0) {
    Write-Host "All checks passed!" -ForegroundColor Green
    exit 0
}
else {
    Write-Host "Deployment checks failed:" -ForegroundColor Red
    $errors | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
}
```

## Erwartete Ergebnisse

1. **Single-File Executable** (~50-80 MB) mit allen Dependencies
2. **MSI-Installer** mit Wizard und Silent-Install-Option
3. **Automatische Updates** über integrierten Updater
4. **Konfigurationsassistent** für Ersteinrichtung
5. **Vollständige Dokumentation** (PDF) im Paket
6. **Deployment-Skripte** für automatisierte Installation

## Zusätzliche Hinweise
- Signiere alle Executables mit Code-Signing-Zertifikat
- Teste Installation auf verschiedenen Windows-Versionen
- Erstelle Backup-Mechanismus für Updates
- Implementiere Rollback-Funktion bei fehlgeschlagenen Updates
- Dokumentiere alle Konfigurationsoptionen

## Deployment-Checkliste
- [ ] Version-Nummer aktualisiert
- [ ] CHANGELOG.md aktualisiert
- [ ] Alle Tests erfolgreich
- [ ] Code-Signing durchgeführt
- [ ] Installer auf Testumgebung geprüft
- [ ] Dokumentation aktualisiert
- [ ] Update-Server vorbereitet
- [ ] Release-Notes erstellt
# ---
# title: Projekt-Initialisierungs-Skript (PowerShell) - Korrigiert
# version: 1.1
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/init-projekt-fixed.ps1
# description: Korrigiertes PowerShell-Skript zur Initialisierung des Arbeitszeiterfassungsprojekts
# ---

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Arbeitszeiterfassung Projekt Setup" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Prüfe ob .NET 8.0 installiert ist
Write-Host "Prüfe .NET SDK Version..." -ForegroundColor Yellow
try {
    $dotnetVersion = dotnet --version
    Write-Host "✓ .NET SDK Version: $dotnetVersion" -ForegroundColor Green
}
catch {
    Write-Host "❌ FEHLER: .NET SDK ist nicht installiert!" -ForegroundColor Red
    Write-Host "Bitte installieren Sie .NET 8.0 SDK von: https://dotnet.microsoft.com/download/dotnet/8.0" -ForegroundColor Yellow
    exit 1
}

# Basis-Verzeichnis - verwende aktuelles Verzeichnis
$baseDir = Join-Path (Get-Location) "Arbeitszeiterfassung"

# Erstelle Projektstruktur
Write-Host ""
Write-Host "Erstelle Projektstruktur..." -ForegroundColor Yellow
if (!(Test-Path $baseDir)) {
    New-Item -ItemType Directory -Path $baseDir -Force | Out-Null
}
Set-Location $baseDir

# Erstelle Solution
Write-Host "Erstelle Solution..." -ForegroundColor Yellow
dotnet new sln -n Arbeitszeiterfassung

# Erstelle Projekte
Write-Host ""
Write-Host "Erstelle Projekte..." -ForegroundColor Yellow

# Common Library
Write-Host "- Arbeitszeiterfassung.Common" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.Common -f net8.0
dotnet sln add Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj

# Data Access Layer
Write-Host "- Arbeitszeiterfassung.DAL" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.DAL -f net8.0
dotnet sln add Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj

# Business Logic Layer
Write-Host "- Arbeitszeiterfassung.BLL" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.BLL -f net8.0
dotnet sln add Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj

# User Interface (Windows Forms)
Write-Host "- Arbeitszeiterfassung.UI" -ForegroundColor Cyan
dotnet new winforms -n Arbeitszeiterfassung.UI -f net8.0-windows
dotnet sln add Arbeitszeiterfassung.UI\Arbeitszeiterfassung.UI.csproj

# Test-Projekte
Write-Host "- Arbeitszeiterfassung.Tests" -ForegroundColor Cyan
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests\Arbeitszeiterfassung.Tests.csproj

# Erstelle Projektverweise
Write-Host ""
Write-Host "Erstelle Projektverweise..." -ForegroundColor Yellow

# DAL referenziert Common
Set-Location Arbeitszeiterfassung.DAL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
Set-Location ..

# BLL referenziert Common und DAL
Set-Location Arbeitszeiterfassung.BLL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
Set-Location ..

# UI referenziert alle anderen
Set-Location Arbeitszeiterfassung.UI
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
Set-Location ..

# Tests referenziert alle anderen
Set-Location Arbeitszeiterfassung.Tests
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
Set-Location ..

# Installiere NuGet-Pakete
Write-Host ""
Write-Host "Installiere NuGet-Pakete..." -ForegroundColor Yellow

# Entity Framework Core für DAL
Set-Location Arbeitszeiterfassung.DAL
dotnet add package Microsoft.EntityFrameworkCore -v 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Sqlite -v 8.0.0
dotnet add package Pomelo.EntityFrameworkCore.MySql -v 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Tools -v 8.0.0
Set-Location ..

# Configuration für Common
Set-Location Arbeitszeiterfassung.Common
dotnet add package Microsoft.Extensions.Configuration -v 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json -v 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Binder -v 8.0.0
dotnet add package Microsoft.Extensions.Logging -v 8.0.0
dotnet add package System.Security.Cryptography.ProtectedData -v 8.0.0
Set-Location ..

# Windows Forms Erweiterungen für UI
Set-Location Arbeitszeiterfassung.UI
dotnet add package Microsoft.Extensions.DependencyInjection -v 8.0.0
dotnet add package Microsoft.Extensions.Hosting -v 8.0.0
Set-Location ..

# Test-Pakete
Set-Location Arbeitszeiterfassung.Tests
dotnet add package Microsoft.NET.Test.Sdk -v 17.11.1
dotnet add package xunit -v 2.9.2
dotnet add package xunit.runner.visualstudio -v 2.8.2
dotnet add package Moq -v 4.20.72
dotnet add package FluentAssertions -v 6.12.1
Set-Location ..

# Erstelle Ordnerstruktur in den Projekten
Write-Host ""
Write-Host "Erstelle Ordnerstruktur..." -ForegroundColor Yellow

# Common
$commonFolders = @("Configuration", "Enums", "Extensions", "Helpers", "Models")
foreach ($folder in $commonFolders) {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.Common\$folder" -Force | Out-Null
}

# DAL
$dalFolders = @("Context", "Entities", "Migrations", "Repositories")
foreach ($folder in $dalFolders) {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.DAL\$folder" -Force | Out-Null
}

# BLL
$bllFolders = @("Services", "Validators", "Interfaces", "DTOs")
foreach ($folder in $bllFolders) {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.BLL\$folder" -Force | Out-Null
}

# UI
$uiFolders = @("Forms", "Controls", "Resources", "Helpers")
foreach ($folder in $uiFolders) {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.UI\$folder" -Force | Out-Null
}

# Erstelle Konfigurationsdateien
Write-Host ""
Write-Host "Erstelle Konfigurationsdateien..." -ForegroundColor Yellow

# appsettings.json (als separate Datei erstellen)
$appsettingsContent = @'
{
  "ApplicationSettings": {
    "ApplicationName": "Arbeitszeiterfassung Mikropartner",
    "Version": "1.0.0",
    "LogLevel": "Information",
    "SessionTimeout": 30,
    "EnableOfflineMode": true
  },
  "DatabaseSettings": {
    "Provider": "MySQL",
    "ConnectionString": "server=wp10454681.Server-he.de;database=db10454681-aze;uid=db10454681-aze;pwd=Start.321;",
    "CommandTimeout": 30,
    "EnableSensitiveDataLogging": false
  },
  "OfflineDatabase": {
    "Provider": "SQLite",
    "DatabasePath": "%LOCALAPPDATA%\\Arbeitszeiterfassung\\offline.db",
    "SyncInterval": 300
  }
}
'@

$appsettingsContent | Out-File -FilePath "Arbeitszeiterfassung.UI\appsettings.json" -Encoding UTF8


# Erstelle .gitignore
$gitignoreContent = @'
## Ignore Visual Studio temporary files, build results, and
## files generated by popular Visual Studio add-ons.

# User-specific files
*.rsuser
*.suo
*.user
*.userosscache
*.sln.docstates

# Build results
[Dd]ebug/
[Dd]ebugPublic/
[Rr]elease/
[Rr]eleases/
x64/
x86/
[Ww][Ii][Nn]32/
[Aa][Rr][Mm]/
[Aa][Rr][Mm]64/
bld/
[Bb]in/
[Oo]bj/
[Ll]og/
[Ll]ogs/

# Visual Studio cache/options directory
.vs/

# .NET
project.lock.json
project.fragment.lock.json
artifacts/

# NuGet Packages
*.nupkg
*.snupkg
**/packages/*
!**/packages/build/

# SQLite Datenbanken
*.db
*.db-shm
*.db-wal

# Konfigurationsdateien mit sensiblen Daten
appsettings.Production.json
appsettings.Development.json
'@

$gitignoreContent | Out-File -FilePath ".gitignore" -Encoding UTF8

# Build Solution
Write-Host ""
Write-Host "Baue Solution..." -ForegroundColor Yellow
dotnet build

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "======================================" -ForegroundColor Green
    Write-Host "✓ Projekt wurde erfolgreich erstellt!" -ForegroundColor Green
    Write-Host "======================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Projektverzeichnis: $baseDir" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Nächste Schritte:" -ForegroundColor Yellow
    Write-Host "1. Öffnen Sie die Solution in Visual Studio oder VS Code" -ForegroundColor White
    Write-Host "2. Führen Sie Schritt 1.2 aus: Datenbankdesign und Entity-Modelle" -ForegroundColor White
    Write-Host "3. Verwenden Sie den Prompt: ..\Prompts\Schritt_1_2_Datenbankdesign.md" -ForegroundColor White
    Write-Host ""
    Write-Host "4. README.md im Projektstamm bei relevanten Änderungen aktualisieren" -ForegroundColor White
    Write-Host "Zum Öffnen in Visual Studio:" -ForegroundColor Yellow
    Write-Host "  cd '$baseDir'" -ForegroundColor White
    Write-Host "  Start-Process 'Arbeitszeiterfassung.sln'" -ForegroundColor White
}
else {
    Write-Host ""
    Write-Host "❌ Build fehlgeschlagen!" -ForegroundColor Red
    Write-Host "Prüfen Sie die Ausgabe für Details." -ForegroundColor Yellow
    exit 1
}
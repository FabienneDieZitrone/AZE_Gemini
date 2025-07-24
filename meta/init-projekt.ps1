# ---
# title: Projekt-Initialisierungs-Skript (PowerShell)
# version: 1.0
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/init-projekt.ps1
# description: PowerShell-Skript zur Initialisierung des Arbeitszeiterfassungsprojekts
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

# Basis-Verzeichnis
$baseDir = "C:\Projekte\Arbeitszeiterfassung"

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
dotnet add package Pomelo.EntityFrameworkCore.MySql -v 8.0.0-preview.1
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
@("Configuration", "Enums", "Extensions", "Helpers", "Models") | ForEach-Object {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.Common\$_" -Force | Out-Null
}

# DAL
@("Context", "Entities", "Migrations", "Repositories") | ForEach-Object {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.DAL\$_" -Force | Out-Null
}

# BLL
@("Services", "Validators", "Interfaces", "DTOs") | ForEach-Object {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.BLL\$_" -Force | Out-Null
}

# UI
@("Forms", "Controls", "Resources", "Helpers") | ForEach-Object {
    New-Item -ItemType Directory -Path "Arbeitszeiterfassung.UI\$_" -Force | Out-Null
}

# Erstelle Konfigurationsdateien
Write-Host ""
Write-Host "Erstelle Konfigurationsdateien..." -ForegroundColor Yellow

# appsettings.json
$appsettings = @'
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
$appsettings | Out-File -FilePath "Arbeitszeiterfassung.UI\appsettings.json" -Encoding UTF8


# Erstelle .gitignore
$gitignore = @'
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
$gitignore | Out-File -FilePath ".gitignore" -Encoding UTF8

# Build Solution
Write-Host ""
Write-Host "Baue Solution..." -ForegroundColor Yellow
dotnet build

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
Write-Host "3. Verwenden Sie den Prompt: /app/AZE/Prompts/Schritt_1_2_Datenbankdesign.md" -ForegroundColor White
Write-Host "4. README.md im Projektstamm bei relevanten Änderungen aktualisieren" -ForegroundColor White
Write-Host ""
Write-Host "Zum Öffnen in Visual Studio:" -ForegroundColor Yellow
Write-Host "Start-Process 'Arbeitszeiterfassung.sln'" -ForegroundColor White
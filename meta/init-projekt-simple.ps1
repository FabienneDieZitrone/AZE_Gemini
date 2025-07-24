# Arbeitszeiterfassung Projekt Setup (Vereinfacht)
# Version: 1.2
# Author: Tanja Trella

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Arbeitszeiterfassung Projekt Setup" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Prüfe .NET SDK
Write-Host "Prüfe .NET SDK..." -ForegroundColor Yellow
try {
    $dotnetVersion = dotnet --version
    Write-Host "✓ .NET SDK Version: $dotnetVersion" -ForegroundColor Green
}
catch {
    Write-Host "❌ FEHLER: .NET SDK nicht gefunden!" -ForegroundColor Red
    Write-Host "Installation: winget install Microsoft.DotNet.SDK.8" -ForegroundColor Yellow
    exit 1
}

# Basis-Verzeichnis
$baseDir = Join-Path (Get-Location) "Arbeitszeiterfassung"
Write-Host "Projektverzeichnis: $baseDir" -ForegroundColor Cyan

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

Write-Host "- Common Library" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.Common -f net8.0
dotnet sln add Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj

Write-Host "- Data Access Layer" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.DAL -f net8.0
dotnet sln add Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj

Write-Host "- Business Logic Layer" -ForegroundColor Cyan
dotnet new classlib -n Arbeitszeiterfassung.BLL -f net8.0
dotnet sln add Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj

Write-Host "- User Interface" -ForegroundColor Cyan
dotnet new winforms -n Arbeitszeiterfassung.UI -f net8.0-windows
dotnet sln add Arbeitszeiterfassung.UI\Arbeitszeiterfassung.UI.csproj

Write-Host "- Tests" -ForegroundColor Cyan
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests\Arbeitszeiterfassung.Tests.csproj

# Projektverweise
Write-Host ""
Write-Host "Erstelle Projektverweise..." -ForegroundColor Yellow

Set-Location Arbeitszeiterfassung.DAL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
Set-Location ..

Set-Location Arbeitszeiterfassung.BLL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
Set-Location ..

Set-Location Arbeitszeiterfassung.UI
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
Set-Location ..

Set-Location Arbeitszeiterfassung.Tests
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
Set-Location ..

# NuGet-Pakete
Write-Host ""
Write-Host "Installiere NuGet-Pakete..." -ForegroundColor Yellow

Write-Host "- Entity Framework für DAL" -ForegroundColor Gray
Set-Location Arbeitszeiterfassung.DAL
dotnet add package Microsoft.EntityFrameworkCore --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Sqlite --version 8.0.0
dotnet add package Pomelo.EntityFrameworkCore.MySql --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Tools --version 8.0.0
Set-Location ..

Write-Host "- Configuration für Common" -ForegroundColor Gray
Set-Location Arbeitszeiterfassung.Common
dotnet add package Microsoft.Extensions.Configuration --version 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json --version 8.0.0
dotnet add package Microsoft.Extensions.Logging --version 8.0.0
Set-Location ..

Write-Host "- DI Container für UI" -ForegroundColor Gray
Set-Location Arbeitszeiterfassung.UI
dotnet add package Microsoft.Extensions.DependencyInjection --version 8.0.0
dotnet add package Microsoft.Extensions.Hosting --version 8.0.0
Set-Location ..

Write-Host "- Test-Pakete" -ForegroundColor Gray
Set-Location Arbeitszeiterfassung.Tests
dotnet add package Microsoft.NET.Test.Sdk --version 17.11.1
dotnet add package xunit --version 2.9.2
dotnet add package xunit.runner.visualstudio --version 2.8.2
dotnet add package Moq --version 4.20.72
dotnet add package FluentAssertions --version 6.12.1
Set-Location ..

# Ordnerstruktur
Write-Host ""
Write-Host "Erstelle Ordnerstruktur..." -ForegroundColor Yellow

# Common
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.Common\Configuration" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.Common\Enums" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.Common\Models" -Force | Out-Null

# DAL
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.DAL\Context" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.DAL\Entities" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.DAL\Repositories" -Force | Out-Null

# BLL
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.BLL\Services" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.BLL\DTOs" -Force | Out-Null

# UI
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.UI\Forms" -Force | Out-Null
New-Item -ItemType Directory -Path "Arbeitszeiterfassung.UI\Controls" -Force | Out-Null

# Konfigurationsdateien
Write-Host ""
Write-Host "Erstelle Konfigurationsdateien..." -ForegroundColor Yellow

# appsettings.json (PowerShell-freundlich)
$appsettings = [PSCustomObject]@{
    ApplicationSettings = [PSCustomObject]@{
        ApplicationName = "Arbeitszeiterfassung Mikropartner"
        Version = "1.0.0"
        LogLevel = "Information"
        SessionTimeout = 30
        EnableOfflineMode = $true
    }
    DatabaseSettings = [PSCustomObject]@{
        Provider = "MySQL"
        ConnectionString = "server=wp10454681.Server-he.de;database=db10454681-aze;uid=db10454681-aze;pwd=Start.321;"
        CommandTimeout = 30
        EnableSensitiveDataLogging = $false
    }
    OfflineDatabase = [PSCustomObject]@{
        Provider = "SQLite"
        DatabasePath = "%LOCALAPPDATA%\Arbeitszeiterfassung\offline.db"
        SyncInterval = 300
    }
}

$appsettings | ConvertTo-Json -Depth 3 | Out-File -FilePath "Arbeitszeiterfassung.UI\appsettings.json" -Encoding UTF8


# .gitignore (als Array)
$gitignoreLines = @(
    "# Visual Studio",
    "*.user",
    "*.suo",
    ".vs/",
    "",
    "# Build results",
    "[Dd]ebug/",
    "[Rr]elease/",
    "[Bb]in/",
    "[Oo]bj/",
    "",
    "# .NET",
    "*.log",
    "",
    "# Database",
    "*.db",
    "*.db-*",
    "",
    "# Config",
    "appsettings.Production.json",
    "appsettings.Development.json"
)

$gitignoreLines | Out-File -FilePath ".gitignore" -Encoding UTF8

# Build Test
Write-Host ""
Write-Host "Teste Build..." -ForegroundColor Yellow
$buildResult = dotnet build --verbosity quiet

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "======================================" -ForegroundColor Green
    Write-Host "✓ Projekt erfolgreich erstellt!" -ForegroundColor Green
    Write-Host "======================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Verzeichnis: $baseDir" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Nächste Schritte:" -ForegroundColor Yellow
    Write-Host "1. Visual Studio öffnen: Start-Process 'Arbeitszeiterfassung.sln'" -ForegroundColor White
    Write-Host "2. Schritt 1.2 ausführen (Datenbankdesign)" -ForegroundColor White
    Write-Host "3. Prompt verwenden: ..\Prompts\Schritt_1_2_Datenbankdesign.md" -ForegroundColor White
Write-Host "4. README.md im Projektstamm bei relevanten Änderungen aktualisieren" -ForegroundColor White
}
else {
    Write-Host "❌ Build-Fehler!" -ForegroundColor Red
    Write-Host "Führen Sie 'dotnet build' manuell aus für Details." -ForegroundColor Yellow
}
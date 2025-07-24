@echo off
REM ---
REM title: Projekt-Initialisierungs-Batch-Skript
REM version: 1.0
REM lastUpdated: 26.01.2025
REM author: Tanja Trella
REM status: Final
REM file: /app/AZE/init-projekt.bat
REM description: Einfaches Batch-Skript zur Projektinitialisierung
REM ---

echo ======================================
echo Arbeitszeiterfassung Projekt Setup
echo ======================================
echo.

REM Prüfe .NET SDK
echo Prüfe .NET SDK...
dotnet --version >nul 2>&1
if errorlevel 1 (
    echo FEHLER: .NET SDK nicht gefunden!
    echo Installation: winget install Microsoft.DotNet.SDK.8
    pause
    exit /b 1
)

echo ✓ .NET SDK gefunden
dotnet --version
echo.

REM Erstelle Projektverzeichnis
set PROJECT_DIR=%CD%\Arbeitszeiterfassung
echo Erstelle Projektverzeichnis: %PROJECT_DIR%
if not exist "%PROJECT_DIR%" mkdir "%PROJECT_DIR%"
cd /d "%PROJECT_DIR%"

REM Erstelle Solution
echo.
echo Erstelle Solution...
dotnet new sln -n Arbeitszeiterfassung

REM Erstelle Projekte
echo.
echo Erstelle Projekte...

echo - Common Library
dotnet new classlib -n Arbeitszeiterfassung.Common -f net8.0
dotnet sln add Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj

echo - Data Access Layer
dotnet new classlib -n Arbeitszeiterfassung.DAL -f net8.0
dotnet sln add Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj

echo - Business Logic Layer
dotnet new classlib -n Arbeitszeiterfassung.BLL -f net8.0
dotnet sln add Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj

echo - User Interface
dotnet new winforms -n Arbeitszeiterfassung.UI -f net8.0-windows
dotnet sln add Arbeitszeiterfassung.UI\Arbeitszeiterfassung.UI.csproj

echo - Tests
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests\Arbeitszeiterfassung.Tests.csproj

REM Projektverweise
echo.
echo Erstelle Projektverweise...

cd Arbeitszeiterfassung.DAL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
cd ..

cd Arbeitszeiterfassung.BLL
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
cd ..

cd Arbeitszeiterfassung.UI
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
cd ..

cd Arbeitszeiterfassung.Tests
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj
cd ..

REM NuGet-Pakete
echo.
echo Installiere NuGet-Pakete...

echo - Entity Framework für DAL
cd Arbeitszeiterfassung.DAL
dotnet add package Microsoft.EntityFrameworkCore --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Sqlite --version 8.0.0
dotnet add package Pomelo.EntityFrameworkCore.MySql --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Tools --version 8.0.0
cd ..

echo - Configuration für Common
cd Arbeitszeiterfassung.Common
dotnet add package Microsoft.Extensions.Configuration --version 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json --version 8.0.0
dotnet add package Microsoft.Extensions.Logging --version 8.0.0
cd ..

echo - DI Container für UI
cd Arbeitszeiterfassung.UI
dotnet add package Microsoft.Extensions.DependencyInjection --version 8.0.0
dotnet add package Microsoft.Extensions.Hosting --version 8.0.0
cd ..

echo - Test-Pakete
cd Arbeitszeiterfassung.Tests
dotnet add package Microsoft.NET.Test.Sdk --version 17.11.1
dotnet add package xunit --version 2.9.2
dotnet add package xunit.runner.visualstudio --version 2.8.2
dotnet add package Moq --version 4.20.72
dotnet add package FluentAssertions --version 6.12.1
cd ..

REM Ordnerstruktur
echo.
echo Erstelle Ordnerstruktur...

mkdir Arbeitszeiterfassung.Common\Configuration
mkdir Arbeitszeiterfassung.Common\Enums
mkdir Arbeitszeiterfassung.Common\Models

mkdir Arbeitszeiterfassung.DAL\Context
mkdir Arbeitszeiterfassung.DAL\Entities
mkdir Arbeitszeiterfassung.DAL\Repositories

mkdir Arbeitszeiterfassung.BLL\Services
mkdir Arbeitszeiterfassung.BLL\DTOs

mkdir Arbeitszeiterfassung.UI\Forms
mkdir Arbeitszeiterfassung.UI\Controls

REM Erstelle Konfigurationsdateien
echo.
echo Erstelle Konfigurationsdateien...

REM appsettings.json
(
echo {
echo   "ApplicationSettings": {
echo     "ApplicationName": "Arbeitszeiterfassung Mikropartner",
echo     "Version": "1.0.0",
echo     "LogLevel": "Information",
echo     "SessionTimeout": 30,
echo     "EnableOfflineMode": true
echo   },
echo   "DatabaseSettings": {
echo     "Provider": "MySQL",
echo     "ConnectionString": "server=wp10454681.Server-he.de;database=db10454681-aze;uid=db10454681-aze;pwd=Start.321;",
echo     "CommandTimeout": 30,
echo     "EnableSensitiveDataLogging": false
echo   },
echo   "OfflineDatabase": {
echo     "Provider": "SQLite",
echo     "DatabasePath": "%%LOCALAPPDATA%%\\Arbeitszeiterfassung\\offline.db",
echo     "SyncInterval": 300
echo   }
echo }
) > Arbeitszeiterfassung.UI\appsettings.json


REM .gitignore
(
echo # Visual Studio
echo *.user
echo *.suo
echo .vs/
echo.
echo # Build results
echo [Dd]ebug/
echo [Rr]elease/
echo [Bb]in/
echo [Oo]bj/
echo.
echo # .NET
echo *.log
echo.
echo # Database
echo *.db
echo *.db-*
echo.
echo # Config
echo appsettings.Production.json
echo appsettings.Development.json
) > .gitignore

REM Build Test
echo.
echo Teste Build...
dotnet build --verbosity minimal

if errorlevel 1 (
    echo.
    echo FEHLER beim Build!
    echo Führen Sie 'dotnet build' manuell aus für Details.
    pause
    exit /b 1
)

echo.
echo ======================================
echo ✓ Projekt erfolgreich erstellt!
echo ======================================
echo.
echo Projektverzeichnis: %PROJECT_DIR%
echo.
echo Nächste Schritte:
echo 1. Visual Studio öffnen: start Arbeitszeiterfassung.sln
echo 2. Schritt 1.2 ausführen (Datenbankdesign)
echo 3. Prompt verwenden: ..\Prompts\Schritt_1_2_Datenbankdesign.md
echo 4. README.md im Projektstamm bei relevanten Änderungen aktualisieren
echo.
pause
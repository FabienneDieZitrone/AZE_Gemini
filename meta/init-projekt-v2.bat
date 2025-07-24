@echo off
echo ======================================
echo Arbeitszeiterfassung Projekt Setup v2
echo ======================================
echo.

REM Prüfe .NET SDK
echo Pruefe .NET SDK...
dotnet --version >nul 2>&1
if errorlevel 1 (
    echo FEHLER: .NET SDK nicht gefunden!
    echo Installation: winget install Microsoft.DotNet.SDK.8
    pause
    exit /b 1
)

echo + .NET SDK gefunden
dotnet --version
echo.

REM Basis-Verzeichnis (absoluter Pfad)
set "BASE_DIR=%CD%\Arbeitszeiterfassung"
echo Projektverzeichnis: %BASE_DIR%

REM Erstelle und wechsle in Projektverzeichnis
if not exist "%BASE_DIR%" mkdir "%BASE_DIR%"
cd /d "%BASE_DIR%"

echo Arbeitsverzeichnis: %CD%
echo.

REM Erstelle Solution
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

echo - User Interface (Windows Forms)
REM Verwende net8.0 ohne -windows Suffix
dotnet new winforms -n Arbeitszeiterfassung.UI -f net8.0

REM Bearbeite die csproj-Datei für Windows-Unterstützung
echo ^<Project Sdk="Microsoft.NET.Sdk"^> > temp_ui.csproj
echo   ^<PropertyGroup^> >> temp_ui.csproj
echo     ^<OutputType^>WinExe^</OutputType^> >> temp_ui.csproj
echo     ^<TargetFramework^>net8.0-windows^</TargetFramework^> >> temp_ui.csproj
echo     ^<UseWindowsForms^>true^</UseWindowsForms^> >> temp_ui.csproj
echo     ^<ImplicitUsings^>enable^</ImplicitUsings^> >> temp_ui.csproj
echo     ^<Nullable^>enable^</Nullable^> >> temp_ui.csproj
echo   ^</PropertyGroup^> >> temp_ui.csproj
echo ^</Project^> >> temp_ui.csproj

move temp_ui.csproj Arbeitszeiterfassung.UI\Arbeitszeiterfassung.UI.csproj

dotnet sln add Arbeitszeiterfassung.UI\Arbeitszeiterfassung.UI.csproj

echo - Tests
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests\Arbeitszeiterfassung.Tests.csproj

REM Projektverweise (bleibe im BASE_DIR)
echo.
echo Erstelle Projektverweise...

cd /d "%BASE_DIR%\Arbeitszeiterfassung.DAL"
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj

cd /d "%BASE_DIR%\Arbeitszeiterfassung.BLL"
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj

cd /d "%BASE_DIR%\Arbeitszeiterfassung.UI"
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj

cd /d "%BASE_DIR%\Arbeitszeiterfassung.Tests"
dotnet add reference ..\Arbeitszeiterfassung.Common\Arbeitszeiterfassung.Common.csproj
dotnet add reference ..\Arbeitszeiterfassung.DAL\Arbeitszeiterfassung.DAL.csproj
dotnet add reference ..\Arbeitszeiterfassung.BLL\Arbeitszeiterfassung.BLL.csproj

REM Zurück zum Basis-Verzeichnis
cd /d "%BASE_DIR%"

REM NuGet-Pakete
echo.
echo Installiere NuGet-Pakete...

echo - Entity Framework fuer DAL
cd /d "%BASE_DIR%\Arbeitszeiterfassung.DAL"
dotnet add package Microsoft.EntityFrameworkCore --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Sqlite --version 8.0.0
dotnet add package Pomelo.EntityFrameworkCore.MySql --version 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Tools --version 8.0.0

echo - Configuration fuer Common
cd /d "%BASE_DIR%\Arbeitszeiterfassung.Common"
dotnet add package Microsoft.Extensions.Configuration --version 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json --version 8.0.0
dotnet add package Microsoft.Extensions.Logging --version 8.0.0

echo - DI Container fuer UI
cd /d "%BASE_DIR%\Arbeitszeiterfassung.UI"
dotnet add package Microsoft.Extensions.DependencyInjection --version 8.0.0
dotnet add package Microsoft.Extensions.Hosting --version 8.0.0

echo - Test-Pakete
cd /d "%BASE_DIR%\Arbeitszeiterfassung.Tests"
dotnet add package Microsoft.NET.Test.Sdk --version 17.11.1
dotnet add package xunit --version 2.9.2
dotnet add package xunit.runner.visualstudio --version 2.8.2
dotnet add package Moq --version 4.20.72
dotnet add package FluentAssertions --version 6.12.1

REM Zurück zum Basis-Verzeichnis
cd /d "%BASE_DIR%"

REM Ordnerstruktur
echo.
echo Erstelle Ordnerstruktur...

mkdir Arbeitszeiterfassung.Common\Configuration 2>nul
mkdir Arbeitszeiterfassung.Common\Enums 2>nul
mkdir Arbeitszeiterfassung.Common\Models 2>nul

mkdir Arbeitszeiterfassung.DAL\Context 2>nul
mkdir Arbeitszeiterfassung.DAL\Entities 2>nul
mkdir Arbeitszeiterfassung.DAL\Repositories 2>nul

mkdir Arbeitszeiterfassung.BLL\Services 2>nul
mkdir Arbeitszeiterfassung.BLL\DTOs 2>nul

mkdir Arbeitszeiterfassung.UI\Forms 2>nul
mkdir Arbeitszeiterfassung.UI\Controls 2>nul

REM Konfigurationsdateien
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
    echo ! Build-Probleme erkannt
    echo Fuehren Sie manuell aus: dotnet build
    echo.
) else (
    echo.
    echo ======================================
    echo + Projekt erfolgreich erstellt!
    echo ======================================
    echo.
)

echo Projektverzeichnis: %BASE_DIR%
echo.
echo Naechste Schritte:
echo 1. Visual Studio oeffnen: start Arbeitszeiterfassung.sln
echo 2. Schritt 1.2 ausfuehren (Datenbankdesign)
echo 3. Prompt verwenden: ..\Prompts\Schritt_1_2_Datenbankdesign.md
echo.
echo 4. README.md im Projektstamm bei relevanten Änderungen aktualisieren
pause
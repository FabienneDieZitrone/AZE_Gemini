@echo off
setlocal enabledelayedexpansion

echo ======================================
echo Arbeitszeiterfassung Projekt Setup
echo (Clean Install - .NET 8.0)
echo ======================================

echo.
echo Pruefe .NET SDK...
dotnet --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ! .NET SDK nicht gefunden
    echo Bitte installieren Sie .NET 8.0 SDK von https://dotnet.microsoft.com/download
    pause
    exit /b 1
)
echo + .NET SDK gefunden
dotnet --version

echo.
set "PROJECT_DIR=%~dp0Arbeitszeiterfassung"
echo Projektverzeichnis: %PROJECT_DIR%

REM Bereinige altes Projektverzeichnis
if exist "%PROJECT_DIR%" (
    echo Bereinige vorhandenes Projektverzeichnis...
    rmdir /s /q "%PROJECT_DIR%" 2>nul
)

REM Erstelle neues Projektverzeichnis
mkdir "%PROJECT_DIR%"
cd /d "%PROJECT_DIR%"
echo Arbeitsverzeichnis: %CD%

echo.
echo Erstelle Solution...
dotnet new sln -n Arbeitszeiterfassung

echo.
echo Erstelle Projekte mit .NET 8.0...

echo - Common Library
dotnet new classlib -n Arbeitszeiterfassung.Common -f net8.0
dotnet sln add Arbeitszeiterfassung.Common

echo - Data Access Layer
dotnet new classlib -n Arbeitszeiterfassung.DAL -f net8.0
dotnet sln add Arbeitszeiterfassung.DAL

echo - Business Logic Layer
dotnet new classlib -n Arbeitszeiterfassung.BLL -f net8.0
dotnet sln add Arbeitszeiterfassung.BLL

echo - User Interface (Windows Forms)
mkdir Arbeitszeiterfassung.UI
cd Arbeitszeiterfassung.UI

REM Erstelle UI-Projektdatei manuell für .NET 8.0
echo ^<Project Sdk="Microsoft.NET.Sdk"^> > Arbeitszeiterfassung.UI.csproj
echo   ^<PropertyGroup^> >> Arbeitszeiterfassung.UI.csproj
echo     ^<OutputType^>WinExe^</OutputType^> >> Arbeitszeiterfassung.UI.csproj
echo     ^<TargetFramework^>net8.0-windows^</TargetFramework^> >> Arbeitszeiterfassung.UI.csproj
echo     ^<UseWindowsForms^>true^</UseWindowsForms^> >> Arbeitszeiterfassung.UI.csproj
echo     ^<ImplicitUsings^>enable^</ImplicitUsings^> >> Arbeitszeiterfassung.UI.csproj
echo     ^<Nullable^>enable^</Nullable^> >> Arbeitszeiterfassung.UI.csproj
echo   ^</PropertyGroup^> >> Arbeitszeiterfassung.UI.csproj
echo ^</Project^> >> Arbeitszeiterfassung.UI.csproj

REM Erstelle Program.cs für Windows Forms
echo namespace Arbeitszeiterfassung.UI; > Program.cs
echo. >> Program.cs
echo internal static class Program >> Program.cs
echo { >> Program.cs
echo     [STAThread] >> Program.cs
echo     static void Main() >> Program.cs
echo     { >> Program.cs
echo         ApplicationConfiguration.Initialize(); >> Program.cs
echo         Application.Run(new Form1()); >> Program.cs
echo     } >> Program.cs
echo } >> Program.cs

REM Erstelle Form1.cs
echo namespace Arbeitszeiterfassung.UI; > Form1.cs
echo. >> Form1.cs
echo public partial class Form1 : Form >> Form1.cs
echo { >> Form1.cs
echo     public Form1() >> Form1.cs
echo     { >> Form1.cs
echo         InitializeComponent(); >> Form1.cs
echo     } >> Form1.cs
echo } >> Form1.cs

REM Erstelle Form1.Designer.cs
echo namespace Arbeitszeiterfassung.UI; > Form1.Designer.cs
echo. >> Form1.Designer.cs
echo partial class Form1 >> Form1.Designer.cs
echo { >> Form1.Designer.cs
echo     private System.ComponentModel.IContainer components = null; >> Form1.Designer.cs
echo. >> Form1.Designer.cs
echo     protected override void Dispose(bool disposing) >> Form1.Designer.cs
echo     { >> Form1.Designer.cs
echo         if (disposing ^&^& (components != null)) >> Form1.Designer.cs
echo         { >> Form1.Designer.cs
echo             components.Dispose(); >> Form1.Designer.cs
echo         } >> Form1.Designer.cs
echo         base.Dispose(disposing); >> Form1.Designer.cs
echo     } >> Form1.Designer.cs
echo. >> Form1.Designer.cs
echo     private void InitializeComponent() >> Form1.Designer.cs
echo     { >> Form1.Designer.cs
echo         this.components = new System.ComponentModel.Container(); >> Form1.Designer.cs
echo         this.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font; >> Form1.Designer.cs
echo         this.ClientSize = new System.Drawing.Size(800, 450); >> Form1.Designer.cs
echo         this.Text = "Arbeitszeiterfassung"; >> Form1.Designer.cs
echo     } >> Form1.Designer.cs
echo } >> Form1.Designer.cs

cd ..
dotnet sln add Arbeitszeiterfassung.UI

echo - Tests
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests

echo.
echo Erstelle Projektverweise...
dotnet add Arbeitszeiterfassung.DAL reference Arbeitszeiterfassung.Common
dotnet add Arbeitszeiterfassung.BLL reference Arbeitszeiterfassung.Common
dotnet add Arbeitszeiterfassung.BLL reference Arbeitszeiterfassung.DAL
dotnet add Arbeitszeiterfassung.UI reference Arbeitszeiterfassung.Common
dotnet add Arbeitszeiterfassung.UI reference Arbeitszeiterfassung.DAL
dotnet add Arbeitszeiterfassung.UI reference Arbeitszeiterfassung.BLL
dotnet add Arbeitszeiterfassung.Tests reference Arbeitszeiterfassung.Common
dotnet add Arbeitszeiterfassung.Tests reference Arbeitszeiterfassung.DAL
dotnet add Arbeitszeiterfassung.Tests reference Arbeitszeiterfassung.BLL

echo.
echo Installiere NuGet-Pakete...
echo - Entity Framework fuer DAL
cd Arbeitszeiterfassung.DAL
dotnet add package Microsoft.EntityFrameworkCore --version 8.0.11
dotnet add package Microsoft.EntityFrameworkCore.Sqlite --version 8.0.11
dotnet add package Pomelo.EntityFrameworkCore.MySql --version 8.0.2
dotnet add package Microsoft.EntityFrameworkCore.Tools --version 8.0.11
cd ..

echo - Configuration fuer Common
cd Arbeitszeiterfassung.Common
dotnet add package Microsoft.Extensions.Configuration --version 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json --version 8.0.1
dotnet add package Microsoft.Extensions.Logging --version 8.0.1
cd ..

echo - DI Container fuer UI
cd Arbeitszeiterfassung.UI
dotnet add package Microsoft.Extensions.DependencyInjection --version 8.0.1
dotnet add package Microsoft.Extensions.Hosting --version 8.0.1
cd ..

echo - Test-Pakete
cd Arbeitszeiterfassung.Tests
dotnet add package Moq --version 4.20.70
dotnet add package FluentAssertions --version 6.12.0
cd ..

echo.
echo Erstelle Ordnerstruktur...
mkdir Docs 2>nul
mkdir Scripts 2>nul
mkdir Database 2>nul

REM Erstelle Unterordner in Projekten
mkdir Arbeitszeiterfassung.Common\Models 2>nul
mkdir Arbeitszeiterfassung.Common\Enums 2>nul
mkdir Arbeitszeiterfassung.Common\DTOs 2>nul
mkdir Arbeitszeiterfassung.Common\Interfaces 2>nul

mkdir Arbeitszeiterfassung.DAL\Context 2>nul
mkdir Arbeitszeiterfassung.DAL\Entities 2>nul
mkdir Arbeitszeiterfassung.DAL\Repositories 2>nul
mkdir Arbeitszeiterfassung.DAL\Configurations 2>nul

mkdir Arbeitszeiterfassung.BLL\Services 2>nul
mkdir Arbeitszeiterfassung.BLL\Validators 2>nul
mkdir Arbeitszeiterfassung.BLL\Processors 2>nul

mkdir Arbeitszeiterfassung.UI\Forms 2>nul
mkdir Arbeitszeiterfassung.UI\Controls 2>nul
mkdir Arbeitszeiterfassung.UI\Helpers 2>nul

mkdir Arbeitszeiterfassung.Tests\Unit 2>nul
mkdir Arbeitszeiterfassung.Tests\Integration 2>nul
mkdir Arbeitszeiterfassung.Tests\TestData 2>nul

echo.
echo Erstelle Konfigurationsdateien...

REM appsettings.json fuer UI
echo { > Arbeitszeiterfassung.UI\appsettings.json
echo   "ConnectionStrings": { >> Arbeitszeiterfassung.UI\appsettings.json
echo     "DefaultConnection": "server=wp10454681.Server-he.de;database=db10454681-aze;uid=db10454681-aze;pwd=Start.321;", >> Arbeitszeiterfassung.UI\appsettings.json
echo     "OfflineConnection": "Data Source=arbeitszeiterfassung.db" >> Arbeitszeiterfassung.UI\appsettings.json
echo   }, >> Arbeitszeiterfassung.UI\appsettings.json
echo   "Logging": { >> Arbeitszeiterfassung.UI\appsettings.json
echo     "LogLevel": { >> Arbeitszeiterfassung.UI\appsettings.json
echo       "Default": "Information", >> Arbeitszeiterfassung.UI\appsettings.json
echo       "Microsoft": "Warning" >> Arbeitszeiterfassung.UI\appsettings.json
echo     } >> Arbeitszeiterfassung.UI\appsettings.json
echo   } >> Arbeitszeiterfassung.UI\appsettings.json
echo } >> Arbeitszeiterfassung.UI\appsettings.json

REM README erstellen
echo # Arbeitszeiterfassung > README.md
echo. >> README.md
echo Dieses Projekt wurde mit .NET 8.0 erstellt. >> README.md
echo. >> README.md
echo ## Projektstruktur >> README.md
echo - Arbeitszeiterfassung.Common: Gemeinsame Modelle und Interfaces >> README.md
echo - Arbeitszeiterfassung.DAL: Data Access Layer mit Entity Framework >> README.md
echo - Arbeitszeiterfassung.BLL: Business Logic Layer >> README.md
echo - Arbeitszeiterfassung.UI: Windows Forms Benutzeroberfläche >> README.md
echo - Arbeitszeiterfassung.Tests: Unit- und Integrationstests >> README.md
echo. >> README.md
echo ## Naechste Schritte >> README.md
echo 1. Visual Studio oeffnen: Arbeitszeiterfassung.sln >> README.md
echo 2. Schritt 1.2 ausfuehren (Datenbankdesign) >> README.md
echo 3. Prompt verwenden: ..\Prompts\Schritt_1_2_Datenbankdesign.md >> README.md

echo.
echo Teste Build...
dotnet restore
dotnet build --no-restore
if %errorlevel% neq 0 (
    echo ! Build-Probleme erkannt
    echo Versuche manuell: dotnet build
) else (
    echo + Build erfolgreich!
)

echo.
echo =====================================
echo Setup abgeschlossen!
echo =====================================
echo Projektverzeichnis: %PROJECT_DIR%
echo.
echo Naechste Schritte:
echo 1. Visual Studio oeffnen: Arbeitszeiterfassung.sln
echo 2. Schritt 1.2 ausfuehren (Datenbankdesign)
echo 3. Prompt verwenden: ..\Prompts\Schritt_1_2_Datenbankdesign.md
echo.
echo 4. README.md im Projektstamm bei relevanten Änderungen aktualisieren
echo Hinweis: Das Projekt verwendet .NET 8.0 (LTS)

pause
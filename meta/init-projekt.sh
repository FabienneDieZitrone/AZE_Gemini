#!/bin/bash
# ---
# title: Projekt-Initialisierungs-Skript
# version: 1.0
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/init-projekt.sh
# description: Bash-Skript zur Initialisierung des Arbeitszeiterfassungsprojekts
# ---

echo "======================================"
echo "Arbeitszeiterfassung Projekt Setup"
echo "======================================"
echo ""

# Prüfe ob .NET 8.0 installiert ist
echo "Prüfe .NET SDK Version..."
if ! command -v dotnet &> /dev/null; then
    echo "❌ FEHLER: .NET SDK ist nicht installiert!"
    echo "Bitte installieren Sie .NET 8.0 SDK von: https://dotnet.microsoft.com/download/dotnet/8.0"
    exit 1
fi

DOTNET_VERSION=$(dotnet --version)
echo "✓ .NET SDK Version: $DOTNET_VERSION"

# Basis-Verzeichnis (überschreibbar per Umgebungsvariable BASE_DIR)
BASE_DIR="${BASE_DIR:-/app/AZE/Arbeitszeiterfassung}"

# Erstelle Projektstruktur
echo ""
echo "Erstelle Projektstruktur..."
mkdir -p "$BASE_DIR"
cd "$BASE_DIR"

# Erstelle Solution
echo "Erstelle Solution..."
dotnet new sln -n Arbeitszeiterfassung

# Erstelle Projekte
echo ""
echo "Erstelle Projekte..."

# Common Library
echo "- Arbeitszeiterfassung.Common"
dotnet new classlib -n Arbeitszeiterfassung.Common -f net8.0
dotnet sln add Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj

# Data Access Layer
echo "- Arbeitszeiterfassung.DAL"
dotnet new classlib -n Arbeitszeiterfassung.DAL -f net8.0
dotnet sln add Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj

# Business Logic Layer
echo "- Arbeitszeiterfassung.BLL"
dotnet new classlib -n Arbeitszeiterfassung.BLL -f net8.0
dotnet sln add Arbeitszeiterfassung.BLL/Arbeitszeiterfassung.BLL.csproj

# User Interface (Windows Forms)
echo "- Arbeitszeiterfassung.UI"
dotnet new winforms -n Arbeitszeiterfassung.UI -f net8.0-windows
dotnet sln add Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj

# Test-Projekte
echo "- Arbeitszeiterfassung.Tests"
dotnet new xunit -n Arbeitszeiterfassung.Tests -f net8.0
dotnet sln add Arbeitszeiterfassung.Tests/Arbeitszeiterfassung.Tests.csproj

# Erstelle Projektverweise
echo ""
echo "Erstelle Projektverweise..."

# DAL referenziert Common
cd Arbeitszeiterfassung.DAL
dotnet add reference ../Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj
cd ..

# BLL referenziert Common und DAL
cd Arbeitszeiterfassung.BLL
dotnet add reference ../Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj
dotnet add reference ../Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj
cd ..

# UI referenziert alle anderen
cd Arbeitszeiterfassung.UI
dotnet add reference ../Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj
dotnet add reference ../Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj
dotnet add reference ../Arbeitszeiterfassung.BLL/Arbeitszeiterfassung.BLL.csproj
cd ..

# Tests referenziert alle anderen
cd Arbeitszeiterfassung.Tests
dotnet add reference ../Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj
dotnet add reference ../Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj
dotnet add reference ../Arbeitszeiterfassung.BLL/Arbeitszeiterfassung.BLL.csproj
cd ..

# Installiere NuGet-Pakete
echo ""
echo "Installiere NuGet-Pakete..."

# Entity Framework Core für DAL
cd Arbeitszeiterfassung.DAL
dotnet add package Microsoft.EntityFrameworkCore -v 8.0.0
dotnet add package Microsoft.EntityFrameworkCore.Sqlite -v 8.0.0
dotnet add package Pomelo.EntityFrameworkCore.MySql -v 8.0.0-preview.1
dotnet add package Microsoft.EntityFrameworkCore.Tools -v 8.0.0
cd ..

# Configuration für Common
cd Arbeitszeiterfassung.Common
dotnet add package Microsoft.Extensions.Configuration -v 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Json -v 8.0.0
dotnet add package Microsoft.Extensions.Configuration.Binder -v 8.0.0
dotnet add package Microsoft.Extensions.Logging -v 8.0.0
dotnet add package System.Security.Cryptography.ProtectedData -v 8.0.0
cd ..

# Windows Forms Erweiterungen für UI
cd Arbeitszeiterfassung.UI
dotnet add package Microsoft.Extensions.DependencyInjection -v 8.0.0
dotnet add package Microsoft.Extensions.Hosting -v 8.0.0
cd ..

# Test-Pakete
cd Arbeitszeiterfassung.Tests
dotnet add package Microsoft.NET.Test.Sdk -v 17.11.1
dotnet add package xunit -v 2.9.2
dotnet add package xunit.runner.visualstudio -v 2.8.2
dotnet add package Moq -v 4.20.72
dotnet add package FluentAssertions -v 6.12.1
cd ..

# Erstelle Ordnerstruktur in den Projekten
echo ""
echo "Erstelle Ordnerstruktur..."

# Common
mkdir -p Arbeitszeiterfassung.Common/{Configuration,Enums,Extensions,Helpers,Models}

# DAL
mkdir -p Arbeitszeiterfassung.DAL/{Context,Entities,Migrations,Repositories}

# BLL
mkdir -p Arbeitszeiterfassung.BLL/{Services,Validators,Interfaces,DTOs}

# UI
mkdir -p Arbeitszeiterfassung.UI/{Forms,Controls,Resources,Helpers}

# Erstelle Konfigurationsdateien
echo ""
echo "Erstelle Konfigurationsdateien..."

# appsettings.json
cat > Arbeitszeiterfassung.UI/appsettings.json << 'EOF'
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
EOF


# Erstelle .gitignore
cat > .gitignore << 'EOF'
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
EOF

# Build Solution
echo ""
echo "Baue Solution..."
dotnet build

echo ""
echo "======================================"
echo "✓ Projekt wurde erfolgreich erstellt!"
echo "======================================"
echo ""
echo "Projektverzeichnis: $BASE_DIR"
echo ""
echo "Nächste Schritte:"
echo "1. Öffnen Sie die Solution in Visual Studio oder VS Code"
echo "2. Führen Sie Schritt 1.2 aus: Datenbankdesign und Entity-Modelle"
echo "3. Verwenden Sie den Prompt: /app/AZE/Prompts/Schritt_1_2_Datenbankdesign.md"
echo "4. README.md im Projektstamm bei relevanten Änderungen aktualisieren"
echo ""
echo "Zum Öffnen in VS Code:"
echo "cd $BASE_DIR && code ."


@echo off
REM ---
REM title: Voraussetzungen-Check für Arbeitszeiterfassung (Batch)
REM version: 1.0
REM lastUpdated: 26.01.2025
REM author: Tanja Trella
REM status: Final
REM file: /app/AZE/check-prerequisites.bat
REM description: Prüft alle benötigten Voraussetzungen für die Entwicklung unter Windows
REM ---

echo ======================================
echo Prüfe Entwicklungsumgebung
echo ======================================
echo.

set ERRORS=0
set WARNINGS=0

REM === 1. Betriebssystem ===
echo 1. Betriebssystem:
echo ✓ Windows erkannt
for /f "tokens=4-7 delims=[.] " %%i in ('ver') do (
    echo   Version: Windows %%i.%%j Build %%k.%%l
)
echo.

REM === 2. .NET SDK ===
echo 2. .NET SDK:
dotnet --version >nul 2>&1
if errorlevel 1 (
    echo ✗ .NET SDK nicht gefunden
    echo   Installation: winget install Microsoft.DotNet.SDK.8
    set /a ERRORS+=1
) else (
    echo ✓ .NET SDK gefunden
    for /f %%v in ('dotnet --version 2^>nul') do echo   Version: %%v
    
    REM Prüfe auf .NET 8.0
    dotnet --list-sdks | find "8.0" >nul 2>&1
    if errorlevel 1 (
        echo   ⚠ .NET 8.0 SDK nicht gefunden
        echo     Installierte SDKs:
        dotnet --list-sdks 2>nul | findstr "^" | more
        set /a WARNINGS+=1
    ) else (
        echo   ✓ .NET 8.0 SDK verfügbar
    )
)
echo.

REM === 3. MySQL/MariaDB ===
echo 3. Datenbank-Server:
mysql --version >nul 2>&1
if errorlevel 1 (
    echo ✗ MySQL/MariaDB Client nicht gefunden
    echo   Installation: winget install Oracle.MySQL
    set /a ERRORS+=1
) else (
    echo ✓ MySQL/MariaDB Client gefunden
    for /f "tokens=*" %%v in ('mysql --version 2^>nul') do echo   %%v
    
    REM Prüfe MySQL Service
    sc query MySQL >nul 2>&1
    if not errorlevel 1 (
        for /f "tokens=3" %%s in ('sc query MySQL ^| find "STATE"') do (
            if "%%s"=="RUNNING" (
                echo   ✓ MySQL Service läuft
            ) else (
                echo   ⚠ MySQL Service läuft nicht
                echo     Starten mit: net start MySQL
                set /a WARNINGS+=1
            )
        )
    ) else (
        REM Prüfe MariaDB Service
        sc query MariaDB >nul 2>&1
        if not errorlevel 1 (
            for /f "tokens=3" %%s in ('sc query MariaDB ^| find "STATE"') do (
                if "%%s"=="RUNNING" (
                    echo   ✓ MariaDB Service läuft
                ) else (
                    echo   ⚠ MariaDB Service läuft nicht
                    echo     Starten mit: net start MariaDB
                    set /a WARNINGS+=1
                )
            )
        ) else (
            echo   ⚠ Kein MySQL/MariaDB Service gefunden
            set /a WARNINGS+=1
        )
    )
)
echo.

REM === 4. Git ===
echo 4. Versionskontrolle:
git --version >nul 2>&1
if errorlevel 1 (
    echo ✗ Git nicht gefunden
    echo   Installation: winget install Git.Git
    set /a ERRORS+=1
) else (
    echo ✓ Git gefunden
    for /f "tokens=*" %%v in ('git --version 2^>nul') do echo   %%v
)
echo.

REM === 5. Entwicklungsumgebung ===
echo 5. Entwicklungsumgebung (optional):
set IDE_FOUND=0

REM Visual Studio 2022
reg query "HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall" /s /f "Visual Studio" 2>nul | find "2022" >nul
if not errorlevel 1 (
    echo ✓ Visual Studio 2022 gefunden
    set IDE_FOUND=1
)

REM VS Code
code --version >nul 2>&1
if not errorlevel 1 (
    echo ✓ Visual Studio Code gefunden
    for /f "tokens=1" %%v in ('code --version 2^>nul') do echo   Version: %%v
    set IDE_FOUND=1
)

if %IDE_FOUND%==0 (
    echo ⚠ Keine IDE gefunden
    echo   Empfohlen: Visual Studio 2022 oder VS Code
    echo   VS 2022: winget install Microsoft.VisualStudio.2022.Community
    echo   VS Code: winget install Microsoft.VisualStudioCode
    set /a WARNINGS+=1
)
echo.

REM === 6. Windows-Features ===
echo 6. Windows-Features:

REM .NET Framework (über Registry prüfen)
reg query "HKLM\SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full" /v Release >nul 2>&1
if not errorlevel 1 (
    echo ✓ .NET Framework 4.x installiert
) else (
    echo ⚠ .NET Framework 4.x nicht gefunden
    set /a WARNINGS+=1
)

echo ✓ Windows Forms (via .NET SDK)
echo.

REM === 7. Zusätzliche Tools ===
echo 7. Zusätzliche Tools (optional):

REM PowerShell Version
echo ✓ PowerShell Version: %PSModulePath:~0,1%
if defined PSModulePath (
    powershell -Command "$PSVersionTable.PSVersion" 2>nul
) else (
    echo   Windows PowerShell (Classic)
)

REM Windows Terminal
wt --version >nul 2>&1
if not errorlevel 1 (
    echo ✓ Windows Terminal gefunden
) else (
    echo   Info: Windows Terminal empfohlen (winget install Microsoft.WindowsTerminal)
)

REM Node.js (optional)
node --version >nul 2>&1
if not errorlevel 1 (
    echo ✓ Node.js gefunden
    for /f %%v in ('node --version 2^>nul') do echo   Version: %%v
    echo   Info: Nicht erforderlich für dieses Projekt
)

REM Docker (optional)
docker --version >nul 2>&1
if not errorlevel 1 (
    echo ✓ Docker gefunden
    for /f "tokens=*" %%v in ('docker --version 2^>nul') do echo   %%v
    echo   Info: Nur für Container-Deployment benötigt
)

echo.
echo ======================================
echo Zusammenfassung:
echo ======================================

if %ERRORS%==0 (
    if %WARNINGS%==0 (
        echo ✓ Alle Voraussetzungen erfüllt!
        echo.
        echo Sie können mit der Entwicklung beginnen:
        echo   init-projekt.bat
        echo.
    ) else (
        echo ✓ Grundvoraussetzungen erfüllt
        echo   %WARNINGS% Warnung(en) gefunden
        echo.
        echo Sie können mit der Entwicklung beginnen, aber prüfen Sie die Warnungen.
        echo.
    )
) else (
    echo ✗ %ERRORS% kritische Fehler gefunden!
    echo.
    echo Bitte installieren Sie die fehlenden Komponenten vor dem Start.
    echo.
)

REM Admin-Rechte prüfen
net session >nul 2>&1
if not errorlevel 1 (
    echo Hinweis: Sie haben Administrator-Rechte
) else (
    echo Hinweis: Einige Installationen benötigen Administrator-Rechte
    echo          Führen Sie die Eingabeaufforderung als Administrator aus
)

echo.
echo Drücken Sie eine beliebige Taste zum Beenden...
pause >nul

exit /b %ERRORS%
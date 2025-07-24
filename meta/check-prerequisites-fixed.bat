@echo off
chcp 65001 >nul
REM ---
REM title: Voraussetzungen-Check für Arbeitszeiterfassung (Korrigiert)
REM version: 1.1
REM lastUpdated: 26.01.2025
REM author: Tanja Trella
REM status: Final
REM file: /app/AZE/check-prerequisites-fixed.bat
REM description: Korrigierte Version für Netzlaufwerke
REM ---

echo ======================================
echo Pruefe Entwicklungsumgebung
echo ======================================
echo.

set ERRORS=0
set WARNINGS=0

REM === UNC-Pfad Problem lösen ===
if "%CD:~0,2%"=="\\" (
    echo Hinweis: UNC-Pfad erkannt - kopiere auf lokales Laufwerk...
    set "LOCAL_TEMP=%TEMP%\AZE_Check"
    if not exist "%LOCAL_TEMP%" mkdir "%LOCAL_TEMP%" >nul 2>&1
    pushd "%LOCAL_TEMP%" >nul 2>&1
    echo Arbeitsverzeichnis: %LOCAL_TEMP%
    echo.
)

REM === 1. Betriebssystem ===
echo 1. Betriebssystem:
echo + Windows erkannt
ver | find "Version" >nul 2>&1
if not errorlevel 1 (
    for /f "tokens=*" %%v in ('ver') do echo   %%v
) else (
    echo   Windows 10/11
)
echo.

REM === 2. .NET SDK ===
echo 2. .NET SDK:
where dotnet >nul 2>&1
if errorlevel 1 (
    echo - .NET SDK nicht gefunden
    echo   Installation: winget install Microsoft.DotNet.SDK.8
    set /a ERRORS+=1
) else (
    echo + .NET SDK gefunden
    dotnet --version 2>nul | findstr /R "^[0-9]" && (
        echo   Gefundene Version
    ) || (
        echo   Version nicht ermittelbar
    )
    
    REM Prüfe auf .NET 8.0
    dotnet --list-sdks 2>nul | find "8.0" >nul 2>&1
    if errorlevel 1 (
        echo   ! .NET 8.0 SDK nicht gefunden
        echo     Installieren Sie: winget install Microsoft.DotNet.SDK.8
        set /a WARNINGS+=1
    ) else (
        echo   + .NET 8.0 SDK verfuegbar
    )
)
echo.

REM === 3. MySQL/MariaDB ===
echo 3. Datenbank-Server:
where mysql >nul 2>&1
if errorlevel 1 (
    echo - MySQL/MariaDB Client nicht gefunden
    echo   Installation: winget install Oracle.MySQL
    set /a ERRORS+=1
) else (
    echo + MySQL/MariaDB Client gefunden
    
    REM Prüfe Services (vereinfacht)
    sc query MySQL >nul 2>&1 && (
        echo   + MySQL Service gefunden
    ) || (
        sc query MariaDB >nul 2>&1 && (
            echo   + MariaDB Service gefunden
        ) || (
            echo   ! Kein Datenbank-Service aktiv
            set /a WARNINGS+=1
        )
    )
)
echo.

REM === 4. Git ===
echo 4. Versionskontrolle:
where git >nul 2>&1
if errorlevel 1 (
    echo - Git nicht gefunden
    echo   Installation: winget install Git.Git
    set /a ERRORS+=1
) else (
    echo + Git gefunden
    git --version 2>nul | findstr /R "^git" && (
        echo   Git ist verfuegbar
    )
)
echo.

REM === 5. Entwicklungsumgebung ===
echo 5. Entwicklungsumgebung:
set IDE_FOUND=0

REM Visual Studio (vereinfacht)
if exist "%ProgramFiles%\Microsoft Visual Studio" (
    echo + Visual Studio gefunden
    set IDE_FOUND=1
)

if exist "%ProgramFiles(x86)%\Microsoft Visual Studio" (
    echo + Visual Studio gefunden
    set IDE_FOUND=1
)

REM VS Code
where code >nul 2>&1
if not errorlevel 1 (
    echo + Visual Studio Code gefunden
    set IDE_FOUND=1
)

if %IDE_FOUND%==0 (
    echo ! Keine IDE gefunden
    echo   Empfohlen: VS Code oder Visual Studio 2022
    echo   VS Code: winget install Microsoft.VisualStudioCode
    set /a WARNINGS+=1
)
echo.

REM === 6. Zusätzliche Tools ===
echo 6. Zusaetzliche Tools:

where wt >nul 2>&1
if not errorlevel 1 (
    echo + Windows Terminal verfuegbar
) else (
    echo   Info: Windows Terminal empfohlen
)

where node >nul 2>&1
if not errorlevel 1 (
    echo + Node.js gefunden (optional)
)

echo.

REM === Zusammenfassung ===
echo ======================================
echo Zusammenfassung:
echo ======================================

if %ERRORS%==0 (
    if %WARNINGS%==0 (
        echo + Alle Voraussetzungen erfuellt!
        echo.
        echo Naechste Schritte:
        echo 1. Kopieren Sie die AZE-Dateien auf lokales Laufwerk
        echo 2. Fuehren Sie init-projekt.bat aus
        echo 3. README.md im Projektstamm bei relevanten Änderungen aktualisieren
        echo + Grundvoraussetzungen erfuellt
        echo ! %WARNINGS% Warnung(en) vorhanden
        echo.
        echo Sie koennen mit der Entwicklung beginnen.
    )
) else (
    echo - %ERRORS% kritische Fehler gefunden!
    echo.
    echo Installieren Sie die fehlenden Komponenten:
    if %ERRORS% geq 1 (
        echo.
        echo Schnell-Installation:
        echo   winget install Microsoft.DotNet.SDK.8
        echo   winget install Git.Git
        echo   winget install Oracle.MySQL
        echo   winget install Microsoft.VisualStudioCode
    )
)

echo.
echo Empfehlung: Kopieren Sie die Dateien auf C:\Temp\AZE
echo und arbeiten Sie von dort aus.
echo.

REM Cleanup
if defined LOCAL_TEMP (
    popd >nul 2>&1
)

pause
exit /b %ERRORS%
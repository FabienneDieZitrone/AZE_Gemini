@echo off
REM ---
REM title: Lokales Setup für Arbeitszeiterfassung
REM version: 1.0
REM lastUpdated: 26.01.2025
REM author: Tanja Trella
REM status: Final
REM file: /app/AZE/setup-local.bat
REM description: Kopiert Dateien lokal und startet Setup
REM ---

echo ======================================
echo Lokales Arbeitszeiterfassung Setup
echo ======================================
echo.

REM Zielverzeichnis
set "TARGET_DIR=C:\Temp\AZE"
set "SOURCE_DIR=%~dp0"

echo Quelle: %SOURCE_DIR%
echo Ziel:   %TARGET_DIR%
echo.

REM Erstelle Zielverzeichnis
if not exist "%TARGET_DIR%" (
    echo Erstelle Verzeichnis %TARGET_DIR%...
    mkdir "%TARGET_DIR%" 2>nul
    if errorlevel 1 (
        echo FEHLER: Kann Verzeichnis nicht erstellen!
        echo Verwenden Sie: %USERPROFILE%\Desktop\AZE
        set "TARGET_DIR=%USERPROFILE%\Desktop\AZE_Local"
        mkdir "%TARGET_DIR%" 2>nul
    )
)

REM Kopiere Dateien
echo Kopiere Dateien...
xcopy "%SOURCE_DIR%*.*" "%TARGET_DIR%\" /Y /Q >nul 2>&1
xcopy "%SOURCE_DIR%Prompts\*.*" "%TARGET_DIR%\Prompts\" /Y /Q /S >nul 2>&1

if errorlevel 1 (
    echo FEHLER beim Kopieren!
    echo Kopieren Sie manuell:
    echo   Quelle: %SOURCE_DIR%
    echo   Ziel:   %TARGET_DIR%
    pause
    exit /b 1
)

echo Dateien erfolgreich kopiert!
echo.

REM Wechsle zum lokalen Verzeichnis
cd /d "%TARGET_DIR%"

echo Arbeitsverzeichnis: %CD%
echo.

REM Führe Check aus
echo Starte Voraussetzungen-Check...
echo.
call check-prerequisites-fixed.bat

echo.
echo ======================================
echo Setup abgeschlossen!
echo ======================================
echo.
echo Arbeitsverzeichnis: %TARGET_DIR%
echo.
echo Naechste Schritte:
echo 1. Falls alle Voraussetzungen erfuellt:
echo    init-projekt.bat
echo.
echo 2. README.md im Projektstamm bei relevanten Änderungen aktualisieren
echo.
echo 3. Falls Fehler vorhanden:
echo    Installieren Sie die fehlenden Komponenten
echo    und wiederholen Sie init-projekt.bat
echo.

pause
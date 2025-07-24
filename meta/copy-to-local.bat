@echo off
echo ======================================
echo Kopiere AZE-Projekt auf lokales Laufwerk
echo ======================================
echo.

set "SOURCE=%~dp0"
set "TARGET=C:\Temp\AZE"

echo Quelle: %SOURCE%
echo Ziel:   %TARGET%
echo.

if exist "%TARGET%" (
    echo Verzeichnis %TARGET% existiert bereits.
    echo Inhalt wird ueberschrieben.
    echo.
)

echo Erstelle Zielverzeichnis...
mkdir "%TARGET%" 2>nul
mkdir "%TARGET%\Prompts" 2>nul

echo Kopiere Dateien...
copy "%SOURCE%*.bat" "%TARGET%\" >nul 2>&1
copy "%SOURCE%*.ps1" "%TARGET%\" >nul 2>&1  
copy "%SOURCE%*.sh" "%TARGET%\" >nul 2>&1
copy "%SOURCE%*.md" "%TARGET%\" >nul 2>&1
copy "%SOURCE%Prompts\*.md" "%TARGET%\Prompts\" >nul 2>&1

if errorlevel 1 (
    echo FEHLER beim Kopieren!
    echo Versuchen Sie manuelles Kopieren.
    pause
    exit /b 1
)

echo.
echo ======================================
echo Erfolgreich kopiert!
echo ======================================
echo.
echo Arbeitsverzeichnis: %TARGET%
echo.
echo Naechste Schritte:
echo 1. cd /d C:\Temp\AZE
echo 2. quick-check.bat
echo 3. init-projekt.bat
echo 4. README.md im Projektstamm bei relevanten Änderungen aktualisieren
echo.

echo Soll automatisch zu %TARGET% gewechselt werden? (J/N)
set /p answer=
if /i "%answer%"=="J" (
    cd /d "%TARGET%"
    echo.
    echo Jetzt in: %CD%
    echo.
    echo Fuehren Sie aus: quick-check.bat
)

pause
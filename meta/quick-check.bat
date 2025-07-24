@echo off
echo ======================================
echo Schnell-Check: Entwicklungsumgebung
echo ======================================
echo.

echo 1. Betriebssystem:
echo    Windows erkannt
echo.

echo 2. .NET SDK:
dotnet --version >nul 2>&1
if errorlevel 1 (
    echo    FEHLER: .NET SDK nicht gefunden
    echo    Installation: winget install Microsoft.DotNet.SDK.8
    echo.
    goto :mysql
) else (
    echo    OK: .NET SDK gefunden
    dotnet --version
    echo.
)

:mysql
echo 3. MySQL/MariaDB:
mysql --version >nul 2>&1
if errorlevel 1 (
    echo    FEHLER: MySQL Client nicht gefunden
    echo    Installation: winget install Oracle.MySQL
    echo.
) else (
    echo    OK: MySQL Client gefunden
    echo.
)

echo 4. Git:
git --version >nul 2>&1
if errorlevel 1 (
    echo    FEHLER: Git nicht gefunden
    echo    Installation: winget install Git.Git
    echo.
) else (
    echo    OK: Git gefunden
    echo.
)

echo 5. Visual Studio Code:
code --version >nul 2>&1
if errorlevel 1 (
    echo    INFO: VS Code nicht gefunden
    echo    Installation: winget install Microsoft.VisualStudioCode
    echo.
) else (
    echo    OK: VS Code gefunden
    echo.
)

echo ======================================
echo Zusammenfassung:
echo ======================================
echo.
echo Falls FEHLER angezeigt wurden, installieren Sie:
echo.
echo   winget install Microsoft.DotNet.SDK.8
echo   winget install Git.Git  
echo   winget install Oracle.MySQL
echo   winget install Microsoft.VisualStudioCode
echo.
echo Danach koennen Sie das Projekt erstellen mit:
echo   init-projekt.bat
echo.
echo WICHTIG: Kopieren Sie zuerst alle Dateien 
echo auf Ihr lokales Laufwerk (z.B. C:\Temp\AZE)
echo.
pause
# ---
# title: Voraussetzungen-Check für Arbeitszeiterfassung (PowerShell)
# version: 1.0
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/check-prerequisites.ps1
# description: Prüft alle benötigten Voraussetzungen für die Entwicklung unter Windows
# ---

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Prüfe Entwicklungsumgebung" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

$errors = 0
$warnings = 0

# Hilfsfunktion
function Test-Command {
    param(
        [string]$Command,
        [string]$DisplayName,
        [scriptblock]$VersionCheck,
        [string]$InstallHint
    )
    
    try {
        if (Get-Command $Command -ErrorAction SilentlyContinue) {
            Write-Host "✓ $DisplayName gefunden" -ForegroundColor Green
            if ($VersionCheck) {
                $version = & $VersionCheck
                Write-Host "  Version: $version" -ForegroundColor Gray
            }
            return $true
        }
        else {
            Write-Host "✗ $DisplayName nicht gefunden" -ForegroundColor Red
            Write-Host "  Installation: $InstallHint" -ForegroundColor Yellow
            $script:errors++
            return $false
        }
    }
    catch {
        Write-Host "✗ Fehler beim Prüfen von $DisplayName" -ForegroundColor Red
        $script:errors++
        return $false
    }
}

# 1. Betriebssystem
Write-Host "1. Betriebssystem:" -ForegroundColor White
$os = Get-CimInstance Win32_OperatingSystem
Write-Host "✓ Windows erkannt" -ForegroundColor Green
Write-Host "  Version: $($os.Caption) Build $($os.BuildNumber)" -ForegroundColor Gray
Write-Host ""

# 2. .NET SDK
Write-Host "2. .NET SDK:" -ForegroundColor White
if (Test-Command "dotnet" ".NET SDK" { dotnet --version } "winget install Microsoft.DotNet.SDK.8") {
    # Prüfe auf .NET 8.0
    $sdks = dotnet --list-sdks
    if ($sdks -match "8.0") {
        Write-Host "  ✓ .NET 8.0 SDK verfügbar" -ForegroundColor Green
    }
    else {
        Write-Host "  ⚠ .NET 8.0 SDK nicht gefunden" -ForegroundColor Yellow
        Write-Host "    Installierte SDKs:" -ForegroundColor Gray
        $sdks | ForEach-Object { Write-Host "    $_" -ForegroundColor Gray }
        $warnings++
    }
}
Write-Host ""

# 3. MySQL/MariaDB
Write-Host "3. Datenbank-Server:" -ForegroundColor White
$mysqlFound = Test-Command "mysql" "MySQL/MariaDB Client" { mysql --version 2>$null } "winget install Oracle.MySQL"

if ($mysqlFound) {
    # Prüfe ob Service läuft
    $mysqlService = Get-Service -Name "MySQL*", "MariaDB*" -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Running' }
    if ($mysqlService) {
        Write-Host "  ✓ MySQL/MariaDB Service läuft" -ForegroundColor Green
    }
    else {
        Write-Host "  ⚠ MySQL/MariaDB Service läuft nicht" -ForegroundColor Yellow
        $warnings++
    }
}
Write-Host ""

# 4. Git
Write-Host "4. Versionskontrolle:" -ForegroundColor White
Test-Command "git" "Git" { git --version } "winget install Git.Git" | Out-Null
Write-Host ""

# 5. Entwicklungsumgebung
Write-Host "5. Entwicklungsumgebung (optional):" -ForegroundColor White
$ideFound = $false

# Visual Studio 2022
$vs2022 = Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\*" | 
    Where-Object { $_.DisplayName -like "*Visual Studio*2022*" }
if ($vs2022) {
    Write-Host "✓ Visual Studio 2022 gefunden" -ForegroundColor Green
    $ideFound = $true
}

# VS Code
if (Test-Command "code" "Visual Studio Code" { code --version | Select-Object -First 1 } "winget install Microsoft.VisualStudioCode") {
    $ideFound = $true
}

if (-not $ideFound) {
    Write-Host "⚠ Keine IDE gefunden" -ForegroundColor Yellow
    Write-Host "  Empfohlen: Visual Studio 2022 oder VS Code" -ForegroundColor Gray
    $warnings++
}
Write-Host ""

# 6. Windows-Features
Write-Host "6. Windows-Features:" -ForegroundColor White

# .NET Framework (für Legacy-Kompatibilität)
$dotnetFramework = Get-WindowsOptionalFeature -Online -FeatureName "NetFx4-AdvSrvs" -ErrorAction SilentlyContinue
if ($dotnetFramework -and $dotnetFramework.State -eq "Enabled") {
    Write-Host "✓ .NET Framework 4.x aktiviert" -ForegroundColor Green
}
else {
    Write-Host "⚠ .NET Framework 4.x nicht aktiviert" -ForegroundColor Yellow
    $warnings++
}

# Windows Forms (sollte mit .NET SDK kommen)
Write-Host "✓ Windows Forms (via .NET SDK)" -ForegroundColor Green
Write-Host ""

# 7. Zusätzliche Tools
Write-Host "7. Zusätzliche Tools (optional):" -ForegroundColor White

# PowerShell Version
Write-Host "✓ PowerShell Version: $($PSVersionTable.PSVersion)" -ForegroundColor Green

# Windows Terminal
if (Get-Command "wt" -ErrorAction SilentlyContinue) {
    Write-Host "✓ Windows Terminal gefunden" -ForegroundColor Green
}
else {
    Write-Host "  Info: Windows Terminal empfohlen (winget install Microsoft.WindowsTerminal)" -ForegroundColor Gray
}

# Node.js
if (Test-Command "node" "Node.js" { node --version } "winget install OpenJS.NodeJS") {
    Write-Host "  Info: Nicht erforderlich für dieses Projekt" -ForegroundColor Gray
}

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Zusammenfassung:" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan

if ($errors -eq 0) {
    if ($warnings -eq 0) {
        Write-Host "✓ Alle Voraussetzungen erfüllt!" -ForegroundColor Green
        Write-Host ""
        Write-Host "Sie können mit der Entwicklung beginnen:" -ForegroundColor White
        Write-Host "  .\init-projekt.ps1" -ForegroundColor Yellow
    }
    else {
        Write-Host "✓ Grundvoraussetzungen erfüllt" -ForegroundColor Green
        Write-Host "  $warnings Warnung(en) gefunden" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Sie können mit der Entwicklung beginnen, aber prüfen Sie die Warnungen." -ForegroundColor White
    }
}
else {
    Write-Host "✗ $errors kritische Fehler gefunden!" -ForegroundColor Red
    Write-Host ""
    Write-Host "Bitte installieren Sie die fehlenden Komponenten vor dem Start." -ForegroundColor White
    exit $errors
}

Write-Host ""

# Admin-Rechte prüfen
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
if (-not $isAdmin) {
    Write-Host "Hinweis: Einige Installationen benötigen Administrator-Rechte" -ForegroundColor Yellow
}
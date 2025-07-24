#!/bin/bash
# ---
# title: Voraussetzungen-Check für Arbeitszeiterfassung
# version: 1.0
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/check-prerequisites.sh
# description: Prüft alle benötigten Voraussetzungen für die Entwicklung
# ---

echo "======================================"
echo "Prüfe Entwicklungsumgebung"
echo "======================================"
echo ""

# Farben
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ERRORS=0
WARNINGS=0

# Funktion für Erfolg/Fehler
check_command() {
    if command -v $1 &> /dev/null; then
        VERSION=$($2 2>&1)
        echo -e "${GREEN}✓ $3 gefunden${NC}"
        echo "  Version: $VERSION"
        return 0
    else
        echo -e "${RED}✗ $3 nicht gefunden${NC}"
        echo "  Installation: $4"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

# 1. Betriebssystem
echo "1. Betriebssystem:"
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    echo -e "${GREEN}✓ Linux erkannt${NC}"
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        echo "  Distribution: $NAME $VERSION"
    fi
elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo -e "${GREEN}✓ macOS erkannt${NC}"
    echo "  Version: $(sw_vers -productVersion)"
elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "cygwin" ]]; then
    echo -e "${GREEN}✓ Windows (Git Bash/Cygwin) erkannt${NC}"
elif [[ -n "$WSL_DISTRO_NAME" ]]; then
    echo -e "${GREEN}✓ Windows WSL erkannt${NC}"
    echo "  Distribution: $WSL_DISTRO_NAME"
else
    echo -e "${YELLOW}⚠ Unbekanntes Betriebssystem: $OSTYPE${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# 2. .NET SDK
echo "2. .NET SDK:"
if check_command "dotnet" "dotnet --version" ".NET SDK" "https://dotnet.microsoft.com/download/dotnet/8.0"; then
    # Prüfe spezifisch auf .NET 8.0
    if dotnet --list-sdks | grep -q "8.0"; then
        echo -e "${GREEN}  ✓ .NET 8.0 SDK verfügbar${NC}"
    else
        echo -e "${YELLOW}  ⚠ .NET 8.0 SDK nicht gefunden${NC}"
        echo "    Installierte SDKs:"
        dotnet --list-sdks | sed 's/^/    /'
        WARNINGS=$((WARNINGS + 1))
    fi
fi

echo ""

# 3. MySQL/MariaDB
echo "3. Datenbank-Server:"
MYSQL_FOUND=false
if check_command "mysql" "mysql --version" "MySQL/MariaDB Client" "sudo apt-get install mariadb-client"; then
    MYSQL_FOUND=true
fi

# Optional prüfen, ob der lokale Server laufen muss
SKIP_MYSQL_SERVICE_CHECK=${SKIP_MYSQL_SERVICE_CHECK:-0}
if $MYSQL_FOUND; then
    if [ "$SKIP_MYSQL_SERVICE_CHECK" = "1" ]; then
        echo -e "${YELLOW}  ⚠ Überspringe MySQL/MariaDB Server-Check (Remote DB)${NC}"
    else
        if systemctl is-active --quiet mysql 2>/dev/null || systemctl is-active --quiet mariadb 2>/dev/null; then
            echo -e "${GREEN}  ✓ MySQL/MariaDB Server läuft${NC}"
        elif pgrep -x "mysqld" > /dev/null; then
            echo -e "${GREEN}  ✓ MySQL/MariaDB Server läuft${NC}"
        else
            echo -e "${YELLOW}  ⚠ MySQL/MariaDB Server läuft nicht${NC}"
            echo "    Starten mit: sudo systemctl start mariadb"
            WARNINGS=$((WARNINGS + 1))
        fi
    fi
fi

echo ""

# 4. Git
echo "4. Versionskontrolle:"
check_command "git" "git --version" "Git" "sudo apt-get install git"

echo ""

# 5. Entwicklungsumgebung
echo "5. Entwicklungsumgebung (optional):"
VS_FOUND=false

# Visual Studio Code
if check_command "code" "code --version | head -1" "Visual Studio Code" "https://code.visualstudio.com/"; then
    VS_FOUND=true
fi

# Visual Studio (Windows)
if command -v devenv &> /dev/null; then
    echo -e "${GREEN}✓ Visual Studio gefunden${NC}"
    VS_FOUND=true
fi

if ! $VS_FOUND; then
    echo -e "${YELLOW}⚠ Keine IDE gefunden${NC}"
    echo "  Empfohlen: VS Code oder Visual Studio"
    WARNINGS=$((WARNINGS + 1))
fi

echo ""

# 6. Build-Tools
echo "6. Build-Tools:"
# MSBuild (kommt mit .NET SDK)
if command -v dotnet &> /dev/null; then
    if dotnet msbuild --version &> /dev/null; then
        echo -e "${GREEN}✓ MSBuild verfügbar (via dotnet)${NC}"
    fi
fi

echo ""

# 7. Zusätzliche Tools
echo "7. Zusätzliche Tools (optional):"

# Node.js (für Frontend-Tools)
if check_command "node" "node --version" "Node.js" "https://nodejs.org/"; then
    :
else
    echo "  Info: Nicht erforderlich für dieses Projekt"
fi

# Docker (für Container-Deployment)
if check_command "docker" "docker --version" "Docker" "https://www.docker.com/"; then
    :
else
    echo "  Info: Nur für Container-Deployment benötigt"
fi

echo ""
echo "======================================"
echo "Zusammenfassung:"
echo "======================================"

if [ $ERRORS -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}✓ Alle Voraussetzungen erfüllt!${NC}"
        echo ""
        echo "Sie können mit der Entwicklung beginnen:"
        echo "  bash init-projekt.sh"
    else
        echo -e "${YELLOW}✓ Grundvoraussetzungen erfüllt${NC}"
        echo -e "${YELLOW}  $WARNINGS Warnung(en) gefunden${NC}"
        echo ""
        echo "Sie können mit der Entwicklung beginnen, aber prüfen Sie die Warnungen."
    fi
else
    echo -e "${RED}✗ $ERRORS kritische Fehler gefunden!${NC}"
    echo ""
    echo "Bitte installieren Sie die fehlenden Komponenten vor dem Start."
fi

echo ""

# Exit-Code basierend auf Fehlern
exit $ERRORS


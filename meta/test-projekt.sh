#!/bin/bash
# ---
# title: Test-Skript für Projektinitialisierung
# version: 1.2
# lastUpdated: 09.07.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/test-projekt.sh
# description: Skript zum Testen der Projektstruktur und Abhängigkeiten
# ---

echo "======================================"
echo "Arbeitszeiterfassung Projekt Test"
echo "======================================"
echo ""

# Farben für Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Basis-Verzeichnis (überschreibbar per Umgebungsvariable BASE_DIR)
# Fallback auf Verzeichnis der Skriptdatei, falls /app/AZE/ nicht existiert
BASE_DIR="${BASE_DIR:-/app/AZE/Arbeitszeiterfassung}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ALT_DIR="$SCRIPT_DIR/.."

if [ ! -d "$BASE_DIR" ] && [ -d "$ALT_DIR" ]; then
    BASE_DIR="$ALT_DIR"
fi

# Funktion für Erfolg/Fehler-Ausgabe
check_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}"
    else
        echo -e "${RED}✗ $2${NC}"
        ERRORS=$((ERRORS + 1))
    fi
}

# Fehler-Zähler
ERRORS=0

# 1. Prüfe Projektstruktur
echo -e "${YELLOW}1. Prüfe Projektstruktur...${NC}"

# Prüfe Hauptverzeichnis
if [ -d "$BASE_DIR" ]; then
    check_result 0 "Hauptverzeichnis existiert"
else
    check_result 1 "Hauptverzeichnis fehlt"
    echo -e "${RED}Abbruch: Projekt wurde noch nicht initialisiert!${NC}"
    echo "Führen Sie zuerst aus: bash /app/AZE/init-projekt.sh"
    echo "Oder starten Sie das Skript mit der Umgebungsvariable BASE_DIR"
    echo "z.B.: BASE_DIR=/pfad/zum/Projekt bash meta/test-projekt.sh"
    exit 1
fi

cd "$BASE_DIR"

# Prüfe Solution-Datei
test -f "Arbeitszeiterfassung.sln"
check_result $? "Solution-Datei vorhanden"

# Prüfe Projekte
for proj in "Common" "DAL" "BLL" "UI" "Tests"; do
    test -d "Arbeitszeiterfassung.$proj"
    check_result $? "Projekt Arbeitszeiterfassung.$proj existiert"
done

echo ""

# 2. Prüfe Projektverweise
echo -e "${YELLOW}2. Prüfe Projektverweise...${NC}"

# DAL sollte Common referenzieren
grep -q "Arbeitszeiterfassung.Common" "Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj" 2>/dev/null
check_result $? "DAL referenziert Common"

# BLL sollte Common und DAL referenzieren
grep -q "Arbeitszeiterfassung.Common" "Arbeitszeiterfassung.BLL/Arbeitszeiterfassung.BLL.csproj" 2>/dev/null
check_result $? "BLL referenziert Common"
grep -q "Arbeitszeiterfassung.DAL" "Arbeitszeiterfassung.BLL/Arbeitszeiterfassung.BLL.csproj" 2>/dev/null
check_result $? "BLL referenziert DAL"

# UI sollte alle referenzieren
grep -q "Arbeitszeiterfassung.Common" "Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj" 2>/dev/null
check_result $? "UI referenziert Common"
grep -q "Arbeitszeiterfassung.DAL" "Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj" 2>/dev/null
check_result $? "UI referenziert DAL"
grep -q "Arbeitszeiterfassung.BLL" "Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj" 2>/dev/null
check_result $? "UI referenziert BLL"

echo ""

# 3. Prüfe Konfigurationsdateien
echo -e "${YELLOW}3. Prüfe Konfigurationsdateien...${NC}"

test -f "Arbeitszeiterfassung.UI/appsettings.json"
check_result $? "appsettings.json vorhanden"


test -f ".gitignore"
check_result $? ".gitignore vorhanden"

echo ""

# 4. Prüfe NuGet-Pakete
echo -e "${YELLOW}4. Prüfe wichtige NuGet-Pakete...${NC}"

# Entity Framework in DAL
grep -q "Microsoft.EntityFrameworkCore" "Arbeitszeiterfassung.DAL/Arbeitszeiterfassung.DAL.csproj" 2>/dev/null
check_result $? "Entity Framework Core in DAL"

# Configuration in Common
grep -q "Microsoft.Extensions.Configuration" "Arbeitszeiterfassung.Common/Arbeitszeiterfassung.Common.csproj" 2>/dev/null
check_result $? "Configuration Extensions in Common"

# Test Framework
grep -q "xunit" "Arbeitszeiterfassung.Tests/Arbeitszeiterfassung.Tests.csproj" 2>/dev/null
check_result $? "xUnit in Tests"

echo ""

# 5. Versuche Build
echo -e "${YELLOW}5. Teste Build...${NC}"

# Baue alle Projekte außer der Windows-Forms-UI, da das WindowsDesktop SDK unter Linux nicht verfügbar ist
BUILD_RESULT=0
for proj in "Common" "DAL" "BLL" "Tests"; do
    dotnet build "Arbeitszeiterfassung.$proj/Arbeitszeiterfassung.$proj.csproj" -p:EnableWindowsTargeting=true > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        check_result 0 "Arbeitszeiterfassung.$proj lässt sich bauen"
    else
        check_result 1 "Arbeitszeiterfassung.$proj lässt sich bauen"
        BUILD_RESULT=1
    fi
done

# UI Projekt wird nur getestet, ob die Projektdatei existiert
test -f "Arbeitszeiterfassung.UI/Arbeitszeiterfassung.UI.csproj"
check_result $? "UI-Projekt vorhanden"

if [ $BUILD_RESULT -eq 0 ]; then
    # Prüfe ob alle Bibliotheken erzeugt wurden
    for proj in "Common" "DAL" "BLL" "Tests"; do
        if find Arbeitszeiterfassung.$proj/bin/Debug -name "Arbeitszeiterfassung.$proj.dll" | grep -q .; then
            check_result 0 "Arbeitszeiterfassung.$proj wurde gebaut"
        else
            check_result 1 "Arbeitszeiterfassung.$proj wurde gebaut"
        fi
    done

    # Unit-Tests ausführen
    echo ""
    echo -e "${YELLOW}6. Fuehre Unit-Tests aus...${NC}"
    dotnet test Arbeitszeiterfassung.Tests/Arbeitszeiterfassung.Tests.csproj --no-build --nologo > /tmp/unit_tests.log 2>&1
    if [ $? -eq 0 ]; then
        check_result 0 "Unit-Tests erfolgreich"
    else
        check_result 1 "Unit-Tests fehlgeschlagen"
        cat /tmp/unit_tests.log
    fi
else
    echo ""
    echo -e "${RED}Build fehlgeschlagen – Tests werden uebersprungen${NC}"
fi

echo ""

# 7. Prüfe Ordnerstruktur
echo -e "${YELLOW}7. Prüfe Ordnerstruktur...${NC}"

# Common
for folder in "Configuration" "Enums" "Extensions" "Helpers" "Models"; do
    test -d "Arbeitszeiterfassung.Common/$folder"
    check_result $? "Common/$folder existiert"
done

# DAL
for folder in "Context" "Entities" "Migrations" "Repositories"; do
    test -d "Arbeitszeiterfassung.DAL/$folder"
    check_result $? "DAL/$folder existiert"
done

echo ""

# 8. Zusammenfassung
echo -e "${YELLOW}======================================"
echo "Test-Zusammenfassung"
echo -e "======================================${NC}"

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Alle Tests bestanden!${NC}"
    echo ""
    echo "Das Projekt ist bereit für die Entwicklung."
    echo "Nächster Schritt: Hauptfenster implementieren"
    echo "Verwenden Sie: /app/AZE/Prompts/Schritt_4_1_Hauptfenster.md"
else
    echo -e "${RED}✗ $ERRORS Test(s) fehlgeschlagen!${NC}"
    echo ""
    echo "Bitte prüfen Sie die Fehler und führen Sie ggf."
    echo "das Initialisierungsskript erneut aus:"
    echo "bash /app/AZE/init-projekt.sh"
fi

echo ""
echo "Projektverzeichnis: $BASE_DIR"

# Exit mit Fehlercode wenn Tests fehlgeschlagen
exit $ERRORS


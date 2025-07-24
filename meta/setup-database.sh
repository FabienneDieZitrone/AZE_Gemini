#!/bin/bash
# ---
# title: Datenbank-Setup-Skript
# version: 1.0
# lastUpdated: 26.01.2025
# author: Tanja Trella
# status: Final
# file: /app/AZE/setup-database.sh
# description: Skript zur Einrichtung der MySQL/MariaDB-Datenbank für die Arbeitszeiterfassung
# ---

echo "======================================"
echo "Datenbank-Setup für Arbeitszeiterfassung"
echo "======================================"
echo ""

# Standardwerte
DB_HOST="wp10454681.Server-he.de"
DB_PORT="3306"
DB_NAME="db10454681-aze"
DB_USER="db10454681-aze"
DB_PASS="Start.321"

# Farben für Ausgabe
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Prüfe ob MySQL/MariaDB installiert ist
echo "Prüfe MySQL/MariaDB Installation..."
if ! command -v mysql &> /dev/null; then
    echo -e "${RED}❌ FEHLER: MySQL/MariaDB ist nicht installiert!${NC}"
    echo "Bitte installieren Sie MySQL oder MariaDB Server."
    echo ""
    echo "Ubuntu/Debian:"
    echo "  sudo apt update && sudo apt install mariadb-server"
    echo ""
    echo "CentOS/RHEL:"
    echo "  sudo yum install mariadb-server"
    echo ""
    exit 1
fi

echo -e "${GREEN}✓ MySQL/MariaDB gefunden${NC}"
echo ""

# Optional: Benutzerdefinierte Werte abfragen
read -p "Datenbank-Host [$DB_HOST]: " input_host
DB_HOST=${input_host:-$DB_HOST}

read -p "Datenbank-Port [$DB_PORT]: " input_port
DB_PORT=${input_port:-$DB_PORT}

read -p "Datenbank-Name [$DB_NAME]: " input_name
DB_NAME=${input_name:-$DB_NAME}

read -p "Datenbank-Benutzer [$DB_USER]: " input_user
DB_USER=${input_user:-$DB_USER}

echo -n "Datenbank-Passwort [$DB_PASS]: "
read -s input_pass
echo ""
DB_PASS=${input_pass:-$DB_PASS}

# SQL-Skript erstellen
echo ""
echo "Erstelle Datenbank-Setup-Skript..."

cat > /tmp/aze_db_setup.sql << EOF
-- Arbeitszeiterfassung Datenbank-Setup
-- Version: 1.0
-- Datum: $(date +%Y-%m-%d)

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE \`$DB_NAME\`;

-- Benutzer erstellen (falls nicht root)
-- CREATE USER IF NOT EXISTS 'aze_user'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO 'aze_user'@'localhost';
-- FLUSH PRIVILEGES;

-- Tabellen werden später durch Entity Framework erstellt
-- Hier nur grundlegende Einstellungen

-- Zeitzonen-Tabelle für korrekte Zeitbehandlung
CREATE TABLE IF NOT EXISTS db_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property VARCHAR(50) NOT NULL UNIQUE,
    value VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Basis-Informationen einfügen
INSERT INTO db_info (property, value) VALUES
    ('version', '1.0.0'),
    ('created', NOW()),
    ('timezone', 'Europe/Berlin')
ON DUPLICATE KEY UPDATE 
    value = VALUES(value),
    updated_at = NOW();

-- Status ausgeben
SELECT 'Datenbank-Setup abgeschlossen' AS Status;
SELECT property, value FROM db_info;
EOF

# Führe SQL-Skript aus
echo ""
echo "Führe Datenbank-Setup aus..."
echo -e "${YELLOW}Verbinde zu $DB_HOST:$DB_PORT als $DB_USER...${NC}"

mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" < /tmp/aze_db_setup.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Datenbank wurde erfolgreich eingerichtet!${NC}"
    
    # Teste Verbindung
    echo ""
    echo "Teste Datenbankverbindung..."
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 'Verbindung erfolgreich' AS Test;" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Verbindungstest erfolgreich!${NC}"
    else
        echo -e "${RED}✗ Verbindungstest fehlgeschlagen${NC}"
    fi
    
    # Erstelle Connection String für .NET
    echo ""
    echo "Connection String für appsettings.json:"
    echo -e "${YELLOW}Server=$DB_HOST;Port=$DB_PORT;Database=$DB_NAME;User=$DB_USER;Password=$DB_PASS;${NC}"
    
    # Speichere Connection String
    echo ""
    read -p "Connection String in Datei speichern? (j/n): " save_cs
    if [[ $save_cs =~ ^[Jj]$ ]]; then
        CS_FILE="/app/AZE/connection-string.txt"
        echo "Server=$DB_HOST;Port=$DB_PORT;Database=$DB_NAME;User=$DB_USER;Password=$DB_PASS;" > "$CS_FILE"
        echo -e "${GREEN}✓ Connection String gespeichert in: $CS_FILE${NC}"
        echo -e "${YELLOW}WICHTIG: Löschen Sie diese Datei nach der Konfiguration!${NC}"
    fi
    
else
    echo -e "${RED}✗ Datenbank-Setup fehlgeschlagen!${NC}"
    echo ""
    echo "Mögliche Ursachen:"
    echo "- MySQL/MariaDB Server läuft nicht"
    echo "- Falsches Passwort"
    echo "- Keine Berechtigung zum Erstellen von Datenbanken"
    echo ""
    echo "Prüfen Sie den MySQL-Status mit:"
    echo "  sudo systemctl status mysql"
    echo "oder"
    echo "  sudo systemctl status mariadb"
    exit 1
fi

# Aufräumen
rm -f /tmp/aze_db_setup.sql

echo ""
echo "======================================"
echo "Nächste Schritte:"
echo "======================================"
echo "1. Kopieren Sie den Connection String in appsettings.json"
echo "2. Führen Sie das Projekt-Setup aus: bash init-projekt.sh"
echo "3. Starten Sie mit Schritt 1.2: Datenbankdesign"
echo ""
echo "Entity Framework Migrations:"
echo "  cd Arbeitszeiterfassung/Arbeitszeiterfassung.DAL"
echo "  dotnet ef migrations add InitialCreate"
echo "  dotnet ef database update"


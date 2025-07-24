#!/bin/bash
# Titel: Umbenennungsskript für Server-Deployment & Entwicklung
# Version: 3.0
# Autor: MP-IT
# Beschreibung: Benennt Dateien für verschiedene Umgebungen um.
#              - 'deploy'-Modus: Konvertiert .txt-Dateien in ihre Zielformate (.php, .sql, .htaccess).
#              - 'dev'-Modus: Konvertiert Server-Dateien zurück in .txt für die lokale Bearbeitung.

# --- ANLEITUNG ---
# 1. (Einmalig) Machen Sie das Skript ausführbar:
#    chmod +x rename_files.sh
#
# 2. FÜR DEPLOYMENT (Dateien auf den Server hochladen):
#    ./rename_files.sh deploy
#    ODER (standardmäßig, wenn kein Argument angegeben wird):
#    ./rename_files.sh
#
# 3. FÜR LOKALE ENTWICKLUNG (Dateien zurück in .txt umbenennen):
#    ./rename_files.sh dev

# --- FÜR WINDOWS BENUTZER (Manuelle Schritte in CMD/PowerShell) ---
# Für Deployment:
# ren htaccess.txt .htaccess
# ren schema.txt schema.sql
# cd api
# ren *.txt *.php
# cd ..
#
# Für Entwicklung (zurück zu .txt):
# ren .htaccess htaccess.txt
# ren schema.sql schema.txt
# cd api
# ren *.php *.txt
# cd ..
# --------------------------------------------------

# --- Skriptlogik ---

MODE=${1:-deploy} # Standardmodus ist 'deploy'
API_DIR="api"

rename_to_server() {
    echo "Modus: Deployment. Konvertiere .txt-Dateien für den Server..."
    
    # 1. PHP-Dateien im 'api'-Ordner umbenennen
    if [ -d "$API_DIR" ]; then
        echo "Benenne API-Dateien von .txt zu .php um..."
        for file in "$API_DIR"/*.txt; do
            if [ -f "$file" ]; then
                mv -- "$file" "${file%.txt}.php"
                echo "  - ${file} -> ${file%.txt}.php"
            fi
        done
    else
        echo "Warnung: Verzeichnis '$API_DIR' nicht gefunden. Überspringe PHP-Dateien."
    fi

    # 2. SQL-Schemadatei umbenennen
    if [ -f "schema.txt" ]; then
        mv -- "schema.txt" "schema.sql"
        echo "Benenne schema.txt zu schema.sql um."
    fi

    # 3. htaccess-Datei umbenennen
    if [ -f "htaccess.txt" ]; then
        mv -- "htaccess.txt" ".htaccess"
        echo "Benenne htaccess.txt zu .htaccess um."
    fi
    
    echo "Deployment-Umbenennung abgeschlossen."
}

rename_to_dev() {
    echo "Modus: Entwicklung. Konvertiere Server-Dateien zurück zu .txt..."

    # 1. PHP-Dateien zurück zu .txt
    if [ -d "$API_DIR" ]; then
        echo "Benenne API-Dateien von .php zu .txt um..."
        for file in "$API_DIR"/*.php; do
            if [ -f "$file" ]; then
                mv -- "$file" "${file%.php}.txt"
                echo "  - ${file} -> ${file%.php}.txt"
            fi
        done
    else
        echo "Warnung: Verzeichnis '$API_DIR' nicht gefunden. Überspringe PHP-Dateien."
    fi

    # 2. SQL-Datei zurück zu .txt
    if [ -f "schema.sql" ]; then
        mv -- "schema.sql" "schema.txt"
        echo "Benenne schema.sql zu schema.txt um."
    fi

    # 3. .htaccess zurück zu .txt
    if [ -f ".htaccess" ]; then
        mv -- ".htaccess" "htaccess.txt"
        echo "Benenne .htaccess zu htaccess.txt um."
    fi

    echo "Entwicklungs-Umbenennung abgeschlossen."
}


if [ "$MODE" = "dev" ]; then
    rename_to_dev
else
    rename_to_server
fi

echo ""
echo "Skript erfolgreich ausgeführt."

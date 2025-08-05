# 🔐 Server-Admin: Deployment-Umgebungsvariablen einrichten

## Beschreibung
Alle Deployment-Skripte wurden auf Umgebungsvariablen umgestellt. Die alten Skripte mit hartcodierten Passwörtern funktionieren nicht mehr.

## Aufgaben

### 1. Sichere .env Datei erstellen
```bash
# Im Projekt-Root
cd /home/admin/deployments/aze-gemini
cp .env.example .env
chmod 600 .env
nano .env
```

### 2. Erforderliche Variablen setzen
```bash
# FTP/FTPS Konfiguration
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASS=<aktuelles-passwort>
FTP_PROD_PATH=/www/aze/
FTP_TEST_PATH=/www/aze-test/

# Datenbank (für Backups)
DB_HOST=vwp8374.webpack.hosteurope.de
DB_NAME=db10454681-aze
DB_USER=db10454681-aze
DB_PASS=<db-passwort>
```

### 3. Deployment-Wrapper erstellen
```bash
# Erstelle Wrapper-Skript
cat > deploy-wrapper.sh << 'EOF'
#!/bin/bash
# Lädt .env und führt Deployment aus

# Lade Umgebungsvariablen
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "ERROR: .env file not found!"
    exit 1
fi

# Führe gewünschtes Deployment aus
if [ $# -eq 0 ]; then
    echo "Usage: ./deploy-wrapper.sh <script.py>"
    echo "Available scripts:"
    ls -1 deploy_*.py
    exit 1
fi

python3 "$@"
EOF

chmod +x deploy-wrapper.sh
```

### 4. Neue Deployment-Prozedur testen
```bash
# Teste Verbindung
./deploy-wrapper.sh test-ftp-connection.sh

# Führe Deployment aus
./deploy-wrapper.sh deploy_production_final.py
```

### 5. Team-Dokumentation aktualisieren
- Alte Deployment-Anleitungen aktualisieren
- Team über neue Prozedur informieren
- Passwort-Rotation planen

## Sicherheitshinweise
- ⚠️ NIEMALS .env ins Git committen!
- 🔒 .env nur mit 600 Permissions
- 🔄 Passwörter regelmäßig rotieren
- 📝 Zugriffslog führen

## Priorität
🔴 **KRITISCH** - Ohne korrekte Env-Vars funktionieren Deployments nicht!

## Zeitaufwand
Ca. 20 Minuten

## Verifikation
- [ ] .env Datei erstellt mit korrekten Permissions
- [ ] Alle erforderlichen Variablen gesetzt
- [ ] Wrapper-Skript funktioniert
- [ ] Test-Deployment erfolgreich
- [ ] Team dokumentiert und informiert

## Labels
- server-admin
- security
- deployment
- critical

## Related
- Issue #31: Secure hardcoded credentials
- DEPLOYMENT_ENV_SETUP.md
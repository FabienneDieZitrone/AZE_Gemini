#!/bin/bash
# SFTP-Verbindung zu HostEurope konfigurieren
# Datum: 2025-07-24

# HostEurope SFTP-Zugangsdaten
SFTP_HOST="wp10454681.server-he.de"
SFTP_USER="ftp10454681-aze2"
SFTP_WEBUSER="wp10454681"
SFTP_PORT="22"

echo "=== HostEurope SFTP-Verbindung ==="
echo "Server: $SFTP_HOST"
echo "Benutzer: $SFTP_USER"
echo "Web-User: $SFTP_WEBUSER"
echo "Port: $SFTP_PORT"
echo

# Test der Verbindung
echo "Teste SFTP-Verbindung..."
if timeout 10s sftp -o ConnectTimeout=5 -P $SFTP_PORT $SFTP_USER@$SFTP_HOST <<< "exit" >/dev/null 2>&1; then
    echo "✅ SFTP-Server ist erreichbar"
else
    echo "⚠️  SFTP-Server-Test fehlgeschlagen (Normal ohne Passwort/Key)"
fi

echo
echo "=== Manuelle Verbindung ==="
echo "sftp -P $SFTP_PORT $SFTP_USER@$SFTP_HOST"
echo
echo "=== Interaktive Verbindung starten ==="
read -p "SFTP-Verbindung jetzt starten? (y/n): " answer
if [[ $answer == "y" || $answer == "Y" ]]; then
    echo "Starte SFTP-Verbindung..."
    sftp -P $SFTP_PORT $SFTP_USER@$SFTP_HOST
else
    echo "SFTP-Verbindung abgebrochen."
fi
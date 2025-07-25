#!/bin/bash
# SFTP-Verbindung zu HostEurope herstellen
# Basierend auf Recherche der HostEurope-Dokumentation

echo "=== HostEurope SFTP-Verbindung ==="
echo
echo "📋 Korrekte Verbindungsdaten (recherchiert):"
echo "   Server: ssh.server-he.de (HostEurope Standard SSH-Server)"
echo "   User: wp10454681"
echo "   Port: 22"
echo "   Passwort: MPintF2022!"
echo

echo "🔧 HostEurope-Spezifische Informationen:"
echo "   - SSH-Zugang muss im KIS aktiviert sein"
echo "   - Änderungen werden nach ~15 Minuten aktiv"
echo "   - Verwenden Sie SSH-Credentials, NICHT FTP-User"
echo "   - Server unterstützt: SSH, SCP, SFTP, rsync"
echo

echo "📁 Verfügbare Verzeichnisse auf HostEurope:"
echo "   /htdocs/ - Web-Root Verzeichnis"
echo "   /logs/ - Log-Dateien"
echo "   /ssl/ - SSL-Zertifikate"
echo

echo "🚀 SFTP-Verbindung starten..."
echo "   (Passwort: MPintF2022!)"
echo

# Host Key bereits bekannt machen (optional)
ssh-keyscan -H ssh.server-he.de >> ~/.ssh/known_hosts 2>/dev/null

# SFTP-Verbindung starten
sftp -o ConnectTimeout=10 wp10454681@ssh.server-he.de

echo
echo "=== Verbindung beendet ==="
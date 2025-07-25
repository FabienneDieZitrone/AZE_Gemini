#!/bin/bash
# Finale SFTP-Verbindung zu HostEurope
# Alle Recherche-Ergebnisse umgesetzt

echo "=== HostEurope SFTP - Finaler Verbindungstest ==="
echo
echo "📋 Basierend auf Recherche:"
echo "   ✅ SSH ist im KIS aktiviert"
echo "   ✅ Server: ssh.server-he.de"
echo "   ✅ Passwort: MPintF2022!"
echo "   ❓ SSH-User: Mehrere Varianten möglich"
echo

# Die wahrscheinlichsten User-Varianten
users=("wp10454681" "ftp10454681" "ftp10454681-aze2")

echo "🔧 Teste die wahrscheinlichsten SSH-User..."
echo

for user in "${users[@]}"; do
    echo "🔑 Teste User: $user"
    echo "   Kommando: sftp $user@ssh.server-he.de"
    echo "   Passwort: MPintF2022!"
    echo
    
    read -p "   Diesen User testen? (y/n/skip): " choice
    case $choice in
        y|Y)
            echo "   Starte SFTP-Verbindung..."
            sftp -o ConnectTimeout=15 $user@ssh.server-he.de
            if [ $? -eq 0 ]; then
                echo "   ✅ SUCCESS mit User: $user"
                echo
                echo "📝 Erfolgreiche Verbindungsdaten:"
                echo "   Server: ssh.server-he.de"
                echo "   User: $user"
                echo "   Passwort: MPintF2022!"
                exit 0
            else
                echo "   ❌ Verbindung mit $user fehlgeschlagen"
            fi
            ;;
        skip|s)
            echo "Überspringe Tests - starte direkt häufigsten User..."
            user="wp10454681"
            break
            ;;
        *)
            echo "   → Übersprungen"
            ;;
    esac
    echo
done

echo "🚀 Direkte Verbindung mit wahrscheinlichstem User: wp10454681"
echo "   Bei Password-Prompt eingeben: MPintF2022!"
echo
sftp -o ConnectTimeout=15 wp10454681@ssh.server-he.de

echo
echo "=== Test beendet ==="
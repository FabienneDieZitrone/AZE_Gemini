# HostEurope Deployment Guide

## 🔧 SFTP-Verbindung zu HostEurope

### Verbindungsdaten:
- **Server**: `wp10454681.server-he.de`
- **SFTP-Benutzer**: `ftp10454681-aze2`
- **Web-Benutzer**: `wp10454681`
- **Standard-Port**: 22 (SFTP)

### ⚠️ Verbindungsprobleme identifiziert:

**Problem**: Port 22 (SFTP) scheint gesperrt oder nicht verfügbar
- Connection refused auf Standard-SFTP-Port
- Möglicherweise nur FTP (Port 21) oder Web-Upload verfügbar

## 🛠️ Alternative Deployment-Methoden

### Option 1: Web-Interface Upload
1. Über HostEurope Control Panel einloggen
2. Dateimanager öffnen
3. Dateien manuell in `/htdocs` hochladen

### Option 2: FTP-Client verwenden
```bash
# Falls FTP verfügbar (zu testen):
ftp wp10454681.server-he.de
# Login: ftp10454681-aze2
# Passwort: [vom Hosting-Provider]
```

### Option 3: Git-basiertes Deployment
```bash
# Falls Git auf Server verfügbar:
ssh wp10454681@wp10454681.server-he.de
cd /htdocs
git clone https://github.com/FabienneDieZitrone/AZE_Gemini.git .
cd build
# Web-App Setup...
```

## 📋 Deployment-Checkliste

### Dateien zum Upload:
```
build/
├── dist/              # React Production Build
├── api/               # PHP Backend Files
├── config.php         # Sichere Konfiguration
├── .env.example       # Environment Template
├── schema.sql         # Datenbank-Schema
└── README.md          # Dokumentation
```

### Nach dem Upload:
1. ✅ `.env` Datei auf Server erstellen:
   ```bash
   # Auf Server:
   cp .env.example .env
   # DB-Credentials für HostEurope eintragen
   ```

2. ✅ MySQL-Datenbank importieren:
   ```sql
   -- Via phpMyAdmin oder MySQL-Client:
   mysql -u db10454681-aze -p db10454681-aze < schema.sql
   ```

3. ✅ PHP-Konfiguration prüfen:
   - mysqli Extension aktiv?
   - PHP Version 8.0+?
   - Ausreichend Memory Limit?

4. ✅ Azure AD OAuth2 konfigurieren:
   - Redirect URL: `https://wp10454681.server-he.de/api/auth-callback.php`
   - Logout URL: `https://wp10454681.server-he.de/api/auth-logout.php`

5. ✅ Berechtigungen setzen:
   ```bash
   chmod 644 *.php
   chmod 600 .env
   chmod 755 api/
   ```

## 🚀 Automatisierte Scripts

### SFTP-Verbindung testen:
```bash
./deployment/sftp-config.sh
```

### Vollständiges Deployment:
```bash
./deployment/deploy-to-hosteurope.sh
```

**Hinweis**: Scripts sind vorbereitet, aber SFTP-Verbindung muss erst funktionieren!

## 🔍 Troubleshooting

### SFTP-Verbindung fehlschlägt:
1. **Port prüfen**: Möglicherweise nicht Port 22
2. **Protokoll**: Eventuell nur FTP, nicht SFTP
3. **Firewall**: Container-Netzwerk könnte blockiert sein
4. **Credentials**: Passwort vom Hosting-Provider erforderlich

### Alternative Lösungen:
1. **Web-Upload**: Control Panel nutzen
2. **FTP-Client**: FileZilla oder ähnlich
3. **Local Development**: Web-App lokal testen, dann Upload

## 📞 HostEurope Support

Falls SFTP weiterhin nicht funktioniert:
- **Support kontaktieren**: Welche Protokolle sind verfügbar?
- **SSH-Zugang**: Ist Shell-Zugang möglich?
- **Git-Support**: Kann direkt vom Repository deployed werden?

---

**Status**: SFTP-Verbindung problematisch - Alternative Methoden verwenden  
**Letztes Update**: 2025-07-24
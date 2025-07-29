# 🔒 Sichere Deployment-Anleitung - AZE_Gemini

## Überblick

Diese Anleitung erklärt, wie AZE_Gemini sicher deployed wird, ohne Zugangsdaten preiszugeben.

## ⚠️ Sicherheit zuerst

**NIEMALS** Zugangsdaten in Git committen. Dies umfasst:
- FTP-Passwörter
- Datenbank-Passwörter
- API-Schlüssel
- OAuth-Secrets

## 🚀 Deployment-Einrichtung

### 1. Ersteinrichtung (Einmalig pro Umgebung)

```bash
# Beispiel-Umgebungsdatei kopieren
cp .env.example .env.local

# .env.local mit Ihren Zugangsdaten bearbeiten
nano .env.local
```

Fügen Sie Ihre tatsächlichen Zugangsdaten zu `.env.local` hinzu:
```
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASSWORD=ihr-tatsächliches-passwort
```

### 2. Deployment

```bash
# Alles deployen
./deploy-secure.sh

# Nur Frontend deployen
./deploy-secure.sh frontend

# Nur Backend deployen
./deploy-secure.sh backend
```

## 🔐 Für zukünftige Sitzungen

### Option 1: Umgebungsdatei (Empfohlen)
1. `.env.local` mit Zugangsdaten erstellen
2. Das Deploy-Skript lädt sie automatisch
3. `.env.local` nach Deployment löschen, falls in geteilter Umgebung

### Option 2: Umgebungsvariablen
```bash
export FTP_HOST="wp10454681.server-he.de"
export FTP_USER="ftp10454681-aze"
export FTP_PASSWORD="ihr-passwort"
./deploy-secure.sh
```

### Option 3: Interaktive Eingabe
```bash
# Das Skript fragt nach fehlenden Zugangsdaten
./deploy-secure.sh
```

## 🛡️ Sicherheits-Checkliste

- [ ] `.env.local` ist in `.gitignore`
- [ ] Keine Passwörter in committeten Dateien
- [ ] Pre-commit Hook ist aktiv
- [ ] Zugangsdaten über sicheren Kanal erhalten
- [ ] Deployment-Logs enthalten keine Passwörter

## 📋 Optionen zur Speicherung von Zugangsdaten

### Für CI/CD (GitHub Actions)
Repository Secrets verwenden:
1. Zu Einstellungen → Secrets → Actions gehen
2. Hinzufügen: `FTP_USER`, `FTP_PASS`
3. Im Workflow verwenden: `${{ secrets.FTP_USER }}`

### Für lokale Entwicklung
`.env.local` Datei verwenden (niemals committen!)

### Für Produktions-Deployment
Umgebungsvariablen oder Secret Management Service verwenden

## 🚨 Falls Zugangsdaten kompromittiert wurden

1. **Sofort** das FTP-Passwort ändern
2. Server-Logs auf unbefugten Zugriff prüfen
3. Bei Bedarf Git-Historie bereinigen
4. Das Team benachrichtigen

## 🔧 Fehlerbehebung

### "Fehlende erforderliche Umgebungsvariablen"
- Sicherstellen, dass `.env.local` existiert und alle Variablen enthält
- Dateiberechtigungen prüfen: `chmod 600 .env.local`

### "Zugriff verweigert"
- Skript ausführbar machen: `chmod +x deploy-secure.sh`

### "Upload fehlgeschlagen"
- Zugangsdaten auf Korrektheit überprüfen
- FTP-Server-Erreichbarkeit prüfen
- Sicherstellen, dass SSL/TLS aktiviert ist

## 📝 Best Practices

1. **Zugangsdaten regelmäßig rotieren**
2. **Starke, einzigartige Passwörter verwenden**
3. **Zugriffsprotokolle überwachen**
4. **`.env.local` Berechtigungen restriktiv halten**
5. **Zugangsdaten niemals über unsichere Kanäle teilen**

## 🔍 Sicherheitsvalidierung

Führen Sie dies aus, um exponierte Zugangsdaten zu prüfen:
```bash
# Passwörter in Git-Historie prüfen
git log -p | grep -E "(password|secret|token|321Start)"

# Aktuelle Dateien prüfen
grep -r "FTP_PASS" . --exclude-dir=node_modules --exclude-dir=.git
```

---

**Denken Sie daran**: Sicherheit ist die Verantwortung aller. Im Zweifelsfall lieber um Hilfe bitten, als ein Risiko einzugehen.
# 🔐 Secure Credentials Management Guide

## ⚠️ WICHTIG: Passwort-Rotation erforderlich!

Das aktuelle FTP-Passwort wurde in der Konversation exponiert und muss SOFORT geändert werden.

## 📋 Empfohlene neue Credentials:

### FTP-Zugang:
```
Host: wp10454681.server-he.de
User: ftp10454681-aze
Neues Passwort: [MUSS VOM HOSTING-PROVIDER GEÄNDERT WERDEN]
```

### Empfohlenes sicheres Passwort-Schema:
- Mindestens 20 Zeichen
- Mix aus Groß-/Kleinbuchstaben, Zahlen und Sonderzeichen
- Keine Wörter aus dem Wörterbuch
- Keine persönlichen Informationen
- Beispiel-Generator: `openssl rand -base64 20`

## 🛡️ Sichere Speicherung:

### 1. Lokal (.env.local):
```bash
# Erstellen
nano .env.local

# Inhalt:
FTP_HOST=wp10454681.server-he.de
FTP_USER=ftp10454681-aze
FTP_PASSWORD=IHR_NEUES_SICHERES_PASSWORT
```

### 2. Berechtigungen setzen:
```bash
chmod 600 .env.local
```

### 3. Niemals committen:
- ✅ .env.local ist in .gitignore
- ✅ Pre-commit hooks verhindern versehentliches committen
- ❌ Niemals Passwörter in Dokumentation schreiben

## 🔄 Passwort-Rotation Prozess:

1. **Im Hosting-Panel**:
   - FTP-Benutzer auswählen
   - Neues Passwort generieren/setzen
   - Passwort sicher notieren

2. **Lokal aktualisieren**:
   ```bash
   # .env.local bearbeiten
   nano .env.local
   # Neues Passwort eintragen
   ```

3. **Verbindung testen**:
   ```bash
   ./deploy-secure.sh
   ```

## 🚨 Bei Kompromittierung:

1. **SOFORT** Passwort ändern
2. Server-Logs auf unbefugten Zugriff prüfen
3. Alle deployten Dateien auf Manipulation prüfen
4. Team informieren

## 📊 Passwort-Stärke-Kriterien:

| Kriterium | Minimum | Empfohlen |
|-----------|---------|-----------|
| Länge | 16 Zeichen | 20+ Zeichen |
| Großbuchstaben | 2 | 4+ |
| Kleinbuchstaben | 2 | 4+ |
| Zahlen | 2 | 4+ |
| Sonderzeichen | 2 | 4+ |
| Entropie | 80 Bit | 100+ Bit |

## 🔧 Passwort-Generatoren:

### Linux/Mac:
```bash
# OpenSSL
openssl rand -base64 20

# Python
python3 -c "import secrets; import string; print(''.join(secrets.choice(string.ascii_letters + string.digits + '!@#$%*-_=+') for _ in range(20)))"

# pwgen
pwgen -sy 20 1
```

### Online (nur vertrauenswürdige):
- Bitwarden Generator
- 1Password Generator
- KeePass

## ✅ Best Practices:

1. **Unterschiedliche Passwörter** für jeden Service
2. **Passwort-Manager** verwenden
3. **2FA aktivieren** wo möglich
4. **Regelmäßige Rotation** (alle 90 Tage)
5. **Sichere Übertragung** (nur verschlüsselt)

## 🔒 Aktueller Sicherheitsstatus:

- ✅ Deployment-System verwendet Environment Variables
- ✅ Pre-commit Hooks aktiv
- ✅ SSL/TLS für FTP-Verbindungen
- ⚠️ FTP-Passwort muss rotiert werden
- ✅ Keine Passwörter im Code

---

**Nächster Schritt**: Kontaktieren Sie Ihren Hosting-Provider und ändern Sie das FTP-Passwort!
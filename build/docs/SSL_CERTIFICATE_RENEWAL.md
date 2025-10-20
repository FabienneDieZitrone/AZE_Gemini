# 🔒 SSL-Zertifikat Verlängerung - aze.mikropartner.de

**Autor**: Günnix
**Datum**: 2025-10-21
**Status**: ⚠️ **KRITISCH - Zertifikat abgelaufen!**

---

## 🚨 AKTUELLER STATUS

**Domain**: `aze.mikropartner.de`
**Zertifikat-Typ**: Let's Encrypt
**Ausgestellt**: 21. Juli 2025
**Läuft ab**: **19. Oktober 2025**
**Heutiges Datum**: 21. Oktober 2025

**⚠️ STATUS: ABGELAUFEN (seit 2 Tagen)**

```
Certificate Details:
notBefore=Jul 21 11:34:13 2025 GMT
notAfter=Oct 19 11:34:12 2025 GMT
subject=CN=aze.mikropartner.de
issuer=C=US, O=Let's Encrypt, CN=E6
```

---

## ⚠️ Wichtige Information

**Ich (Claude Code) kann das Zertifikat NICHT direkt erneuern**, weil:
1. ❌ Kein Shell/SSH-Zugriff zum Server vorhanden
2. ❌ Nur FTP-Zugriff verfügbar (nicht ausreichend für Let's Encrypt)
3. ✅ Let's Encrypt erfordert entweder:
   - Shell-Zugriff (certbot)
   - Hosting-Panel-Zugriff (HostEurope KIS)
   - API-Zugriff zum Hosting-Provider

---

## ✅ Lösungsoptionen

### Option 1: HostEurope KIS (Web-Panel) - EMPFOHLEN ⭐

**Vorteile**:
- ✅ Einfachste Methode
- ✅ Kein technisches Wissen erforderlich
- ✅ Automatische Verlängerung kann aktiviert werden

**Schritte**:

1. **Login ins HostEurope KIS**:
   - URL: https://kis.hosteurope.de
   - Account: wp10454681 (Ihr Webhosting-Account)

2. **SSL/TLS Zertifikate verwalten**:
   - Menü: "Domains & SSL" oder "SSL-Zertifikate"
   - Domain auswählen: `aze.mikropartner.de`

3. **Let's Encrypt Zertifikat erneuern**:
   - Button: "Zertifikat erneuern" oder "Let's Encrypt verlängern"
   - Bestätigen
   - Warten (ca. 1-5 Minuten)

4. **Automatische Verlängerung aktivieren**:
   - Option: "Auto-Renewal" oder "Automatische Verlängerung"
   - Aktivieren ✅
   - Zukünftig wird das Zertifikat automatisch verlängert

5. **Verifizieren**:
   ```bash
   # Prüfe neues Zertifikat
   echo | openssl s_client -servername aze.mikropartner.de -connect aze.mikropartner.de:443 2>/dev/null | openssl x509 -noout -dates
   ```

**Erwartung**: Neues Ablaufdatum ca. 90 Tage in der Zukunft

---

### Option 2: HostEurope Support kontaktieren

**Vorteile**:
- ✅ Support erledigt alles für Sie
- ✅ Garantiert korrekte Konfiguration

**Schritte**:

1. **HostEurope Support kontaktieren**:
   - Support-Hotline: 0221 / 99999 - 0
   - Support-Email: support@hosteurope.de
   - Ticket-System: https://kis.hosteurope.de → Support

2. **Angeben**:
   ```
   Betreff: SSL-Zertifikat für aze.mikropartner.de abgelaufen

   Sehr geehrtes HostEurope-Team,

   das Let's Encrypt SSL-Zertifikat für meine Domain aze.mikropartner.de
   ist am 19.10.2025 abgelaufen.

   Bitte erneuern Sie das Zertifikat und aktivieren Sie die automatische
   Verlängerung für die Zukunft.

   Webhosting-Account: wp10454681
   Domain: aze.mikropartner.de

   Vielen Dank!
   ```

3. **Warten auf Support-Antwort** (üblicherweise 1-24 Stunden)

---

### Option 3: SSH-Zugriff für Claude Code bereitstellen

**Vorteile**:
- ✅ Ich kann certbot direkt ausführen
- ✅ Automatische Verlängerung kann konfiguriert werden
- ✅ Vollständige Kontrolle

**Nachteile**:
- ⚠️ Erfordert SSH-Zugang zum Server
- ⚠️ HostEurope Webhosting-Pakete haben oft KEINEN SSH-Zugang

**Prüfen ob SSH verfügbar ist**:

1. **SSH-Zugang testen**:
   ```bash
   ssh wp10454681@wp10454681.server-he.de
   # ODER
   ssh ftp10454681-aze@wp10454681.server-he.de
   ```

2. **Falls SSH NICHT funktioniert**:
   - HostEurope Webhosting-Pakete haben oft keinen SSH-Zugang
   - Nur bei höheren Paketen (Managed Server, VPS) verfügbar
   - → Nutzen Sie **Option 1 (KIS)** oder **Option 2 (Support)**

3. **Falls SSH funktioniert**:
   ```bash
   # Ich kann dann certbot ausführen:
   sudo certbot renew
   # ODER
   sudo certbot certonly --webroot -w /www/it/aze -d aze.mikropartner.de
   ```

---

## 📋 Nach der Verlängerung - Verifikation

### 1. Zertifikat-Details prüfen

```bash
echo | openssl s_client -servername aze.mikropartner.de -connect aze.mikropartner.de:443 2>/dev/null | openssl x509 -noout -dates -subject -issuer
```

**Erwartung**:
```
notBefore=Oct 21 XX:XX:XX 2025 GMT
notAfter=Jan 19 XX:XX:XX 2026 GMT  # ~90 Tage später
subject=CN=aze.mikropartner.de
issuer=C=US, O=Let's Encrypt, CN=...
```

### 2. HTTPS funktioniert ohne Warnung

```bash
curl -I https://aze.mikropartner.de/
```

**Erwartung**: HTTP 200 OK ohne SSL-Fehler

### 3. Browser-Test

1. Öffne: https://aze.mikropartner.de
2. Klicke auf Schloss-Symbol in Adressleiste
3. "Zertifikat anzeigen"
4. Prüfe "Gültig bis" Datum

**Erwartung**: Kein Browser-Warnung, neues Ablaufdatum sichtbar

---

## 🔧 Automatische Verlängerung (WICHTIG!)

**Um zukünftige Ablaufprobleme zu vermeiden:**

### Im HostEurope KIS:
1. Login: https://kis.hosteurope.de
2. Domains & SSL → aze.mikropartner.de
3. **Auto-Renewal aktivieren** ✅

### Falls SSH-Zugang vorhanden:
```bash
# Certbot Auto-Renewal testen
sudo certbot renew --dry-run

# Cron-Job prüfen (sollte automatisch erstellt sein)
sudo crontab -l | grep certbot
```

**Erwartung**: Cron-Job läuft 2x täglich und erneuert Zertifikate automatisch

---

## 🆘 Troubleshooting

### Problem: "Domain nicht erreichbar" nach Verlängerung

**Ursache**: DNS-Propagation oder Server-Neustart erforderlich

**Fix**:
1. Warten Sie 5-10 Minuten
2. Browser-Cache leeren (Strg+Shift+R)
3. Falls weiterhin Problem: HostEurope Support kontaktieren

### Problem: "Zertifikat wird nicht aktualisiert"

**Ursache**: Apache/nginx muss neu geladen werden

**Fix** (falls SSH-Zugang):
```bash
# Apache
sudo systemctl reload apache2

# nginx
sudo systemctl reload nginx
```

**Falls KEIN SSH-Zugang**: HostEurope Support kontaktieren

### Problem: "Let's Encrypt Rate Limit erreicht"

**Ursache**: Zu viele Verlängerungsversuche in kurzer Zeit

**Fix**:
- Warten Sie 1 Stunde
- Maximal 5 Verlängerungen pro Woche pro Domain
- Bei weiteren Problemen: Support kontaktieren

---

## 📞 HostEurope Support-Kontakt

**Telefon**:
- Deutschland: 0221 / 99999 - 0
- International: +49 221 99999 - 0
- Zeiten: Mo-Fr 9-20 Uhr, Sa 10-16 Uhr

**Email**:
- support@hosteurope.de

**Ticket-System**:
- https://kis.hosteurope.de → Support → Neues Ticket

**Web-Panel (KIS)**:
- https://kis.hosteurope.de
- Account: wp10454681

---

## 📚 Weiterführende Informationen

- **Let's Encrypt Dokumentation**: https://letsencrypt.org/docs/
- **HostEurope SSL-Hilfe**: https://www.hosteurope.de/faq/ssl-zertifikate/
- **Certbot Dokumentation**: https://certbot.eff.org/

---

## ✅ Empfohlene Vorgehensweise (Zusammenfassung)

1. **Sofort**: Login ins HostEurope KIS → SSL erneuern (Option 1)
2. **Wichtig**: Auto-Renewal aktivieren
3. **Verifizieren**: Neues Zertifikat prüfen
4. **Zukunft**: Monitoring einrichten (Email-Benachrichtigung bei Ablauf)

**Zeitaufwand**: 5-10 Minuten
**Technische Kenntnisse**: Minimal (nur KIS-Login erforderlich)

---

## 🔔 Monitoring-Empfehlung

**Um zukünftige Ablaufprobleme zu vermeiden:**

1. **SSL-Monitoring-Service** (kostenlos):
   - https://www.ssllabs.com/ssltest/ (einmalig)
   - https://uptimerobot.com/ (kontinuierlich)
   - https://monitor.cert-monitoring.io/ (speziell für SSL)

2. **Email-Benachrichtigungen**:
   - Let's Encrypt sendet automatisch Emails 30 Tage vor Ablauf
   - Prüfen Sie Ihre Admin-Email-Adresse im KIS

3. **Manueller Check** (monatlich):
   ```bash
   echo | openssl s_client -servername aze.mikropartner.de -connect aze.mikropartner.de:443 2>/dev/null | openssl x509 -noout -dates
   ```

---

**⚠️ WICHTIG**: Das Zertifikat ist bereits abgelaufen. Bitte erneuern Sie es schnellstmöglich über das HostEurope KIS oder kontaktieren Sie den Support.

**Priorität**: 🔴 **KRITISCH** - Sofortige Maßnahme erforderlich

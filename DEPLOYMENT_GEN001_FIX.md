# Deployment-Anleitung: GEN_001 Error Fix

**Datum**: 2025-10-20
**Fehlercode**: GEN_001 nach Login/Start-Button
**Commit**: Siehe Git-Log für letzten Commit
**Dateien geändert**: 3 (TimerService.tsx, useSupervisorNotifications.ts, MainAppView.tsx)

## 🎯 Zusammenfassung der Änderungen

### Behobene Probleme:
1. **MainAppView.tsx**: Null-Access bei `masterData[currentUser.id]` Struktur
2. **useSupervisorNotifications.ts**: Undefined-Access bei `globalSettings.overtimeThreshold`
3. **TimerService.tsx**: Fehlende Timer-ID in Server-Response

### Art der Änderungen:
- **Nur defensive Validierungen** (keine funktionalen Änderungen)
- **Keine Breaking Changes**
- **Build erfolgreich**: 6.38s, keine Fehler

---

## 📋 PRE-DEPLOYMENT CHECKLIST

### ✅ Vorbereitung (auf lokalem System):

- [ ] **1. Backup erstellen**:
  ```bash
  # Aktuelles Produktionssystem sichern
  ssh user@aze.mikropartner.de
  cd /var/www/html/aze
  tar -czf backup_aze_$(date +%Y%m%d_%H%M%S).tar.gz dist/ api/
  mv backup_aze_*.tar.gz ~/backups/
  ```

- [ ] **2. Build-Dateien validieren**:
  ```bash
  cd /home/aios/projekte/aze-gemini/claude-container/projekt/build
  ls -lh dist/  # Prüfen: index.html, assets/, assets/index-*.js
  ```

- [ ] **3. Browser-Cache-Warnung vorbereiten**:
  ```
  Benutzer MÜSSEN nach Deployment Strg+Shift+R drücken (Hard Reload)
  ```

---

## 🚀 DEPLOYMENT-PROZESS

### Schritt 1: FTP-Upload (FileZilla)

**FTP-Zugangsdaten**: Siehe `projekt/build/docs/DEPLOYMENT_FTP_ACCESS.md`

1. **Verbindung herstellen**:
   - Host: `aze.mikropartner.de`
   - Protokoll: SFTP (SSH File Transfer Protocol)
   - Port: 22
   - Benutzer: [siehe docs/DEPLOYMENT_FTP_ACCESS.md]

2. **Upload durchführen**:
   ```
   Lokal: /home/aios/projekte/aze-gemini/claude-container/projekt/build/dist/
   Remote: /var/www/html/aze/dist/

   Aktion: dist/ komplett überschreiben
   ```

3. **Wichtige Dateien prüfen**:
   - ✅ `dist/index.html` (neuestes Datum)
   - ✅ `dist/assets/index-[hash].js` (neuer Hash)
   - ✅ `dist/assets/index-[hash].css` (neuer Hash)

### Schritt 2: Permissions prüfen

```bash
ssh user@aze.mikropartner.de
cd /var/www/html/aze/dist
chmod 644 index.html
chmod 644 assets/*
chmod 755 .
```

### Schritt 3: Browser-Cache invalidieren

**Alle Benutzer MÜSSEN**:
1. Firefox: `Strg + Shift + R`
2. Chrome: `Strg + Shift + R`
3. Edge: `Strg + F5`

---

## 🧪 POST-DEPLOYMENT VERIFICATION

### Test 1: Login-Flow
```
1. Öffnen: https://aze.mikropartner.de
2. Login mit Testuser
3. Erwartung: Erfolgreicher Login, Dashboard lädt
```

### Test 2: Timer-Start (Haupttest!)
```
1. Nach erfolgreichem Login
2. Klick auf "Start" Button
3. Erwartung:
   - ✅ Timer startet ohne Fehler
   - ✅ KEIN GEN_001 Error
   - ✅ Zeit wird angezeigt
```

### Test 3: Console-Validierung
```
1. F12 → Console öffnen
2. Seite neu laden (Strg+Shift+R)
3. Login durchführen
4. "Start" klicken
5. Erwartung: KEINE "Unmapped error" Meldung
```

### Test 4: Supervisor-User
```
1. Login als Bereichsleiter/Admin
2. Navigation zu Stammdaten
3. Erwartung: Keine Fehler beim Laden
```

---

## 🔄 ROLLBACK-PROZESS (Falls Fehler auftreten)

### Schneller Rollback (5 Minuten)

```bash
# 1. SSH-Verbindung herstellen
ssh user@aze.mikropartner.de

# 2. Zum Backup-Verzeichnis navigieren
cd ~/backups

# 3. Neuestes Backup identifizieren
ls -lt backup_aze_*.tar.gz | head -1

# 4. Backup wiederherstellen
cd /var/www/html/aze
tar -xzf ~/backups/backup_aze_YYYYMMDD_HHMMSS.tar.gz

# 5. Permissions setzen
chmod 644 dist/index.html dist/assets/*
chmod 755 dist dist/assets

# 6. Browser-Cache leeren (alle Benutzer!)
```

### Rollback-Validierung

- [ ] `https://aze.mikropartner.de` lädt erfolgreich
- [ ] Login funktioniert
- [ ] Dashboard zeigt Daten an
- [ ] Keine Console-Errors

---

## 📊 MONITORING (48h nach Deployment)

### Zu überwachen:

1. **Server-Logs**:
   ```bash
   ssh user@aze.mikropartner.de
   tail -f /var/log/apache2/error.log | grep "aze"
   ```

2. **PHP-Error-Log**:
   ```bash
   tail -f /var/www/html/aze/api/error.log
   ```

3. **Benutzer-Feedback**:
   - Rückgang von "GEN_001" Support-Tickets?
   - Neue Fehlermeldungen?

---

## 🆘 TROUBLESHOOTING

### Problem: "Seite lädt nicht"
**Ursache**: Browser-Cache
**Lösung**: `Strg + Shift + R` (Hard Reload)

### Problem: "Noch GEN_001 Fehler"
**Ursache**: Anderer Root Cause als erwartet
**Lösung**:
1. Console öffnen (F12)
2. `error: {...}` Objekt expandieren
3. `error.message` und `error.stack` dokumentieren
4. GitHub Issue erstellen mit Details

### Problem: "Timer startet nicht"
**Ursache**: Backend-API-Problem
**Lösung**:
1. PHP-Logs prüfen: `/var/www/html/aze/api/error.log`
2. Session-Status prüfen: `curl -I https://aze.mikropartner.de/api/auth-status.php`
3. Rollback durchführen (siehe oben)

---

## 📞 SUPPORT-KONTAKTE

- **MP-IT Support**: [Support-Kontakt aus Docs]
- **GitHub Issues**: https://github.com/[REPO]/issues
- **Kritische Session-Probleme**: `projekt/build/docs/SESSION_LOGIN_TROUBLESHOOTING.md`

---

## ✅ DEPLOYMENT COMPLETION CHECKLIST

Nach erfolgreichem Deployment:

- [ ] Alle 4 Post-Deployment-Tests erfolgreich
- [ ] Kein GEN_001 Error bei Start-Button
- [ ] Console zeigt keine Unmapped Errors
- [ ] Backup erstellt und verifiziert
- [ ] Monitoring für 48h aktiv
- [ ] Benutzer über mögliche Cache-Probleme informiert
- [ ] Deployment-Zeitpunkt dokumentiert: __________

---

**Status**: ⏳ Bereit für Deployment
**Risiko**: 🟢 Niedrig (nur defensive Validierungen)
**Rollback-Zeit**: 🟢 5 Minuten
**Empfehlung**: ✅ Deployment durchführen

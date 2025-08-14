# 🚨 KRITISCH: Falsche index.html auf Production Server!

## ❌ PROBLEM IDENTIFIZIERT

Die **DEVELOPMENT** index.html wurde deployed statt der **PRODUCTION** Version!

### Was läuft falsch:
- Server liefert `/build/index.html` (Development) aus
- Diese versucht `/src/index.tsx` zu laden (TypeScript!)
- Browser blockiert wegen falschem MIME-Type (bekommt HTML statt JavaScript)
- **Azure Login funktioniert nicht**, weil die App gar nicht lädt!

### Falsche Datei (aktuell online):
```html
<!-- /build/index.html - DEVELOPMENT VERSION -->
<script type="module" src="/src/index.tsx"></script>
<script type="module" src="/index.tsx"></script>
```

### Richtige Datei (sollte online sein):
```html
<!-- /build/dist/index.html - PRODUCTION VERSION -->
<script type="module" src="/assets/index-[hash].js"></script>
```

## ✅ SOFORT-LÖSUNG

### Option 1: Korrektes Deployment (FTP)
```bash
# Aus dem lokalen Projekt:
cd /home/aios/projekte/aze-gemini/claude-container/projekt/build

# Build erstellen (falls nicht vorhanden):
npm run build

# Nur den INHALT von dist/ hochladen:
# Upload: dist/* → Server Root
# NICHT: dist/ selbst als Ordner!
```

### Option 2: Quick-Fix auf Server
Falls Sie Server-Zugriff haben:
1. Navigieren Sie zum Web-Root
2. Löschen Sie die aktuelle index.html
3. Kopieren Sie alle Dateien aus dem dist/ Unterordner ins Root:
   ```bash
   cp -r dist/* .
   rm -rf dist/
   ```

### Option 3: .htaccess Redirect (Workaround)
Erstellen Sie eine `.htaccess` im Server-Root:
```apache
# Redirect root to dist folder
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/dist/
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^(.*)$ /dist/$1 [L]
```

## 📁 KORREKTE DEPLOYMENT-STRUKTUR

```
Server-Root (aze.mikropartner.de/)
├── index.html          # Von dist/index.html
├── assets/             # Von dist/assets/
│   ├── index-xxxxx.js  # Gebaute JavaScript-Datei
│   └── index-xxxxx.css # Gebaute CSS-Datei
├── api/                # PHP Backend
│   ├── login.php
│   ├── time-entries.php
│   └── ... (OHNE debug-*.php files!)
└── .htaccess          # Server-Konfiguration
```

## ⚠️ WICHTIG

1. **NIEMALS** die Development index.html deployen!
2. **IMMER** `npm run build` vor Deployment
3. **NUR** den Inhalt von `dist/` hochladen
4. **ENTFERNEN** Sie die 14 Debug-PHP-Files aus `/api/`

## 🔥 DRINGLICHKEIT

**KRITISCH**: Die Anwendung ist aktuell NICHT FUNKTIONAL!
- Keine Benutzer können sich anmelden
- Azure AD Integration funktioniert nicht
- Business Impact: 100% Ausfall

**Geschätzte Reparaturzeit**: 5 Minuten nach korrektem Upload

---
**Erstellt**: 14.08.2025 12:25
**Problem**: Development statt Production Files deployed
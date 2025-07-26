# 🔧 **Troubleshooting**

## **Häufige Probleme:**

### 1. **"npm install" schlägt fehl**:
```bash
node --version    # Node.js 18+ erforderlich
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

### 2. **PHP APIs nicht erreichbar**:
- MySQLi Extension aktiviert?
- Datenbankverbindung korrekt?
- CORS-Header gesetzt?

### 3. **React Build-Fehler**:
```bash
npm run build    # TypeScript-Fehler anzeigen
npx tsc --noEmit # Type-Check ohne Build
```

### 4. **Azure AD Login-Probleme**:
- Client Secret korrekt in .env?
- Redirect URI in Azure AD konfiguriert?
- Session-Cookies aktiviert?

### 5. **Database Connection Issues**:
- .env Datei vorhanden und korrekt?
- MySQL Server erreichbar?
- Firewall-Einstellungen prüfen
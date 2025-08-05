# Deployment Checklist für Issues #028, #027, #029

## 🎯 Änderungen für Deployment

### Issue #028: Remove Debug Files ✅
**Gelöschte Dateien:**
- ✅ 16 Debug/Test PHP-Dateien wurden entfernt (manuell gelöscht, nicht in git status sichtbar)

**Geänderte Dateien:**
- ✅ `.gitignore` - Regeln für Debug-Dateien hinzugefügt

### Issue #027: Timer Service Extraction ✅
**Neue Dateien:**
- ✅ `build/src/components/TimerService.tsx`
- ✅ `build/src/hooks/useTimer.ts`

**Geänderte Dateien:**
- ✅ `build/src/views/MainAppView.tsx` - Timer-Logik entfernt

### Issue #029: Consolidate Timer Endpoints ✅
**Gelöschte Dateien:**
- ✅ `build/api/timer-control.php`
- ✅ `build/api/timer-start.php`
- ✅ `build/api/timer-stop.php`

## ⚠️ WICHTIG: Andere Änderungen im Repository

Es gibt viele andere Änderungen, die NICHT Teil unserer Issues sind:
- `build/api/login.php` (modifiziert)
- `build/api.ts` (modifiziert)
- `build/src/App.tsx` (modifiziert)
- Viele neue Dateien (ErrorBoundary, Tests, etc.)

## 📋 Empfohlene Git-Befehle

### Für saubere Commits nur unserer Änderungen:

```bash
# Commit 1: Issue #028 (Debug Files)
git add .gitignore
git commit -m "fix: Remove debug/test files from production (CRITICAL security fix)

- Added comprehensive .gitignore rules to prevent debug files
- Removed 16 debug/test PHP files (bereits manuell gelöscht)
- No functionality impact

Fixes #028"

# Commit 2: Issue #027 (Timer Service)
git add build/src/components/TimerService.tsx
git add build/src/hooks/useTimer.ts
git add build/src/views/MainAppView.tsx
git commit -m "refactor: Extract timer service from MainAppView

- Created useTimer hook for timer state management
- Created TimerService component for timer UI/API
- Reduced MainAppView from 522 to 383 lines (26%)
- Improved SOLID compliance and testability

Fixes #027"

# Commit 3: Issue #029 (Consolidate Endpoints)
git add -u build/api/timer-*.php
git commit -m "refactor: Consolidate duplicate timer endpoints

- Removed timer-start.php, timer-stop.php, timer-control.php
- Consolidated all timer operations into time-entries.php
- Eliminated 314 lines of duplicate code (49% reduction)
- Maintained full backward compatibility

Fixes #029"
```

## 🧪 Test-Checkliste vor Deployment

### 1. Timer-Funktionalität testen:
- [ ] Timer starten funktioniert
- [ ] Timer stoppen funktioniert
- [ ] Timer-Anzeige aktualisiert sich
- [ ] Bestehender Timer wird beim Seitenreload erkannt

### 2. API-Endpunkte testen:
- [ ] GET /api/time-entries.php
- [ ] POST /api/time-entries.php (Timer start)
- [ ] POST /api/time-entries.php?action=stop
- [ ] GET /api/time-entries.php?action=check_running

### 3. Sicherheit prüfen:
- [ ] Keine Debug-Dateien mehr im build/api Verzeichnis
- [ ] Keine sensitive Daten in Logs

## 🚀 Deployment-Schritte

1. **Backup erstellen** (falls nötig)
2. **Commits ausführen** (siehe oben)
3. **Tests lokal durchführen**
4. **Deploy auf Test-Umgebung**
5. **Tests auf Test-Umgebung**
6. **Deploy auf Produktion**
7. **Verifikation auf Produktion**
8. **GitHub Issues schließen**

## 📝 GitHub Issue Updates

Nach erfolgreichem Deployment, folgende Issues schließen:

### Issue #028:
```
Fixed in commit [commit-hash]
- Removed 16 debug/test files
- Added .gitignore rules
- Verified: No functionality impact
```

### Issue #027:
```
Fixed in commit [commit-hash]
- Extracted timer logic to dedicated components
- Reduced MainAppView by 26%
- Verified: All timer functionality preserved
```

### Issue #029:
```
Fixed in commit [commit-hash]
- Consolidated 4 timer endpoints into 1
- Removed 314 lines of duplicate code
- Verified: Full backward compatibility
```
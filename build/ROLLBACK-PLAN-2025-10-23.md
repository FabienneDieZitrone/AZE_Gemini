# ROLLBACK-PLAN: Employee Onboarding Feature
**Datum:** 2025-10-23 13:15
**Problem:** Admin-Buttons nicht sichtbar nach Onboarding-Implementation
**Status:** AKTIV - Rollback möglich

---

## 🚨 KRITISCH: Schneller Rollback (5 Minuten)

### Option 1: Rollback zu letztem funktionierenden Zustand
```bash
# 1. Zurück zu Commit VOR Onboarding (a80ef06)
cd /home/aios/projekte/aze-gemini/claude-container/projekt/build
git checkout a80ef06

# 2. Frontend neu bauen
npm run build

# 3. Frontend deployen
bash ./deploy-secure.sh frontend

# 4. Backend deployen (alte Version ohne Onboarding-Checks)
bash ./deploy-secure.sh backend

# ✅ System sollte jetzt wieder funktionieren wie vor Onboarding
```

### Option 2: Nur Migration rückgängig machen (falls DB geändert wurde)
```sql
-- Falls Migration BEREITS ausgeführt wurde:
ALTER TABLE users DROP COLUMN onboarding_completed;
ALTER TABLE users DROP COLUMN home_location;
ALTER TABLE users DROP COLUMN created_via_onboarding;
ALTER TABLE users DROP COLUMN pending_since;
```

---

## 📊 Änderungen in diesem Feature

### Betroffene Dateien (Backend)
1. `/api/migrate-add-onboarding-fields.php` - NEU (Migration)
2. `/api/login.php` - GEÄNDERT (Zeilen 152-179: onboarding_completed check)
3. `/api/auth-callback.php` - GEÄNDERT (Zeilen 141-221: onboarding logic)
4. `/api/masterdata.php` - GEÄNDERT (Zeilen 191-213: onboarding completion)
5. `/api/onboarding.php` - NEU (Onboarding-Endpoint)

### Betroffene Dateien (Frontend)
1. `/src/views/MainAppView.tsx` - GEÄNDERT (Zeilen 448-457: Onboarding-Check)
2. `/src/views/OnboardingView.tsx` - NEU
3. `/src/hooks/useSupervisorNotifications.ts` - NEU
4. `/src/components/modals/SupervisorNotificationModal.tsx` - GEÄNDERT (Zeilen 24-44: pendingOnboardingUsers)
5. `/src/types.ts` - GEÄNDERT (neue Types: PendingOnboardingUser, SupervisorNotification)
6. `/api.ts` - GEÄNDERT (neue API-Funktionen: completeOnboarding, getPendingOnboardingUsers)

### Git-Commits
- `9cfebb2` - feat: Implementiere Employee-Onboarding-System (Phase 1)
- `b3d892c` - feat: Vollständige Employee-Onboarding-Integration (Phase 2)
- `8a826d2` - fix: Migration-safe onboarding column checks

---

## 🔍 Aktueller Deployment-Status

### Production-Server
- **URL:** https://aze.mikropartner.de
- **FTP:** wp10454681.server-he.de
- **User:** ftp10454681-aze
- **Letztes Deployment:** 2025-10-23 ~13:00

### Migration-Status
- **Migration ausgeführt?** ❓ UNBEKANNT (muss geprüft werden)
- **Migration-URL:** https://aze.mikropartner.de/api/migrate-add-onboarding-fields.php
- **Test-Befehl:** `curl -s "https://aze.mikropartner.de/api/migrate-add-onboarding-fields.php"`

---

## ✅ Rollback-Verifikation

Nach Rollback folgende Tests durchführen:

1. **Login-Test:**
   ```bash
   # Login sollte funktionieren
   curl -s "https://aze.mikropartner.de/api/login.php?diag=1"
   ```

2. **Admin-User-Test:**
   - Als Günter Allert einloggen
   - ALLE 6 Buttons sollten sichtbar sein:
     - Arbeitszeiten anzeigen ✅
     - Zeit nachtragen ✅
     - Dashboard ✅
     - Stammdaten ✅
     - Genehmigungen ✅
     - Globale Einstellungen ✅

3. **Timer-Test:**
   - Timer starten/stoppen sollte funktionieren

---

## 🔄 Nach Rollback: Feature neu implementieren

Falls Rollback nötig war, Feature schrittweise neu implementieren:

1. **Migration ZUERST ausführen und verifizieren**
2. **Backend migration-safe deployen und testen**
3. **Frontend deployen und testen**
4. **Jeden Schritt EINZELN testen bevor weiter**

---

## 📞 Support

Bei Problemen:
1. Dieses Dokument verwenden für Rollback
2. GitHub Issue erstellen mit Details
3. MP-IT kontaktieren falls Login nicht mehr funktioniert

**WICHTIG:** Dieses Dokument NICHT löschen bis Feature verifiziert stabil ist!

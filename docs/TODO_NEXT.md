# TODO – Nächste Schritte (sicher)

1) Frontend-Deploy der Genehmigungs-Ansicht
   - Lokal bereits umgesetzt:
     - build/api.ts: getPendingApprovals, getAllApprovals
     - build/src/views/MainAppView.tsx: lädt in approvals-Ansicht separat
     - build/src/views/ApprovalView.tsx: Umschalter „Ausstehend/Alle“, Status-Spalte
   - Build & Deploy: `cd build && npm ci && npm run build` → `dist/` deployen.

2) Login-Variante konsolidieren
   - Ziel: eine stabile login.php, die vollständige Initialdaten liefert (User, MasterData, TimeEntries, ApprovalRequests, History) – ohne Query-Logger-Abhängigkeit.
   - Vorschlag: serverseitig aktive Variante fixieren und in Repo spiegeln.

3) Status-Mapping vereinheitlichen
   - Zentrale Status-Map (pending vs. final) in GET /approvals.php (und ggf. login.php) konsolidieren.

4) Timer/Tracking prüfen (Start 24:00 / Tracking stoppt)
   - Netzwerkanfragen zu /api/time-entries.impl.php (start/stop/check_running) prüfen.
   - CSRF-Bypass (Same-Origin) ist aktiv – HTTP-Status in DevTools prüfen.
   - /api/test.html (falls vorhanden) auf neue Zeilen (start/stop) prüfen.

5) Optional: Endpoint /api/approvals-list.php
   - Read-only Endpunkt, der GET /approvals.php kapselt (Option für saubere API-Verträge), inkl. Pagination/Sortierung.


# Genehmigungen – Übersicht & Debug

## Endpunkte

- GET /api/approvals.php
  - Query: optional `status=all` (liefert alle Anträge), ansonsten „Ausstehend“.
  - Rollen: Mitarbeiter/Honorarkraft → eigene; Standortleiter → Standort (new_data.location, fallback original); Admin/Bereichsleiter → alle.
  - Response: `{ items, count, meta? }` – meta enthält `total`, `pending`.

- POST /api/approvals.php
  - `{ type, entryId?, newData?, reasonData? }` – requested_by aus Session-E-Mail.
  - Schema-adaptiv: UUID/AI, requested_at/created_at, entry_id-NULL für create.

- PATCH /api/approvals.php
  - `{ requestId, finalStatus: 'genehmigt'|'abgelehnt' }` – schreibt resolved_* und wendet Änderungen auf time_entries an.

## Status-Klassifikation (Server)

- Ausstehend (nicht final): NULL, leer, 'pending', 'submitted', 'open', 'in_review', 'requested'.
- Final: 'genehmigt', 'abgelehnt', 'approved', 'rejected', 'completed', 'done'.

## Warum Liste leer sein kann

1) Keine ausstehenden Anträge (pending=0). Dann liefert GET /api/approvals.php als Admin die letzten 20 (Fallback), aber die UI muss diese laden (Login-Payload vs. GET beachten).
2) UI lädt Genehmigungen aus login.php, nicht aus GET – und die aktive login.php-Variante liefert keine approvalRequests. Lösung: FE-Deploy (Umschalter) oder serverseitige Injection.

## Diagnostik (schnell)

- Im eingeloggten Browser: GET /api/approvals.php → `meta.total` und `meta.pending`.
- Admin-Dump: GET /api/approvals_dump.php → `counts` (Statuswerte) und `latest` (letzte Einträge).


# Fix approvals workflow: GET filtering, POST transaction commit, UI refresh, and webroot config

## What
- API GET `/api/approvals.php`:
  - Remove admin fallback ("last 20") for pending view.
  - Preserve real `status` in responses (no forced `pending`).
- API POST `/api/approvals.php`:
  - Add explicit transaction (`begin_transaction` + `commit`); rollback on error.
- Login payload (`/api/login.php`):
  - Inject only real `pending` approvals; remove fallback entries.
- Frontend (React):
  - Clear approvals list on entering the view; then load pending via GET.
  - Disable approve/reject buttons for non-pending items.
  - Show supervisor popup only once per session.
- Infra:
  - Update root `.htaccess` to `+SymLinksIfOwnerMatch` and proper `DirectoryIndex`; add sane cache headers.

## Why
- Users saw only 3 historic approvals; fresh requests did not appear because:
  1) GET had fallback and status mapping masked pending/non-pending correctly.
  2) POST inserts were not committed due to `autocommit(false)` in DB layer.
- 403/"Under Construction" at domain root due to `-FollowSymLinks`.

## How
- Backend changes in `api/approvals.php` and `api/login.php` as above.
- Frontend adjustments in `MainAppView.tsx`, `ApprovalView.tsx`, and `useSupervisorNotifications.ts`.
- Webroot `.htaccess` updated.

## Testing
- Create new approval (create/edit/delete) → GET pending shows it; PATCH approve/reject → pending disappears; GET all shows history with real status.
- Frontend: "Ausstehend/Alle" toggles visible; buttons disabled for final items; popup shown once.
- Root URL renders SPA (no 403).

## Risks
- None observed; fallback removed intentionally. Status preserved for UI correctness.
- Ensure debug logs rotated in production.

## Follow-ups
- Add integration tests for approvals POST/GET/PATCH flow.
- Document `autocommit(false)` and transaction requirements for write paths.


## 2025-10-07 – Approvals workflow reliability and UI fixes

- API: Fix GET filtering (no admin fallback for pending; preserve real status).
- API: Ensure POST commits in transaction (no implicit rollback).
- API: Login payload now includes only real pending approvals.
- FE: Clear approvals view on enter; fetch pending; disable actions for non-pending.
- Infra: Root .htaccess adjusted (`+SymLinksIfOwnerMatch`, DirectoryIndex, cache headers) to resolve 403.
- Diagnostics: approvals_dump.php robust ordering without `created_at`.


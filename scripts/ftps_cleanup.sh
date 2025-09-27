#!/usr/bin/env bash
set -euo pipefail

# Dry-run cleanup plan for remote webspace via FTPS
# Uses remote_inventory.csv to determine candidates. For actual deletion, require --execute.

if [[ -f .env ]]; then set -a; source .env; set +a; fi
FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"

if [[ -z "${FTP_HOST}" || -z "${FTP_USER}" || -z "${FTP_PASS}" ]]; then
  echo "ERROR: FTP credentials missing" >&2
  exit 1
fi

CSV="remote_inventory.csv"
[[ -f "$CSV" ]] || { echo "Missing $CSV. Run scripts/ftps_inventory.sh first." >&2; exit 1; }

# Patterns to remove (production hygiene)
PATTERNS=(
  "/api/*-debug*.php"
  "/api/*-test*.php"
  "/api/debug-*.php"
  "/api/test-*.php"
  "/api/*backup*"
  "/api/*.log"
  "/**/*.test.*"
  "/**/*.ts"
  "/**/*.tsx"
  "/**/*.map"
  "/**/*.sql"
)

LIST="cleanup_candidates.txt"
> "$LIST"

echo "Building cleanup candidate list …"
while IFS=, read -r path type size mtime; do
  [[ "$path" == "path" ]] && continue
  for pat in "${PATTERNS[@]}"; do
    if [[ "$path" == */* ]]; then
      :
    fi
    # crude glob match via bash
    if [[ "$path" == ${pat} ]]; then
      echo "$path" >> "$LIST"
      break
    fi
  done
done < "$CSV"

sort -u -o "$LIST" "$LIST"
echo "Candidates written to $LIST"

if [[ "${1:-}" != "--execute" ]]; then
  echo "Dry run only. To delete, re-run: $0 --execute"
  exit 0
fi

echo "Executing deletion via curl (FTPS) …"
while IFS= read -r path; do
  [[ -z "$path" ]] && continue
  # Use DELE for files; RMD for dirs (skip dirs here to be safe)
  echo "Deleting: $path"
  curl -sS --ssl-reqd -Q "DELE $path" "ftp://$FTP_USER:$FTP_PASS@$FTP_HOST/" || true
done < "$LIST"

echo "Cleanup complete. Review the site functionality immediately."


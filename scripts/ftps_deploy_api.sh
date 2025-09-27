#!/usr/bin/env bash
set -euo pipefail

# FTPS deploy for build/api/* → /www/aze/api/
# - Dry-run by default (lists uploads only)
# - Use --execute to actually upload
# - Reads FTP credentials from .env (FTP_HOST/FTP_SERVER, FTP_USER, FTP_PASS/FTP_PASSWORD)

DRY_RUN=1
INCLUDE_NEW=0
REMOTE_BASE="/www/aze/api/"
LOCAL_DIR="build/api"

for arg in "$@"; do
  case "$arg" in
    --execute) DRY_RUN=0 ;;
    --include-new) INCLUDE_NEW=1 ;;
    --base=*) REMOTE_BASE="${arg#--base=}" ;;
    --local=*) LOCAL_DIR="${arg#--local=}" ;;
    --dry-run) DRY_RUN=1 ;;
    *) ;;
  esac
done

if [[ ! -d "$LOCAL_DIR" ]]; then
  echo "ERROR: Local dir not found: $LOCAL_DIR" >&2
  exit 1
fi

# Load .env if present
if [[ -f .env ]]; then set -a; source .env; set +a; fi

FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
  echo "ERROR: Missing FTP credentials (FTP_HOST/FTP_SERVER, FTP_USER, FTP_PASS/FTP_PASSWORD)" >&2
  exit 1
fi

echo "Deploy target: ftps://$FTP_HOST$REMOTE_BASE"
echo "Local source:  $LOCAL_DIR"
[[ "$DRY_RUN" -eq 1 ]] && echo "Mode: DRY-RUN (no uploads)" || echo "Mode: EXECUTE (will upload)"
[[ "$INCLUDE_NEW" -eq 1 ]] && echo "Policy: include NEW files" || echo "Policy: skip NEW files (only update existing)"

changed=0
skipped=0

# Build a quick remote size map using LIST (approximate)
TMP_REMOTE=$(mktemp)
curl -sS -k --ssl-reqd --ftp-ssl --user "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST${REMOTE_BASE%/}/" > "$TMP_REMOTE" || true

remote_size() {
  local name="$1"
  # Grep line ending with name and print size (field 5 in typical UNIX LIST)
  # Note: Names with spaces are rare here; fallback ignores that case
  awk -v n="$name" '$0 ~ (n"$") && $1 !~ /^d/ {print $5; exit}' "$TMP_REMOTE"
}

remote_has() {
  local name="$1"
  grep -qE "[[:space:]]${name//./\.}
$" "$TMP_REMOTE" && return 0 || return 1
}

upload_file() {
  local src="$1"; local rel="$2"
  local url="ftp://$FTP_USER:$FTP_PASS@$FTP_HOST${REMOTE_BASE%/}/$rel"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "UPLOAD (dry-run): $rel"
  else
    echo "UPLOAD: $rel"
    curl -sS -k --ssl-reqd --ftp-ssl -T "$src" "$url"
  fi
}

while IFS= read -r -d '' file; do
  rel=$(basename "$file")
  lsize=$(stat -c %s "$file")
  rsize=$(remote_size "$rel" || true)
  # Skip known debug/test/log files by default
  case "$rel" in
    *-debug*.php|*test*.php|debug-*.php|test-*.php|*.log|server.log)
      echo "SKIP (debug/test): $rel"; skipped=$((skipped+1)); continue ;;
  esac

  # If remote does not have the file and INCLUDE_NEW=0, skip
  if [[ -z "$rsize" ]]; then
    if [[ "$INCLUDE_NEW" -eq 0 ]]; then
      echo "SKIP (new on local): $rel"
      skipped=$((skipped+1))
      continue
    fi
  fi

  if [[ -z "$rsize" || "$lsize" != "$rsize" ]]; then
    upload_file "$file" "$rel"
    changed=$((changed+1))
  else
    skipped=$((skipped+1))
  fi
done < <(find "$LOCAL_DIR" -maxdepth 1 -type f -print0)

echo "Summary: $changed to upload, $skipped up-to-date"

rm -f "$TMP_REMOTE"

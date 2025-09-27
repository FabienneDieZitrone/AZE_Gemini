#!/usr/bin/env bash
set -euo pipefail

# Deploys Vite dist/ to /www/aze (index.html + assets/*)
# Dry-run by default. Use --execute to upload.

DRY_RUN=1
DIST_DIR="build/dist"
REMOTE_BASE="/www/aze"

for arg in "$@"; do
  case "$arg" in
    --execute) DRY_RUN=0 ;;
    --dist=*) DIST_DIR="${arg#--dist=}" ;;
    --base=*) REMOTE_BASE="${arg#--base=}" ;;
    --dry-run) DRY_RUN=1 ;;
    *) ;;
  esac
done

if [[ ! -d "$DIST_DIR" ]]; then
  echo "ERROR: Dist directory not found: $DIST_DIR" >&2
  exit 1
fi

if [[ -f .env ]]; then set -a; source .env; set +a; fi
FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"

if [[ -z "$FTP_HOST" || -z "$FTP_USER" || -z "$FTP_PASS" ]]; then
  echo "ERROR: Missing FTP credentials" >&2
  exit 1
fi

echo "Target base: ftps://$FTP_HOST$REMOTE_BASE"
echo "Source dist: $DIST_DIR"
[[ "$DRY_RUN" -eq 1 ]] && echo "Mode: DRY-RUN (no upload)" || echo "Mode: EXECUTE (will upload)"

upload() {
  local src="$1"; local dest="$2"
  local url="ftp://$FTP_USER:$FTP_PASS@$FTP_HOST$dest"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "UPLOAD (dry-run): $src -> $dest"
  else
    echo "UPLOAD: $src -> $dest"
    curl -sS -k --ssl-reqd --ftp-ssl -T "$src" "$url"
  fi
}

# Ensure assets directory exists
if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "MKD (dry-run): ${REMOTE_BASE}/assets"
else
  curl -sS -k --ssl-reqd --ftp-ssl --user "$FTP_USER:$FTP_PASS" -Q "MKD ${REMOTE_BASE}/assets" "ftp://$FTP_HOST/" >/dev/null || true
fi

# Upload index.html
upload "$DIST_DIR/index.html" "${REMOTE_BASE}/index.html"

# Upload assets
for f in "$DIST_DIR"/assets/*; do
  b=$(basename "$f")
  upload "$f" "${REMOTE_BASE}/assets/$b"
done

echo "Done."


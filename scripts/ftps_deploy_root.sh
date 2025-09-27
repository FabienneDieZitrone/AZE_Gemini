#!/usr/bin/env bash
set -euo pipefail

# Deploys build/index.html to /www/aze/index.html via FTPS
# Dry-run by default. Use --execute to upload.

DRY_RUN=1
REMOTE_PATH="/www/aze/index.html"
LOCAL_FILE="build/index.html"

for arg in "$@"; do
  case "$arg" in
    --execute) DRY_RUN=0 ;;
    --path=*) REMOTE_PATH="${arg#--path=}" ;;
    --file=*) LOCAL_FILE="${arg#--file=}" ;;
    --dry-run) DRY_RUN=1 ;;
    *) ;;
  esac
done

if [[ ! -f "$LOCAL_FILE" ]]; then
  echo "ERROR: Local file not found: $LOCAL_FILE" >&2
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

echo "Target: ftps://$FTP_HOST$REMOTE_PATH"
echo "Source: $LOCAL_FILE"
[[ "$DRY_RUN" -eq 1 ]] && echo "Mode: DRY-RUN (no upload)" || echo "Mode: EXECUTE (will upload)"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "Would upload $LOCAL_FILE → $REMOTE_PATH"
else
  curl -sS -k --ssl-reqd --ftp-ssl --user "$FTP_USER:$FTP_PASS" -T "$LOCAL_FILE" "ftp://$FTP_HOST$REMOTE_PATH"
  echo "Uploaded."
fi


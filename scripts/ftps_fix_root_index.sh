#!/usr/bin/env bash
set -euo pipefail

# Overwrite /www/index.html with a redirect to /aze/
# Dry-run by default; use --execute to upload.

DRY_RUN=1
REMOTE_PATH="/www/index.html"

for arg in "$@"; do
  case "$arg" in
    --execute) DRY_RUN=0 ;;
    --path=*) REMOTE_PATH="${arg#--path=}" ;;
  esac
done

HTML_CONTENT='<!DOCTYPE html>\n<html lang="de">\n<head>\n  <meta charset="UTF-8" />\n  <meta http-equiv="refresh" content="0; url=/aze/" />\n  <meta name="viewport" content="width=device-width, initial-scale=1.0" />\n  <title>Weiterleitung…</title>\n  <script>window.location.replace("/aze/");</script>\n</head>\n<body>\n  <noscript>Weiterleitung zur <a href="/aze/">Hauptseite</a>…</noscript>\n</body>\n</html>\n'

TMP=$(mktemp)
printf "%b" "$HTML_CONTENT" > "$TMP"

if [[ -f .env ]]; then set -a; source .env; set +a; fi
FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"

echo "Target: ftps://$FTP_HOST$REMOTE_PATH"
[[ "$DRY_RUN" -eq 1 ]] && echo "Mode: DRY-RUN (no upload)" || echo "Mode: EXECUTE (will upload)"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "Would upload redirect index.html to $REMOTE_PATH"
else
  curl -sS -k --ssl-reqd --ftp-ssl --user "$FTP_USER:$FTP_PASS" -T "$TMP" "ftp://$FTP_HOST$REMOTE_PATH"
  echo "Uploaded redirect index.html"
fi

rm -f "$TMP"


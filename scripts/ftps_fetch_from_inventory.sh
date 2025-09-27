#!/usr/bin/env bash
set -euo pipefail

# Download all files listed in remote_inventory.csv via FTPS into backups/webspace_<timestamp>

if [[ -f .env ]]; then set -a; source .env; set +a; fi
FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"

if [[ -z "${FTP_HOST}" || -z "${FTP_USER}" || -z "${FTP_PASS}" ]]; then
  echo "ERROR: FTP credentials missing" >&2
  exit 1
fi

CSV="${1:-remote_inventory.csv}"
[[ -f "$CSV" ]] || { echo "Missing $CSV. Run scripts/ftps_inventory.sh first or provide CSV path." >&2; exit 1; }

TS=$(date +%Y%m%d_%H%M%S)
OUT_DIR="backups/webspace_${TS}"
mkdir -p "$OUT_DIR"

echo "Downloading files listed in $CSV to $OUT_DIR …"

while IFS=, read -r path type size mtime; do
  [[ "$path" == "path" ]] && continue
  if [[ "$type" == "file" ]]; then
    local_path="${OUT_DIR}${path}"
    mkdir -p "$(dirname "$local_path")"
    url="ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}${path}"
    echo "GET $path"
    curl -sS --ssl-reqd "$url" -o "$local_path" || echo "WARN: failed $path" >&2
  fi
done < "$CSV"

echo "Creating archive …"
tar -C "$(dirname "$OUT_DIR")" -czf "${OUT_DIR}.tar.gz" "$(basename "$OUT_DIR")"
echo "Backup ready: ${OUT_DIR}.tar.gz"

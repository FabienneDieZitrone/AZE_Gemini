#!/usr/bin/env bash
set -euo pipefail

# Test FTP(S) Verbindung zum Produktivserver
echo "=== Teste FTPS-Verbindung ==="

# 1) .env laden, falls vorhanden (setzt FTP_SERVER, FTP_USER, FTP_PASSWORD)
if [[ -f .env ]]; then
  # shellcheck disable=SC1091
  set -a; source .env; set +a
fi

# 2) Variablen vereinheitlichen (Kompatibilität beider Namensschemata)
FTP_HOST="${FTP_HOST:-${FTP_SERVER:-}}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-${FTP_PASSWORD:-}}"
FTP_PATH="${FTP_PATH:-${FTP_TARGET_DIR:-/www/aze/}}"

if [[ -z "${FTP_HOST}" || -z "${FTP_USER}" || -z "${FTP_PASS}" ]]; then
  echo "ERROR: FTP credentials not set (need FTP_HOST/FTP_SERVER, FTP_USER, FTP_PASS/FTP_PASSWORD)."
  echo "Hint: store them in .env (FTP_SERVER, FTP_USER, FTP_PASSWORD) or export them before running."
  exit 1
fi

echo "Host: ${FTP_HOST}"
echo "User: ${FTP_USER}"
echo "Path: ${FTP_PATH}"

# 3) Test: FTPS Listing via curl (explicit TLS)
echo -e "\nTeste FTPS-Verbindung mit curl..."
curl -v --ssl-reqd "ftp://${FTP_USER}:${FTP_PASS}@${FTP_HOST}${FTP_PATH}" --list-only 2>&1 | head -20 || true

# 4) Test: HTTPS Health Endpoint
echo -e "\nTeste HTTPS-Verbindung zu aze.mikropartner.de..."
curl -I https://aze.mikropartner.de/api/health -k 2>&1 | head -10 || true

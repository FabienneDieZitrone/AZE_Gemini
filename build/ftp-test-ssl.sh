#!/bin/bash
# Test FTP connection with SSL/TLS

cd "$(dirname "$0")"
export $(grep -v '^#' .env.production | xargs)

echo "Testing FTPS connection (FTP over SSL/TLS)..."
echo "Host: $FTP_HOST"
echo "User: $FTP_USER"
echo ""

# Test 1: Explicit FTPS with TLS 1.2
echo "Test 1: Explicit FTPS with TLS 1.2..."
curl -v \
    --ftp-ssl-reqd \
    --ftp-ssl-control \
    --tlsv1.2 \
    --insecure \
    --user "${FTP_USER}:${FTP_PASS}" \
    --list-only \
    "ftp://${FTP_HOST}/" 2>&1 | grep -E "230|530|226|Connected|TLS|SSL"

echo -e "\n---\n"

# Test 2: Implicit FTPS
echo "Test 2: Trying with --ssl flag..."
curl -v \
    --ssl \
    --user "${FTP_USER}:${FTP_PASS}" \
    --list-only \
    "ftp://${FTP_HOST}/" 2>&1 | grep -E "230|530|226|Connected|TLS|SSL"

echo -e "\n---\n"

# Test 3: Try ftps:// protocol
echo "Test 3: Using ftps:// protocol..."
curl -v \
    --insecure \
    --user "${FTP_USER}:${FTP_PASS}" \
    --list-only \
    "ftps://${FTP_HOST}/" 2>&1 | grep -E "230|530|226|Connected|TLS|SSL"
#!/bin/bash
# Quick single-file upload

FILE="$1"
if [ -z "$FILE" ]; then
    echo "Usage: $0 <file-in-api-folder>"
    exit 1
fi

source .env.production

echo "Uploading $FILE..."

curl -T "api/$FILE" \
    "ftp://${FTP_HOST}/api/$FILE" \
    --user "${FTP_USER}:${FTP_PASSWORD}" \
    --ftp-create-dirs \
    --silent \
    --show-error

if [ $? -eq 0 ]; then
    echo "✓ Uploaded: $FILE"
else
    echo "✗ Failed: $FILE"
    exit 1
fi

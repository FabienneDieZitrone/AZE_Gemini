#!/usr/bin/env python3
import subprocess
import time

# SSH-Verbindung mit automatischer Passwort-Eingabe
ssh_command = f"""
echo '321Start321' | ssh -tt -o StrictHostKeyChecking=no -p 22 ftp10454681-aze@wp10454681.server-he.de << 'EOF'
cd /www/aze/
echo "=== Extracting deployment archive ==="
tar -xzf /tmp/aze-deployment.tar.gz
echo "=== Removing temporary file ==="
rm /tmp/aze-deployment.tar.gz
echo "=== Listing files ==="
ls -la | head -10
echo "=== Deployment extraction complete ==="
exit
EOF
"""

try:
    print("Connecting to server and extracting deployment...")
    result = subprocess.run(ssh_command, shell=True, capture_output=True, text=True)
    print("STDOUT:", result.stdout)
    print("STDERR:", result.stderr)
    print("Return code:", result.returncode)
except Exception as e:
    print(f"Error: {e}")
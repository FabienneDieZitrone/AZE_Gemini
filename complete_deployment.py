#!/usr/bin/env python3
"""
Complete the deployment by SSHing to extract the archive
"""

import paramiko
import sys

# Configuration
SSH_HOST = "wp10454681.server-he.de"
SSH_USER = "ftp10454681-aze"
SSH_PASS = "321Start321"
SSH_PORT = 22

def complete_deployment():
    """SSH to server and extract deployment archive"""
    try:
        print(f"Connecting to {SSH_HOST}:{SSH_PORT} via SSH...")
        
        # Create SSH client
        client = paramiko.SSHClient()
        client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        
        # Connect
        client.connect(
            SSH_HOST,
            port=SSH_PORT,
            username=SSH_USER,
            password=SSH_PASS,
            timeout=30
        )
        
        print("Connected! Extracting deployment archive...")
        
        # Commands to execute
        commands = [
            "cd /www/aze/",
            "tar -xzf /tmp/aze-deployment.tar.gz",
            "rm /tmp/aze-deployment.tar.gz",
            "ls -la | head -10"
        ]
        
        # Execute commands
        for cmd in commands:
            print(f"Executing: {cmd}")
            stdin, stdout, stderr = client.exec_command(cmd)
            output = stdout.read().decode()
            error = stderr.read().decode()
            
            if output:
                print(f"Output: {output}")
            if error:
                print(f"Error: {error}")
        
        # Close connection
        client.close()
        
        print("\nDeployment extraction completed!")
        print("\nVerifying deployment...")
        
        # Verify with curl
        import subprocess
        result = subprocess.run(
            ["curl", "-s", "https://aze.mikropartner.de/api/health.php"],
            capture_output=True,
            text=True
        )
        
        print("Health check response:")
        print(result.stdout)
        
        return True
        
    except Exception as e:
        print(f"Error: {e}")
        return False

if __name__ == "__main__":
    if complete_deployment():
        print("\nDeployment completed successfully!")
    else:
        print("\nDeployment failed!")
        sys.exit(1)
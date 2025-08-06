#!/usr/bin/env python3
"""
Extract Test Deployment Script

This script:
1. Connects to the FTP server using FTPS
2. Navigates to /www/aze-test/
3. Creates a PHP script that extracts the tar.gz file
4. Executes the PHP script via HTTP request
5. Verifies the extraction was successful

Requirements:
- Python 3.6+
- requests library (pip install requests)
"""

import ftplib
import ssl
import os
import requests
import sys
import time
from urllib.parse import urljoin

# FTP Configuration
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
FTP_PATH = "/www/aze-test/"

# HTTP Configuration (adjust based on your actual domain)
# Common patterns:
# - https://aze-test.server-he.de/
# - https://your-domain.com/aze-test/
# - http://wp10454681.server-he.de/aze-test/
HTTP_BASE_URL = "https://aze.mikropartner.de/aze-test/"

class FTPSConnection:
    """Secure FTP connection handler"""
    
    def __init__(self, host, user, password):
        self.host = host
        self.user = user
        self.password = password
        self.ftp = None
    
    def connect(self):
        """Establish FTPS connection"""
        try:
            print(f"Connecting to FTPS server: {self.host}")
            
            # Create FTPS connection with explicit TLS
            self.ftp = ftplib.FTP_TLS()
            self.ftp.set_debuglevel(0)  # Set to 2 for verbose debugging
            
            # Connect and login
            self.ftp.connect(self.host, 21)
            self.ftp.login(self.user, self.password)
            
            # Switch to secure data connection
            self.ftp.prot_p()
            
            print("✓ FTPS connection established successfully")
            return True
            
        except Exception as e:
            print(f"✗ Failed to connect to FTPS server: {e}")
            return False
    
    def navigate_to_path(self, path):
        """Navigate to specified directory"""
        try:
            print(f"Navigating to: {path}")
            self.ftp.cwd(path)
            print(f"✓ Successfully navigated to {path}")
            return True
        except Exception as e:
            print(f"✗ Failed to navigate to {path}: {e}")
            return False
    
    def upload_file(self, local_file, remote_file):
        """Upload file to FTP server"""
        try:
            print(f"Uploading {local_file} as {remote_file}")
            with open(local_file, 'rb') as file:
                self.ftp.storbinary(f'STOR {remote_file}', file)
            print(f"✓ Successfully uploaded {remote_file}")
            return True
        except Exception as e:
            print(f"✗ Failed to upload {remote_file}: {e}")
            return False
    
    def list_files(self):
        """List files in current directory"""
        try:
            files = self.ftp.nlst()
            print(f"Files in current directory: {files}")
            return files
        except Exception as e:
            print(f"✗ Failed to list files: {e}")
            return []
    
    def file_exists(self, filename):
        """Check if file exists in current directory"""
        try:
            files = self.ftp.nlst()
            return filename in files
        except Exception as e:
            print(f"✗ Error checking file existence: {e}")
            return False
    
    def close(self):
        """Close FTP connection"""
        if self.ftp:
            try:
                self.ftp.quit()
                print("✓ FTPS connection closed")
            except:
                pass

def create_php_extraction_script():
    """Create PHP script for extracting tar.gz file"""
    
    php_script = '''<?php
/**
 * Automated Extraction Script
 * Extracts aze-test-complete.tar.gz and sets proper permissions
 */

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'details' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    $tarFile = 'aze-test-complete.tar.gz';
    $extractPath = './';
    
    // Check if tar.gz file exists
    if (!file_exists($tarFile)) {
        throw new Exception("Archive file '$tarFile' not found");
    }
    
    $response['details'][] = "Found archive file: $tarFile";
    
    // Get file size for verification
    $fileSize = filesize($tarFile);
    $response['details'][] = "Archive size: " . number_format($fileSize) . " bytes";
    
    // Extract the tar.gz file
    $response['details'][] = "Starting extraction...";
    
    // Use PharData for extraction (more reliable than system commands)
    try {
        $phar = new PharData($tarFile);
        $phar->extractTo($extractPath, null, true);
        $response['details'][] = "✓ Archive extracted successfully using PharData";
    } catch (Exception $pharException) {
        // Fallback to system command
        $response['details'][] = "PharData failed, trying system command...";
        
        $command = "tar -xzf " . escapeshellarg($tarFile) . " -C " . escapeshellarg($extractPath) . " 2>&1";
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Extraction failed with code $returnCode: " . implode("\\n", $output));
        }
        
        $response['details'][] = "✓ Archive extracted successfully using tar command";
    }
    
    // Set proper permissions for extracted files
    $response['details'][] = "Setting file permissions...";
    
    // Function to recursively set permissions
    function setPermissionsRecursive($path, $filePerms = 0644, $dirPerms = 0755) {
        $items = [];
        
        if (is_dir($path)) {
            chmod($path, $dirPerms);
            $items[] = "Directory: $path (755)";
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    chmod($item->getPathname(), $dirPerms);
                    $items[] = "Directory: " . $item->getPathname() . " (755)";
                } else {
                    // Set executable permission for .php and .sh files
                    $extension = strtolower(pathinfo($item->getPathname(), PATHINFO_EXTENSION));
                    $perms = in_array($extension, ['php', 'sh']) ? 0755 : $filePerms;
                    chmod($item->getPathname(), $perms);
                    $items[] = "File: " . $item->getPathname() . " (" . decoct($perms) . ")";
                }
            }
        } else if (is_file($path)) {
            chmod($path, $filePerms);
            $items[] = "File: $path (" . decoct($filePerms) . ")";
        }
        
        return $items;
    }
    
    // Get extracted directory name (assuming it matches the archive name without extension)
    $extractedDir = pathinfo($tarFile, PATHINFO_FILENAME);
    if (substr($extractedDir, -4) === '.tar') {
        $extractedDir = substr($extractedDir, 0, -4);
    }
    
    // Set permissions for extracted content
    if (is_dir($extractedDir)) {
        $permissionItems = setPermissionsRecursive($extractedDir);
        $response['details'][] = "✓ Set permissions for " . count($permissionItems) . " items";
    } else {
        // If no specific directory, set permissions for current directory content
        $files = glob('*');
        foreach ($files as $file) {
            if ($file !== $tarFile && $file !== basename(__FILE__)) {
                if (is_dir($file)) {
                    $permissionItems = setPermissionsRecursive($file);
                    $response['details'][] = "✓ Set permissions for directory: $file";
                } else {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $perms = in_array($extension, ['php', 'sh']) ? 0755 : 0644;
                    chmod($file, $perms);
                    $response['details'][] = "✓ Set permissions for file: $file (" . decoct($perms) . ")";
                }
            }
        }
    }
    
    // Verify extraction by listing extracted files
    $response['details'][] = "Verifying extraction...";
    $extractedFiles = [];
    
    if (is_dir($extractedDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractedDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $extractedFiles[] = $file->getPathname();
        }
    } else {
        // List all files except the archive and this script
        $allFiles = glob('*');
        foreach ($allFiles as $file) {
            if ($file !== $tarFile && $file !== basename(__FILE__)) {
                $extractedFiles[] = $file;
            }
        }
    }
    
    $response['details'][] = "✓ Extracted " . count($extractedFiles) . " files/directories";
    
    // Clean up the tar.gz file
    $response['details'][] = "Cleaning up archive file...";
    if (unlink($tarFile)) {
        $response['details'][] = "✓ Archive file deleted successfully";
    } else {
        $response['details'][] = "⚠ Warning: Could not delete archive file";
    }
    
    // Success response
    $response['success'] = true;
    $response['message'] = "Extraction completed successfully";
    $response['extracted_files_count'] = count($extractedFiles);
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = "Extraction failed: " . $e->getMessage();
    $response['details'][] = "✗ Error: " . $e->getMessage();
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Clean up this script after execution (optional)
// Uncomment the following line if you want the script to self-delete
// @unlink(__FILE__);
?>'''
    
    return php_script

def main():
    """Main execution function"""
    
    print("=== Extract Test Deployment Script ===")
    print(f"Target: {FTP_HOST}{FTP_PATH}")
    print()
    
    # Initialize FTP connection
    ftp_conn = FTPSConnection(FTP_HOST, FTP_USER, FTP_PASS)
    
    try:
        # Step 1: Connect to FTPS server
        if not ftp_conn.connect():
            print("✗ Failed to establish FTPS connection")
            return False
        
        # Step 2: Navigate to target directory
        if not ftp_conn.navigate_to_path(FTP_PATH):
            print("✗ Failed to navigate to target directory")
            return False
        
        # Check if tar.gz file exists
        if not ftp_conn.file_exists("aze-test-complete.tar.gz"):
            print("✗ Archive file 'aze-test-complete.tar.gz' not found in target directory")
            print("Available files:", ftp_conn.list_files())
            return False
        
        print("✓ Archive file 'aze-test-complete.tar.gz' found")
        
        # Step 3: Create and upload PHP extraction script
        print("\nCreating PHP extraction script...")
        
        php_script_content = create_php_extraction_script()
        php_script_filename = "extract_archive.php"
        local_php_file = f"/tmp/{php_script_filename}"
        
        # Write PHP script to local temp file
        with open(local_php_file, 'w', encoding='utf-8') as f:
            f.write(php_script_content)
        
        print(f"✓ PHP script created locally: {local_php_file}")
        
        # Upload PHP script to FTP server
        if not ftp_conn.upload_file(local_php_file, php_script_filename):
            print("✗ Failed to upload PHP extraction script")
            return False
        
        # Clean up local temp file
        os.unlink(local_php_file)
        
        print("✓ PHP extraction script uploaded successfully")
        
        # Step 4: Execute PHP script via HTTP request
        print(f"\nExecuting PHP script via HTTP...")
        
        # Construct URL (you may need to adjust this based on your domain configuration)
        script_url = urljoin(HTTP_BASE_URL, php_script_filename)
        
        print(f"Requesting: {script_url}")
        
        try:
            # Make HTTP request with timeout
            response = requests.get(script_url, timeout=60, verify=False)  # verify=False for self-signed certs
            
            if response.status_code == 200:
                print("✓ HTTP request successful")
                
                # Parse JSON response
                try:
                    result = response.json()
                    
                    print(f"\nExtraction Result:")
                    print(f"Success: {result.get('success', False)}")
                    print(f"Message: {result.get('message', 'No message')}")
                    print(f"Timestamp: {result.get('timestamp', 'Unknown')}")
                    
                    if 'extracted_files_count' in result:
                        print(f"Files extracted: {result['extracted_files_count']}")
                    
                    print(f"\nDetails:")
                    for detail in result.get('details', []):
                        print(f"  {detail}")
                    
                    if result.get('success', False):
                        print("\n✓ Extraction completed successfully!")
                        
                        # Step 5: Verify extraction by listing files again
                        print(f"\nVerifying extraction by listing directory contents...")
                        files_after = ftp_conn.list_files()
                        print(f"Files after extraction: {files_after}")
                        
                        return True
                    else:
                        print(f"\n✗ Extraction failed: {result.get('message', 'Unknown error')}")
                        return False
                        
                except ValueError as e:
                    print(f"✗ Failed to parse JSON response: {e}")
                    print(f"Response content: {response.text}")
                    return False
                    
            else:
                print(f"✗ HTTP request failed with status code: {response.status_code}")
                print(f"Response: {response.text}")
                return False
                
        except requests.exceptions.RequestException as e:
            print(f"✗ HTTP request failed: {e}")
            print(f"Note: You may need to adjust the HTTP_BASE_URL variable")
            print(f"Current URL: {script_url}")
            return False
        
    except Exception as e:
        print(f"✗ Unexpected error: {e}")
        return False
        
    finally:
        # Always close FTP connection
        ftp_conn.close()

if __name__ == "__main__":
    success = main()
    
    if success:
        print(f"\n🎉 Deployment extraction completed successfully!")
        sys.exit(0)
    else:
        print(f"\n❌ Deployment extraction failed!")
        sys.exit(1)
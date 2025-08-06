# Extract Test Deployment Script

This script automates the extraction of the `aze-test-complete.tar.gz` file on the remote server using FTPS and HTTP.

## Overview

The script performs the following steps:

1. **FTPS Connection**: Connects securely to the FTP server using FTPS (FTP over TLS)
2. **Navigation**: Navigates to the `/www/aze-test/` directory
3. **PHP Script Creation**: Creates a PHP script that handles the extraction process
4. **Upload**: Uploads the PHP script to the server
5. **HTTP Execution**: Executes the PHP script via HTTP request
6. **Verification**: Verifies that the extraction was successful
7. **Cleanup**: The PHP script cleans up the tar.gz file after extraction

## Files Created

- `extract_test_deployment.py` - Main extraction script
- `test_extract_deployment.py` - Validation and testing script
- `run_extract_deployment.sh` - Wrapper script with virtual environment support
- `EXTRACT_DEPLOYMENT_README.md` - This documentation

## Prerequisites

- Python 3.6 or higher
- `requests` library (automatically installed by the wrapper script)
- Network access to the FTP server
- HTTP access to the deployed website

## Configuration

The script uses the following configuration (in `extract_test_deployment.py`):

```python
FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"
FTP_PATH = "/www/aze-test/"
HTTP_BASE_URL = "https://aze-test.server-he.de/"
```

**Important**: You may need to adjust the `HTTP_BASE_URL` based on your actual domain configuration.

## Usage

### Method 1: Using the Wrapper Script (Recommended)

```bash
./run_extract_deployment.sh
```

This script automatically:
- Activates the virtual environment
- Installs required dependencies
- Runs the extraction script
- Provides clear status messages

### Method 2: Direct Python Execution

```bash
# Activate virtual environment (if using one)
source venv/bin/activate

# Install dependencies
pip install requests

# Run the script
python3 extract_test_deployment.py
```

### Method 3: Testing/Validation Only

```bash
# Activate virtual environment
source venv/bin/activate

# Run validation tests
python3 test_extract_deployment.py
```

## What the PHP Script Does

The generated PHP script (`extract_archive.php`) performs these operations:

1. **Verification**: Checks if `aze-test-complete.tar.gz` exists
2. **Extraction**: Extracts the tar.gz file using PharData or system tar command
3. **Permissions**: Sets appropriate permissions:
   - Directories: 755 (readable, writable, executable by owner; readable and executable by group and others)
   - PHP/Shell files: 755 (executable)
   - Other files: 644 (readable and writable by owner; readable by group and others)
4. **Cleanup**: Removes the tar.gz file after successful extraction
5. **Response**: Returns a JSON response with detailed status information

## Expected Output

### Successful Execution

```
=== Extract Test Deployment Script ===
Target: wp10454681.server-he.de/www/aze-test/

Connecting to FTPS server: wp10454681.server-he.de
✓ FTPS connection established successfully
✓ Successfully navigated to /www/aze-test/
✓ Archive file 'aze-test-complete.tar.gz' found

Creating PHP extraction script...
✓ PHP script created locally: /tmp/extract_archive.php
Uploading extract_archive.php as extract_archive.php
✓ Successfully uploaded extract_archive.php
✓ PHP extraction script uploaded successfully

Executing PHP script via HTTP...
Requesting: https://aze-test.server-he.de/extract_archive.php
✓ HTTP request successful

Extraction Result:
Success: True
Message: Extraction completed successfully
Timestamp: 2025-08-05 22:30:15
Files extracted: 25

Details:
  Found archive file: aze-test-complete.tar.gz
  Archive size: 1,234,567 bytes
  Starting extraction...
  ✓ Archive extracted successfully using PharData
  Setting file permissions...
  ✓ Set permissions for 25 items
  Verifying extraction...
  ✓ Extracted 25 files/directories
  Cleaning up archive file...
  ✓ Archive file deleted successfully

✓ Extraction completed successfully!

Verifying extraction by listing directory contents...
Files after extraction: ['index.php', 'config', 'assets', 'api', ...]
✓ FTPS connection closed

🎉 Deployment extraction completed successfully!
```

### Error Scenarios

The script handles various error conditions:

- **FTP Connection Issues**: Network problems, wrong credentials
- **Missing Archive**: tar.gz file not found on server
- **HTTP Request Failures**: Domain configuration issues, server problems
- **Extraction Errors**: Corrupted archive, permission issues
- **PHP Execution Errors**: Server-side PHP problems

## Troubleshooting

### Common Issues

1. **"requests module not found"**
   - Solution: Use the wrapper script or install: `pip install requests`

2. **"FTPS connection failed"**
   - Check network connectivity
   - Verify FTP credentials
   - Ensure FTPS is enabled on the server

3. **"HTTP request failed"**
   - Verify the `HTTP_BASE_URL` configuration
   - Check if the domain is properly configured
   - Try alternative URLs:
     - `http://wp10454681.server-he.de/aze-test/`
     - `https://your-actual-domain.com/aze-test/`

4. **"Archive file not found"**
   - Ensure `aze-test-complete.tar.gz` exists in `/www/aze-test/`
   - Check file permissions on the server

### Debugging

Enable verbose FTP debugging by changing in the script:
```python
self.ftp.set_debuglevel(2)  # Change from 0 to 2
```

## Security Considerations

- The script uses FTPS (FTP over TLS) for secure file transfer
- Credentials are stored in the script (consider environment variables for production)
- The PHP script includes basic security measures
- The extraction process sets appropriate file permissions
- The PHP script can optionally self-delete after execution

## File Permissions Set by the Script

- **Directories**: 755 (drwxr-xr-x)
- **PHP Files**: 755 (executable)
- **Shell Scripts**: 755 (executable)
- **Other Files**: 644 (readable/writable by owner, readable by others)

## Customization

You can modify the script for different scenarios:

1. **Different Archive Names**: Change the filename in the PHP script template
2. **Different Paths**: Modify `FTP_PATH` and `HTTP_BASE_URL`
3. **Different Permissions**: Adjust the permission settings in the PHP script
4. **Additional Verification**: Add more checks in the PHP script

## Support

If you encounter issues:

1. Run the validation script: `python3 test_extract_deployment.py`
2. Check the error messages for specific guidance
3. Verify your network connectivity and server configuration
4. Ensure the virtual environment is properly set up

---

**Created**: August 5, 2025  
**Version**: 1.0  
**Compatibility**: Linux, macOS, Windows (with appropriate Python setup)
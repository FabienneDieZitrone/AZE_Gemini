import ftplib
import tempfile

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

try:
    ftp = ftplib.FTP_TLS(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    
    print("📥 Downloading production index.html to verify content...")
    
    # Download index.html
    with tempfile.NamedTemporaryFile(mode='w+b', delete=False) as temp_file:
        ftp.retrbinary('RETR index.html', temp_file.write)
        temp_file_path = temp_file.name
    
    # Read and display content
    with open(temp_file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    print("📄 Production index.html content:")
    print("=" * 50)
    print(content)
    print("=" * 50)
    
    # Check for correct script tag
    if '/assets/index-DsjfTLkB.js' in content:
        print("✅ CORRECT: index.html references the built JavaScript bundle\!")
    else:
        print("❌ ERROR: index.html does not reference the correct JS bundle")
    
    # Check for CSS
    if '/assets/index-Jq3KfgsT.css' in content:
        print("✅ CORRECT: index.html references the built CSS file\!")
    else:
        print("❌ ERROR: index.html does not reference the correct CSS file")
    
    # Check for broken TypeScript reference
    if '/src/index.tsx' in content:
        print("❌ ERROR: index.html still contains broken TypeScript reference\!")
    else:
        print("✅ CORRECT: No broken TypeScript references found\!")
    
    ftp.quit()
    
    # Cleanup
    import os
    os.unlink(temp_file_path)
    
    print("\n🎉 PRODUCTION FIX VERIFICATION COMPLETE\!")
    
except Exception as e:
    print(f"❌ Error: {e}")

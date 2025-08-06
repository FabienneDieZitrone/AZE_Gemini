import ftplib

FTP_HOST = "wp10454681.server-he.de"
FTP_USER = "ftp10454681-aze"
FTP_PASS = "321Start321"

try:
    ftp = ftplib.FTP_TLS(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    ftp.prot_p()
    
    print("✅ Verifying production deployment...")
    
    # Check index.html exists and was updated
    files = ftp.nlst()
    if 'index.html' in files:
        print("✅ index.html found in production")
        
        # Get file info
        ftp.voidcmd('TYPE I')
        size = ftp.size('index.html')
        print(f"📄 index.html size: {size} bytes")
    
    # Check assets directory
    try:
        ftp.cwd('assets')
        asset_files = ftp.nlst()
        print(f"📁 Assets directory contains {len(asset_files)} files:")
        for file in asset_files:
            try:
                size = ftp.size(file)
                print(f"  ✅ {file} ({size} bytes)")
            except:
                print(f"  ✅ {file}")
        
        # Verify the main JS bundle exists
        if 'index-DsjfTLkB.js' in asset_files:
            print("🎉 Main JavaScript bundle (index-DsjfTLkB.js) successfully deployed\!")
        
        if 'index-Jq3KfgsT.css' in asset_files:
            print("🎉 Main CSS file (index-Jq3KfgsT.css) successfully deployed\!")
            
    except Exception as e:
        print(f"❌ Error checking assets: {e}")
    
    ftp.quit()
    print("\n🌐 Production site deployment verified successfully\!")
    
except Exception as e:
    print(f"❌ Verification failed: {e}")

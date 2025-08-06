#!/usr/bin/env python3
"""
Simple MFA Test - No external dependencies
Tests basic MFA functionality
"""

import urllib.request
import urllib.error
import json
import ssl
from datetime import datetime

# Disable SSL verification for test environment
ssl_context = ssl.create_default_context()
ssl_context.check_hostname = False
ssl_context.verify_mode = ssl.CERT_NONE

TEST_URL = "https://aze.mikropartner.de/aze-test"

def test_endpoint(endpoint, method="GET", data=None):
    """Test an API endpoint"""
    url = f"{TEST_URL}{endpoint}"
    
    try:
        if data:
            data = json.dumps(data).encode('utf-8')
            req = urllib.request.Request(url, data=data, method=method)
            req.add_header('Content-Type', 'application/json')
        else:
            req = urllib.request.Request(url, method=method)
        
        response = urllib.request.urlopen(req, context=ssl_context, timeout=10)
        status = response.getcode()
        body = response.read().decode('utf-8')
        
        return {
            "success": True,
            "status": status,
            "body": body
        }
    except urllib.error.HTTPError as e:
        return {
            "success": False,
            "status": e.code,
            "error": str(e.reason)
        }
    except Exception as e:
        return {
            "success": False,
            "status": 0,
            "error": str(e)
        }

def run_tests():
    """Run MFA tests"""
    print("🧪 Simple MFA Test Suite")
    print("=" * 50)
    
    tests_passed = 0
    tests_failed = 0
    
    # Test 1: MFA Setup Endpoint
    print("\n1. Testing MFA Setup Endpoint...")
    result = test_endpoint("/api/mfa-setup.php")
    if result["status"] in [200, 401, 405]:  # Expected responses
        print("✅ MFA Setup endpoint is accessible")
        tests_passed += 1
    else:
        print(f"❌ MFA Setup endpoint failed: {result['error']}")
        tests_failed += 1
    
    # Test 2: MFA Verify Endpoint
    print("\n2. Testing MFA Verify Endpoint...")
    result = test_endpoint("/api/mfa-verify.php", method="POST")
    if result["status"] in [400, 401, 405]:  # Expected for missing data
        print("✅ MFA Verify endpoint is accessible")
        tests_passed += 1
    else:
        print(f"❌ MFA Verify endpoint failed: {result['error']}")
        tests_failed += 1
    
    # Test 3: Database Schema Check
    print("\n3. Testing Database Schema...")
    result = test_endpoint("/database/mfa_schema.sql")
    if result["success"] and "ALTER TABLE" in result.get("body", ""):
        print("✅ Database schema file is accessible")
        tests_passed += 1
    else:
        print("❌ Database schema file not found")
        tests_failed += 1
    
    # Test 4: Config File
    print("\n4. Testing MFA Configuration...")
    result = test_endpoint("/config/mfa.php")
    if result["status"] in [200, 403]:  # May be protected
        print("✅ MFA config file exists")
        tests_passed += 1
    else:
        print("❌ MFA config file not found")
        tests_failed += 1
    
    # Test 5: Login with MFA
    print("\n5. Testing Login with MFA...")
    result = test_endpoint("/api/login-with-mfa.php", method="POST", data={})
    if result["status"] in [400, 401, 405]:  # Expected for no auth
        print("✅ Login with MFA endpoint is accessible")
        tests_passed += 1
    else:
        print(f"❌ Login with MFA failed: {result['error']}")
        tests_failed += 1
    
    # Summary
    print("\n" + "=" * 50)
    print(f"📊 Test Summary:")
    print(f"✅ Passed: {tests_passed}")
    print(f"❌ Failed: {tests_failed}")
    print(f"📈 Success Rate: {(tests_passed/(tests_passed+tests_failed)*100):.1f}%")
    
    # Recommendations
    print("\n💡 Recommendations:")
    if tests_failed == 0:
        print("✅ All tests passed! MFA system appears to be deployed correctly.")
    else:
        if tests_failed > 3:
            print("⚠️  Multiple failures detected. Check deployment status.")
        print("📝 Next steps:")
        print("1. Ensure database migration has been run")
        print("2. Verify file permissions on the server")
        print("3. Check PHP error logs for detailed errors")
    
    return tests_passed, tests_failed

if __name__ == "__main__":
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"Test execution started at: {timestamp}")
    
    try:
        passed, failed = run_tests()
        
        # Create simple report
        report = f"""
MFA Test Report
Generated: {timestamp}

Tests Passed: {passed}
Tests Failed: {failed}
Success Rate: {(passed/(passed+failed)*100):.1f}%

Status: {'READY' if failed == 0 else 'NEEDS ATTENTION'}
"""
        
        with open("mfa_test_report.txt", "w") as f:
            f.write(report)
        
        print(f"\n📄 Report saved to: mfa_test_report.txt")
        
    except Exception as e:
        print(f"\n❌ Test execution error: {e}")
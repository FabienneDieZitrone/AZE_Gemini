#!/usr/bin/env python3
"""
Test authorization fixes on production API
Tests for Issue #74 - Authorization vulnerability
"""

import requests
import json
from datetime import datetime

# Production API configuration
BASE_URL = "https://aze.mikropartner.de/api"

def test_unauthenticated_access():
    """Test that unauthenticated requests are properly blocked"""
    print("\n=== Testing Unauthenticated Access ===")
    
    endpoints = [
        "/time-entries.php",
        "/users.php",
        "/approvals.php",
        "/history.php"
    ]
    
    results = []
    
    for endpoint in endpoints:
        try:
            response = requests.get(BASE_URL + endpoint, verify=False)
            if response.status_code == 401:
                print(f"✅ {endpoint}: Correctly returns 401 Unauthorized")
                results.append({"endpoint": endpoint, "status": "PASS", "code": 401})
            else:
                print(f"❌ {endpoint}: Expected 401, got {response.status_code}")
                results.append({"endpoint": endpoint, "status": "FAIL", "code": response.status_code})
        except Exception as e:
            print(f"❌ {endpoint}: Error - {str(e)}")
            results.append({"endpoint": endpoint, "status": "ERROR", "error": str(e)})
    
    return results

def test_role_change_protection():
    """Test that role changes require admin privileges"""
    print("\n=== Testing Role Change Protection ===")
    
    # Try to change a role without authentication
    try:
        data = {"userId": 1, "newRole": "Admin"}
        response = requests.patch(
            BASE_URL + "/users.php",
            json=data,
            verify=False
        )
        
        if response.status_code == 401:
            print("✅ Role change: Correctly returns 401 for unauthenticated request")
            return {"status": "PASS", "code": 401}
        else:
            print(f"❌ Role change: Expected 401, got {response.status_code}")
            return {"status": "FAIL", "code": response.status_code}
    except Exception as e:
        print(f"❌ Role change: Error - {str(e)}")
        return {"status": "ERROR", "error": str(e)}

def test_sql_injection_protection():
    """Test SQL injection protection"""
    print("\n=== Testing SQL Injection Protection ===")
    
    injections = [
        "1' OR '1'='1",
        "1; DROP TABLE users;--",
        "1 UNION SELECT * FROM users"
    ]
    
    results = []
    
    for injection in injections:
        try:
            response = requests.get(
                BASE_URL + "/time-entries.php",
                params={"user_id": injection},
                verify=False
            )
            
            # We expect 401 (unauthorized) or 400 (bad request)
            if response.status_code in [400, 401]:
                print(f"✅ Injection blocked: {injection[:30]}...")
                results.append({"injection": injection, "status": "BLOCKED"})
            else:
                print(f"⚠️  Injection response: {response.status_code} for {injection[:30]}...")
                results.append({"injection": injection, "status": "CHECK", "code": response.status_code})
        except Exception as e:
            print(f"❌ Injection test error: {str(e)}")
            results.append({"injection": injection, "status": "ERROR"})
    
    return results

def generate_report(all_results):
    """Generate comprehensive test report"""
    print("\n" + "="*60)
    print("SECURITY TEST REPORT - PRODUCTION API")
    print("="*60)
    print(f"Test Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"API Endpoint: {BASE_URL}")
    
    # Summary
    total_tests = 0
    passed = 0
    
    for category, results in all_results.items():
        if isinstance(results, list):
            for result in results:
                total_tests += 1
                if result.get('status') in ['PASS', 'BLOCKED'] or result.get('code') == 401:
                    passed += 1
        else:
            total_tests += 1
            if results.get('status') == 'PASS' or results.get('code') == 401:
                passed += 1
    
    print(f"\nTotal Tests: {total_tests}")
    print(f"Passed: {passed}")
    print(f"Failed/Warnings: {total_tests - passed}")
    
    # Save detailed report
    report = {
        "test_date": datetime.now().isoformat(),
        "api_endpoint": BASE_URL,
        "summary": {
            "total": total_tests,
            "passed": passed,
            "failed": total_tests - passed
        },
        "results": all_results
    }
    
    with open("production_auth_test_report.json", "w") as f:
        json.dump(report, f, indent=2)
    
    print("\nDetailed report saved to: production_auth_test_report.json")
    
    return passed == total_tests

def main():
    """Run all security tests"""
    print("=== AZE Gemini Production Security Test ===")
    print("Testing Issue #74 Authorization Fixes")
    
    import warnings
    warnings.filterwarnings('ignore', message='Unverified HTTPS request')
    
    all_results = {}
    
    # Run tests
    all_results['unauthenticated_access'] = test_unauthenticated_access()
    all_results['role_change_protection'] = test_role_change_protection()
    all_results['sql_injection'] = test_sql_injection_protection()
    
    # Generate report
    all_passed = generate_report(all_results)
    
    if all_passed:
        print("\n✅ All security tests passed!")
        print("\n⚠️  IMPORTANT: These are only basic security checks.")
        print("Full testing with authenticated users is still required.")
    else:
        print("\n⚠️  Some tests had unexpected results.")
        print("Please review the detailed report.")
    
    print("\n" + "="*60)

if __name__ == "__main__":
    main()
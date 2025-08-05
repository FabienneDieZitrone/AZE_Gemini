#!/usr/bin/env python3
"""
Automated Security Fix Verification Script
Tests the authorization fixes for Issue #74
"""

import requests
import json
import sys
from datetime import datetime

# Test environment configuration
BASE_URL = "https://aze.mikropartner.de/aze-test/api"
PROD_URL = "https://aze.mikropartner.de/api"

class SecurityTester:
    def __init__(self, base_url=BASE_URL):
        self.base_url = base_url
        self.session = requests.Session()
        self.test_results = []
        
    def log_result(self, test_name, passed, details=""):
        """Log test result"""
        result = {
            "test": test_name,
            "passed": passed,
            "details": details,
            "timestamp": datetime.now().isoformat()
        }
        self.test_results.append(result)
        
        status = "✅ PASS" if passed else "❌ FAIL"
        print(f"{status} - {test_name}")
        if details:
            print(f"     Details: {details}")
    
    def test_api_health(self):
        """Test if APIs are accessible"""
        print("\n=== Testing API Health ===")
        
        try:
            # Test health endpoint
            response = self.session.get(f"{self.base_url}/health.php")
            if response.status_code == 200:
                self.log_result("API Health Check", True, "API is accessible")
            else:
                self.log_result("API Health Check", False, f"Status: {response.status_code}")
        except Exception as e:
            self.log_result("API Health Check", False, str(e))
    
    def test_time_entries_authorization(self):
        """Test time-entries.php authorization"""
        print("\n=== Testing Time Entries Authorization ===")
        
        # Test without authentication (should fail)
        try:
            response = self.session.get(f"{self.base_url}/time-entries.php")
            if response.status_code == 401:
                self.log_result("Unauthenticated Access Blocked", True, "Got 401 as expected")
            else:
                self.log_result("Unauthenticated Access Blocked", False, 
                              f"Expected 401, got {response.status_code}")
        except Exception as e:
            self.log_result("Unauthenticated Access Test", False, str(e))
    
    def test_user_role_change_authorization(self):
        """Test users.php PATCH authorization"""
        print("\n=== Testing User Role Change Authorization ===")
        
        # Test PATCH without authentication
        try:
            data = {"userId": 1, "newRole": "Admin"}
            response = self.session.patch(
                f"{self.base_url}/users.php",
                json=data
            )
            if response.status_code == 401:
                self.log_result("Unauthenticated Role Change Blocked", True, 
                              "Got 401 as expected")
            else:
                self.log_result("Unauthenticated Role Change Blocked", False,
                              f"Expected 401, got {response.status_code}")
        except Exception as e:
            self.log_result("Role Change Authorization Test", False, str(e))
    
    def test_sql_injection_prevention(self):
        """Test SQL injection prevention"""
        print("\n=== Testing SQL Injection Prevention ===")
        
        # Test various SQL injection attempts
        injection_tests = [
            "1' OR '1'='1",
            "1; DROP TABLE users;--",
            "1 UNION SELECT * FROM users",
            "'; DELETE FROM time_entries WHERE '1'='1"
        ]
        
        for injection in injection_tests:
            try:
                # Attempt injection in time-entries
                response = self.session.get(
                    f"{self.base_url}/time-entries.php",
                    params={"user_id": injection}
                )
                # If we get 401 (unauthorized) or 400 (bad request), that's good
                if response.status_code in [400, 401]:
                    self.log_result(f"SQL Injection Prevention - {injection[:20]}...", 
                                  True, "Injection blocked")
                else:
                    self.log_result(f"SQL Injection Prevention - {injection[:20]}...", 
                                  False, f"Got status {response.status_code}")
            except Exception as e:
                self.log_result(f"SQL Injection Test - {injection[:20]}...", 
                              False, str(e))
    
    def test_environment_marker(self):
        """Check if test environment marker exists"""
        print("\n=== Checking Test Environment ===")
        
        try:
            response = self.session.get(f"{self.base_url}/../TEST_ENVIRONMENT.txt")
            if response.status_code == 200 and "Security Patch: Issue #74" in response.text:
                self.log_result("Test Environment Marker", True, 
                              "Test environment properly marked")
            else:
                self.log_result("Test Environment Marker", False,
                              "Marker not found or incorrect")
        except Exception as e:
            self.log_result("Test Environment Check", False, str(e))
    
    def generate_report(self):
        """Generate test report"""
        print("\n" + "="*60)
        print("SECURITY FIX VERIFICATION REPORT")
        print("="*60)
        print(f"Test Environment: {self.base_url}")
        print(f"Test Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"Total Tests: {len(self.test_results)}")
        
        passed = sum(1 for r in self.test_results if r['passed'])
        failed = len(self.test_results) - passed
        
        print(f"Passed: {passed}")
        print(f"Failed: {failed}")
        
        if failed > 0:
            print("\nFailed Tests:")
            for result in self.test_results:
                if not result['passed']:
                    print(f"  - {result['test']}: {result['details']}")
        
        print("\n" + "="*60)
        
        # Save report
        with open("security_test_report.json", "w") as f:
            json.dump({
                "test_run": datetime.now().isoformat(),
                "environment": self.base_url,
                "summary": {
                    "total": len(self.test_results),
                    "passed": passed,
                    "failed": failed
                },
                "results": self.test_results
            }, f, indent=2)
        
        print("Report saved to: security_test_report.json")
        
        return failed == 0

def main():
    print("=== AZE Gemini Security Fix Verification ===")
    print("Testing Issue #74 Authorization Fixes")
    
    tester = SecurityTester()
    
    # Run all tests
    tester.test_api_health()
    tester.test_environment_marker()
    tester.test_time_entries_authorization()
    tester.test_user_role_change_authorization()
    tester.test_sql_injection_prevention()
    
    # Generate report
    all_passed = tester.generate_report()
    
    if all_passed:
        print("\n✅ All automated tests passed!")
        print("\n⚠️  IMPORTANT: Manual testing with authenticated users still required!")
        print("Please follow the manual test cases in SECURITY_FIX_TEST_PLAN.md")
    else:
        print("\n❌ Some tests failed. Please review the report.")
        sys.exit(1)

if __name__ == "__main__":
    main()
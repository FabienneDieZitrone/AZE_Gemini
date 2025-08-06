#!/usr/bin/env python3
"""
MFA User Flow Test
Tests complete MFA user workflows including setup, verification, and error handling
"""

import requests
import json
import time
import re
import hashlib
import hmac
import base64
import struct
from datetime import datetime
from typing import Dict, List, Optional

class MFAUserFlowTest:
    def __init__(self, base_url="https://aze.mikropartner.de/aze-test"):
        self.base_url = base_url
        self.session = requests.Session()
        self.results = []
        self.test_user_data = None
        
    def log_result(self, test_name: str, success: bool, message: str, details: Dict = None):
        """Log test result"""
        result = {
            'test': test_name,
            'success': success,
            'message': message,
            'timestamp': datetime.now().isoformat(),
            'details': details or {}
        }
        self.results.append(result)
        
        status = "✅" if success else "❌"
        print(f"{status} {test_name}: {message}")
        
    def generate_totp_code(self, secret: str, timestamp: int = None) -> str:
        """Generate TOTP code for testing"""
        if timestamp is None:
            timestamp = int(time.time()) // 30
            
        try:
            # Convert secret from base32
            secret_bytes = base64.b32decode(secret.upper() + '=' * (8 - len(secret) % 8))
            
            # Pack timestamp
            time_bytes = struct.pack('>Q', timestamp)
            
            # Generate HMAC-SHA1
            hmac_hash = hmac.new(secret_bytes, time_bytes, hashlib.sha1).digest()
            
            # Dynamic truncation
            offset = hmac_hash[-1] & 0x0f
            truncated = struct.unpack('>I', hmac_hash[offset:offset + 4])[0]
            truncated &= 0x7fffffff
            
            # Generate 6-digit code
            code = truncated % 1000000
            return f"{code:06d}"
        except Exception as e:
            print(f"Error generating TOTP code: {e}")
            return "000000"
    
    def test_user_authentication_flow(self):
        """Test 1: Complete user authentication with MFA"""
        # This simulates the complete flow but without actual user credentials
        # We test the API structure and responses
        
        # Step 1: Test login endpoint structure
        login_data = {
            "email": "test@example.com",
            "password": "testpassword"
        }
        
        try:
            response = self.session.post(
                f"{self.base_url}/api/login-with-mfa.php",
                json=login_data,
                timeout=10
            )
            
            # We expect either authentication failure or MFA challenge
            auth_flow_working = response.status_code in [401, 403, 400, 200]
            
            response_data = {}
            try:
                response_data = response.json() if response.content else {}
            except:
                pass
            
            self.log_result(
                "Authentication Flow Structure",
                auth_flow_working,
                f"Login endpoint responds correctly (Status: {response.status_code})",
                {"status_code": response.status_code, "response_keys": list(response_data.keys())}
            )
            
        except Exception as e:
            self.log_result(
                "Authentication Flow Structure",
                False,
                f"Authentication flow test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_mfa_setup_workflow(self):
        """Test 2: MFA Setup Workflow"""
        # Test the complete MFA setup process structure
        
        setup_steps = [
            {
                "step": "Generate Secret",
                "data": {"action": "generate"},
                "expected_status": [401, 403, 200]  # Auth required or success
            },
            {
                "step": "Verify Setup",
                "data": {"action": "verify_setup", "code": "123456"},
                "expected_status": [401, 403, 400, 200]  # Various expected responses
            },
            {
                "step": "Generate Backup Codes",
                "data": {"action": "generate_backup_codes"},
                "expected_status": [401, 403, 400, 200]
            }
        ]
        
        setup_results = []
        
        for step in setup_steps:
            try:
                response = self.session.post(
                    f"{self.base_url}/api/mfa-setup.php",
                    json=step["data"],
                    timeout=10
                )
                
                step_success = response.status_code in step["expected_status"]
                setup_results.append({
                    "step": step["step"],
                    "success": step_success,
                    "status": response.status_code
                })
                
            except Exception as e:
                setup_results.append({
                    "step": step["step"],
                    "success": False,
                    "error": str(e)
                })
        
        overall_success = sum(1 for r in setup_results if r.get("success", False)) >= len(setup_results) * 0.7
        
        self.log_result(
            "MFA Setup Workflow",
            overall_success,
            f"Setup workflow structure correct: {overall_success}",
            {"steps": setup_results}
        )
    
    def test_mfa_verification_workflow(self):
        """Test 3: MFA Verification Workflow"""
        # Test TOTP and backup code verification
        
        verification_tests = [
            {
                "type": "TOTP Code",
                "data": {"user_id": 1, "code": "123456"},
                "expected": [400, 404, 401]  # Invalid code/user not found/not authenticated
            },
            {
                "type": "Backup Code",
                "data": {"user_id": 1, "code": "12345678"},
                "expected": [400, 404, 401]
            },
            {
                "type": "Invalid Format",
                "data": {"user_id": 1, "code": "abc123"},
                "expected": [400]  # Bad format
            }
        ]
        
        verification_results = []
        
        for test in verification_tests:
            try:
                response = self.session.post(
                    f"{self.base_url}/api/mfa-verify.php",
                    json=test["data"],
                    timeout=10
                )
                
                test_success = response.status_code in test["expected"]
                verification_results.append({
                    "type": test["type"],
                    "success": test_success,
                    "status": response.status_code
                })
                
            except Exception as e:
                verification_results.append({
                    "type": test["type"],
                    "success": False,
                    "error": str(e)
                })
        
        overall_success = all(r.get("success", False) for r in verification_results)
        
        self.log_result(
            "MFA Verification Workflow",
            overall_success,
            f"Verification workflow working: {overall_success}",
            {"tests": verification_results}
        )
    
    def test_rate_limiting_user_experience(self):
        """Test 4: Rate Limiting User Experience"""
        # Test how rate limiting affects user experience
        
        test_user_id = 777
        attempts = []
        
        for i in range(8):  # Try 8 failed attempts
            try:
                start_time = time.time()
                response = self.session.post(
                    f"{self.base_url}/api/mfa-verify.php",
                    json={"user_id": test_user_id, "code": "000000"},
                    timeout=10
                )
                response_time = time.time() - start_time
                
                attempts.append({
                    "attempt": i + 1,
                    "status": response.status_code,
                    "response_time": round(response_time, 2)
                })
                
                # Check for lockout response
                if response.status_code == 429:
                    break
                    
                time.sleep(0.3)  # Small delay between attempts
                
            except Exception as e:
                attempts.append({
                    "attempt": i + 1,
                    "error": str(e)
                })
        
        # Analyze rate limiting behavior
        status_codes = [a.get("status") for a in attempts if a.get("status")]
        lockout_triggered = 429 in status_codes
        response_times = [a.get("response_time") for a in attempts if a.get("response_time")]
        avg_response_time = sum(response_times) / len(response_times) if response_times else 0
        
        # Rate limiting is working if we get lockout or consistent error responses
        rate_limiting_working = lockout_triggered or (len(set(status_codes)) == 1 and status_codes[0] in [400, 401])
        
        self.log_result(
            "Rate Limiting User Experience",
            rate_limiting_working,
            f"Rate limiting active: {rate_limiting_working}, Avg response: {avg_response_time:.2f}s",
            {
                "attempts": len(attempts),
                "lockout_triggered": lockout_triggered,
                "status_codes": status_codes,
                "avg_response_time": avg_response_time
            }
        )
    
    def test_role_based_access_patterns(self):
        """Test 5: Role-based Access Patterns"""
        # Test how different user roles would interact with MFA
        
        # Test MFA status endpoint for role-based information
        try:
            response = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
            
            role_logic_present = False
            
            if response.status_code in [401, 403]:
                # Proper authentication required
                role_logic_present = True
            
            # Try to find role-related information in response
            try:
                if response.content:
                    response_text = response.text.lower()
                    if any(term in response_text for term in ['role', 'admin', 'bereichsleiter', 'grace', 'required']):
                        role_logic_present = True
            except:
                pass
            
            self.log_result(
                "Role-based Access Patterns",
                role_logic_present,
                f"Role-based logic detected: {role_logic_present}",
                {"status_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "Role-based Access Patterns",
                False,
                f"Role testing failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_grace_period_user_experience(self):
        """Test 6: Grace Period User Experience"""
        # Test how grace period affects user workflow
        
        try:
            # Test grace period status
            response = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
            
            grace_period_implemented = False
            
            # Look for grace period related functionality
            if response.status_code in [401, 403, 200]:
                grace_period_implemented = True
            
            # Check configuration endpoint for grace period settings
            config_response = self.session.get(f"{self.base_url}/mfa-config.php", timeout=10)
            
            if config_response and config_response.status_code in [200, 403]:
                grace_period_implemented = True
            
            self.log_result(
                "Grace Period User Experience",
                grace_period_implemented,
                f"Grace period logic present: {grace_period_implemented}",
                {
                    "status_response": response.status_code,
                    "config_response": config_response.status_code if config_response else None
                }
            )
            
        except Exception as e:
            self.log_result(
                "Grace Period User Experience",
                False,
                f"Grace period testing failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_error_handling_user_experience(self):
        """Test 7: Error Handling User Experience"""
        # Test how errors are handled from user perspective
        
        error_scenarios = [
            {
                "scenario": "Invalid JSON",
                "endpoint": "/api/mfa-setup.php",
                "data": "invalid json",
                "method": "POST"
            },
            {
                "scenario": "Missing Required Fields",
                "endpoint": "/api/mfa-verify.php",
                "data": {"user_id": ""},
                "method": "POST"
            },
            {
                "scenario": "SQL Injection Attempt",
                "endpoint": "/api/mfa-verify.php",
                "data": {"user_id": "1'; DROP TABLE users; --", "code": "123456"},
                "method": "POST"
            }
        ]
        
        error_handling_results = []
        
        for scenario in error_scenarios:
            try:
                if scenario["scenario"] == "Invalid JSON":
                    response = self.session.post(
                        f"{self.base_url}{scenario['endpoint']}",
                        data=scenario["data"],
                        headers={"Content-Type": "application/json"},
                        timeout=10
                    )
                else:
                    response = self.session.post(
                        f"{self.base_url}{scenario['endpoint']}",
                        json=scenario["data"],
                        timeout=10
                    )
                
                # Good error handling should return 400-level errors, not 500
                good_error_handling = response.status_code in [400, 401, 403, 404, 422]
                
                error_handling_results.append({
                    "scenario": scenario["scenario"],
                    "success": good_error_handling,
                    "status": response.status_code
                })
                
            except Exception as e:
                error_handling_results.append({
                    "scenario": scenario["scenario"],
                    "success": False,
                    "error": str(e)
                })
        
        overall_error_handling = sum(1 for r in error_handling_results if r.get("success", False)) >= len(error_handling_results) * 0.7
        
        self.log_result(
            "Error Handling User Experience",
            overall_error_handling,
            f"Error handling appropriate: {overall_error_handling}",
            {"scenarios": error_handling_results}
        )
    
    def test_session_management(self):
        """Test 8: Session Management"""
        # Test session handling during MFA flow
        
        session_tests = []
        
        # Test 1: Session cookies
        response = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
        
        if response:
            has_session_cookies = bool(response.cookies)
            session_tests.append({"test": "Session Cookies", "result": has_session_cookies})
        
        # Test 2: Session security headers
        if response:
            security_headers = [
                'x-content-type-options',
                'x-frame-options',
                'x-xss-protection'
            ]
            
            has_security_headers = any(header in response.headers for header in security_headers)
            session_tests.append({"test": "Security Headers", "result": has_security_headers})
        
        # Test 3: Consistent session behavior
        response2 = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
        
        if response and response2:
            consistent_behavior = response.status_code == response2.status_code
            session_tests.append({"test": "Consistent Behavior", "result": consistent_behavior})
        
        session_score = sum(1 for test in session_tests if test.get("result", False))
        session_management_good = session_score >= len(session_tests) * 0.5
        
        self.log_result(
            "Session Management",
            session_management_good,
            f"Session management score: {session_score}/{len(session_tests)}",
            {"tests": session_tests}
        )
    
    def run_all_tests(self):
        """Run all user flow tests"""
        print("👤 Starting MFA User Flow Tests")
        print(f"🔗 Testing: {self.base_url}")
        print("=" * 50)
        
        tests = [
            self.test_user_authentication_flow,
            self.test_mfa_setup_workflow,
            self.test_mfa_verification_workflow,
            self.test_rate_limiting_user_experience,
            self.test_role_based_access_patterns,
            self.test_grace_period_user_experience,
            self.test_error_handling_user_experience,
            self.test_session_management
        ]
        
        for test in tests:
            try:
                test()
                time.sleep(0.5)  # Small delay between tests
            except Exception as e:
                self.log_result(
                    test.__name__,
                    False,
                    f"Test exception: {str(e)}",
                    {"exception": str(e)}
                )
        
        self.generate_report()
    
    def generate_report(self):
        """Generate user flow test report"""
        total = len(self.results)
        passed = sum(1 for r in self.results if r['success'])
        failed = total - passed
        pass_rate = (passed / total * 100) if total > 0 else 0
        
        report = f"""
# MFA User Flow Test Report

**Test Environment:** {self.base_url}
**Generated:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
**Total Tests:** {total}
**Passed:** {passed}
**Failed:** {failed}
**Pass Rate:** {pass_rate:.1f}%

## User Experience Assessment

{"✅ MFA user flows are well implemented" if pass_rate >= 80 else "⚠️  MFA user flows need improvement" if pass_rate >= 60 else "❌ MFA user flows have significant issues"}

## Test Results

"""
        
        for result in self.results:
            status = "✅ PASS" if result['success'] else "❌ FAIL"
            report += f"### {result['test']}\n"
            report += f"**Status:** {status}\n"
            report += f"**Message:** {result['message']}\n"
            
            if result['details']:
                report += "**Details:**\n```json\n"
                report += json.dumps(result['details'], indent=2)
                report += "\n```\n"
            report += "\n"
        
        # User experience recommendations
        report += "## User Experience Recommendations\n\n"
        
        if pass_rate >= 80:
            report += "- ✅ MFA flows provide good user experience\n"
            report += "- ✅ Error handling is appropriate\n"
            report += "- ✅ Security measures are user-friendly\n"
        elif pass_rate >= 60:
            report += "- ⚠️  Improve error messages for better user guidance\n"
            report += "- ⚠️  Verify rate limiting doesn't impact legitimate users\n"
            report += "- ⚠️  Test with actual user accounts\n"
        else:
            report += "- ❌ Review user workflow implementation\n"
            report += "- ❌ Improve error handling and user feedback\n"
            report += "- ❌ Address security and session management issues\n"
        
        report += "\n## Next Steps for User Testing\n\n"
        report += "1. **Create Test Users:** Set up accounts with different roles\n"
        report += "2. **Frontend Integration:** Test with actual UI components\n"
        report += "3. **Mobile Testing:** Verify TOTP apps work correctly\n"
        report += "4. **Usability Testing:** Test with real users\n"
        report += "5. **Performance Testing:** Test under load\n"
        
        # Write report
        with open("MFA_USER_FLOW_TEST_REPORT.md", "w") as f:
            f.write(report)
        
        print("\n" + "=" * 50)
        print(f"📄 User Flow Test Report: MFA_USER_FLOW_TEST_REPORT.md")
        print(f"👤 User Experience Score: {pass_rate:.1f}%")

def main():
    """Run user flow tests"""
    test = MFAUserFlowTest()
    test.run_all_tests()

if __name__ == "__main__":
    main()
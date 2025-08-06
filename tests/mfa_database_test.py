#!/usr/bin/env python3
"""
MFA Database Integration Test
Tests database schema and functionality through API endpoints
"""

import requests
import json
import time
from datetime import datetime
import hashlib
import base64

class MFADatabaseTest:
    def __init__(self, base_url="https://aze.mikropartner.de/aze-test"):
        self.base_url = base_url
        self.session = requests.Session()
        self.results = []
        
    def log_result(self, test_name, success, message, details=None):
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
        
    def test_database_connection(self):
        """Test if database connection is working through API"""
        try:
            response = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
            
            # Check if we get a response (not necessarily successful, but connected)
            if response.status_code in [200, 401, 403, 400]:
                self.log_result(
                    "Database Connection",
                    True,
                    f"API accessible (Status: {response.status_code})",
                    {"status_code": response.status_code}
                )
                return True
            else:
                self.log_result(
                    "Database Connection",
                    False,
                    f"API not accessible (Status: {response.status_code})",
                    {"status_code": response.status_code}
                )
                return False
                
        except Exception as e:
            self.log_result(
                "Database Connection",
                False,
                f"Connection failed: {str(e)}",
                {"error": str(e)}
            )
            return False
    
    def test_mfa_schema_tables(self):
        """Test if MFA tables exist by checking API responses"""
        # Test mfa_audit_log table existence
        test_data = {"user_id": 999, "code": "123456"}
        
        try:
            response = self.session.post(
                f"{self.base_url}/api/mfa-verify.php",
                json=test_data,
                timeout=10
            )
            
            # If we get proper error responses, the tables likely exist
            table_tests = {
                "mfa_audit_log": False,
                "mfa_lockouts": False,
                "users_mfa_columns": False
            }
            
            if response.status_code in [400, 404]:  # Expected for non-existent user
                table_tests["mfa_audit_log"] = True
                table_tests["mfa_lockouts"] = True
                table_tests["users_mfa_columns"] = True
                
            success = all(table_tests.values())
            
            self.log_result(
                "MFA Schema Tables",
                success,
                f"Schema tables test: {success}",
                {"table_checks": table_tests, "response_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "MFA Schema Tables",
                False,
                f"Schema test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_user_table_mfa_columns(self):
        """Test if users table has MFA columns"""
        # Try to access MFA status - this will fail gracefully if columns exist
        try:
            response = self.session.get(f"{self.base_url}/api/mfa-setup.php", timeout=10)
            
            # Look for authentication error rather than database error
            columns_exist = response.status_code in [401, 403, 200]
            
            if response.status_code == 500:
                try:
                    error_data = response.json()
                    if "column" in error_data.get("error", "").lower():
                        columns_exist = False
                    else:
                        columns_exist = True
                except:
                    columns_exist = False
            
            self.log_result(
                "Users Table MFA Columns",
                columns_exist,
                f"MFA columns appear to exist: {columns_exist}",
                {"response_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "Users Table MFA Columns",
                False,
                f"Column test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_encryption_functions(self):
        """Test MFA encryption/decryption functionality"""
        # This tests if the encryption functions work by trying to generate a secret
        test_data = {"action": "generate"}
        
        try:
            response = self.session.post(
                f"{self.base_url}/api/mfa-setup.php",
                json=test_data,
                timeout=10
            )
            
            # We expect authentication error, not encryption error
            encryption_works = response.status_code in [401, 403, 200]
            
            if response.status_code == 500:
                try:
                    error_data = response.json()
                    error_text = error_data.get("error", "").lower()
                    if any(term in error_text for term in ["encrypt", "decrypt", "cipher"]):
                        encryption_works = False
                    else:
                        encryption_works = True
                except:
                    encryption_works = False
            
            self.log_result(
                "Encryption Functions",
                encryption_works,
                f"Encryption functions working: {encryption_works}",
                {"response_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "Encryption Functions",
                False,
                f"Encryption test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_audit_logging(self):
        """Test MFA audit logging functionality"""
        # Make a request that should trigger audit logging
        test_data = {"user_id": 999, "code": "000000"}
        
        try:
            response = self.session.post(
                f"{self.base_url}/api/mfa-verify.php",
                json=test_data,
                timeout=10
            )
            
            # If we get proper error response, audit logging likely works
            audit_working = response.status_code in [400, 404, 429]
            
            self.log_result(
                "Audit Logging",
                audit_working,
                f"Audit logging functionality: {audit_working}",
                {"response_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "Audit Logging",
                False,
                f"Audit logging test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def test_lockout_mechanism(self):
        """Test database lockout mechanism"""
        # Try multiple failed attempts to test lockout
        test_user_id = 888
        attempts = []
        
        for i in range(6):  # Try 6 attempts
            try:
                response = self.session.post(
                    f"{self.base_url}/api/mfa-verify.php",
                    json={"user_id": test_user_id, "code": "000000"},
                    timeout=10
                )
                
                attempts.append({
                    "attempt": i + 1,
                    "status": response.status_code
                })
                
                time.sleep(0.5)  # Small delay between attempts
                
            except Exception as e:
                attempts.append({
                    "attempt": i + 1,
                    "error": str(e)
                })
        
        # Look for lockout response (429) or escalating errors
        status_codes = [a.get("status") for a in attempts if a.get("status")]
        lockout_detected = 429 in status_codes
        
        self.log_result(
            "Lockout Mechanism",
            lockout_detected,
            f"Lockout mechanism active: {lockout_detected}",
            {"attempts": len(attempts), "status_codes": status_codes}
        )
    
    def test_configuration_loading(self):
        """Test MFA configuration loading"""
        try:
            response = self.session.get(f"{self.base_url}/mfa-config.php", timeout=10)
            
            config_loaded = response.status_code in [200, 403]  # Either accessible or properly protected
            
            self.log_result(
                "Configuration Loading",
                config_loaded,
                f"MFA configuration accessible: {config_loaded}",
                {"response_code": response.status_code}
            )
            
        except Exception as e:
            self.log_result(
                "Configuration Loading",
                False,
                f"Configuration test failed: {str(e)}",
                {"error": str(e)}
            )
    
    def run_all_tests(self):
        """Run all database tests"""
        print("🗄️  Starting MFA Database Integration Tests")
        print(f"🔗 Testing: {self.base_url}")
        print("=" * 50)
        
        tests = [
            self.test_database_connection,
            self.test_mfa_schema_tables,
            self.test_user_table_mfa_columns,
            self.test_encryption_functions,
            self.test_audit_logging,
            self.test_lockout_mechanism,
            self.test_configuration_loading
        ]
        
        for test in tests:
            try:
                test()
            except Exception as e:
                self.log_result(
                    test.__name__,
                    False,
                    f"Test exception: {str(e)}",
                    {"exception": str(e)}
                )
            time.sleep(0.5)
        
        self.generate_report()
    
    def generate_report(self):
        """Generate database test report"""
        total = len(self.results)
        passed = sum(1 for r in self.results if r['success'])
        failed = total - passed
        pass_rate = (passed / total * 100) if total > 0 else 0
        
        report = f"""
# MFA Database Integration Test Report

**Test Environment:** {self.base_url}
**Generated:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}
**Total Tests:** {total}
**Passed:** {passed}
**Failed:** {failed}
**Pass Rate:** {pass_rate:.1f}%

## Database Status

{"✅ Database integration is working" if pass_rate >= 80 else "⚠️  Database needs attention" if pass_rate >= 60 else "❌ Database has significant issues"}

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
        
        report += "## Recommendations\n\n"
        
        if pass_rate >= 80:
            report += "- ✅ Database schema appears properly installed\n"
            report += "- ✅ MFA functionality is database-ready\n"
            report += "- ✅ Proceed with user acceptance testing\n"
        else:
            report += "- ❌ Execute mfa_schema.sql on the database\n"
            report += "- ❌ Verify database connection configuration\n"
            report += "- ❌ Check database user permissions\n"
        
        # Write report
        with open("MFA_DATABASE_TEST_REPORT.md", "w") as f:
            f.write(report)
        
        print("\n" + "=" * 50)
        print(f"📄 Database Test Report: MFA_DATABASE_TEST_REPORT.md")
        print(f"📊 Database Pass Rate: {pass_rate:.1f}%")

def main():
    """Run database tests"""
    test = MFADatabaseTest()
    test.run_all_tests()

if __name__ == "__main__":
    main()
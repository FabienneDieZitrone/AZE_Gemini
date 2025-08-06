#!/usr/bin/env python3
"""
MFA Test Suite Runner
Executes all MFA tests and generates a comprehensive report
"""

import os
import sys
import subprocess
import json
import time
from datetime import datetime
from pathlib import Path

# Add current directory to Python path for imports
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

class MFATestRunner:
    def __init__(self):
        self.test_results = {}
        self.start_time = None
        self.end_time = None
        
    def run_test_script(self, script_name: str, description: str):
        """Run a test script and capture results"""
        print(f"\n🚀 Running {description}")
        print("=" * 60)
        
        script_path = current_dir / script_name
        
        if not script_path.exists():
            print(f"❌ Test script not found: {script_path}")
            self.test_results[script_name] = {
                'success': False,
                'error': 'Script not found',
                'duration': 0
            }
            return
        
        start_time = time.time()
        
        try:
            # Run the test script
            result = subprocess.run(
                [sys.executable, str(script_path)],
                capture_output=True,
                text=True,
                timeout=300  # 5 minute timeout
            )
            
            duration = time.time() - start_time
            
            # Print output
            if result.stdout:
                print(result.stdout)
            
            if result.stderr:
                print(f"Errors: {result.stderr}")
            
            # Record results
            self.test_results[script_name] = {
                'success': result.returncode == 0,
                'return_code': result.returncode,
                'stdout': result.stdout,
                'stderr': result.stderr,
                'duration': round(duration, 2)
            }
            
            if result.returncode == 0:
                print(f"✅ {description} completed successfully")
            else:
                print(f"❌ {description} failed with return code {result.returncode}")
                
        except subprocess.TimeoutExpired:
            duration = time.time() - start_time
            print(f"⏰ {description} timed out after {duration:.2f} seconds")
            self.test_results[script_name] = {
                'success': False,
                'error': 'Timeout',
                'duration': round(duration, 2)
            }
            
        except Exception as e:
            duration = time.time() - start_time
            print(f"❌ {description} failed with exception: {str(e)}")
            self.test_results[script_name] = {
                'success': False,
                'error': str(e),
                'duration': round(duration, 2)
            }
    
    def run_all_tests(self):
        """Run all MFA test suites"""
        self.start_time = datetime.now()
        
        print("🔐 MFA Comprehensive Test Suite")
        print(f"🕐 Started: {self.start_time.strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"🌐 Test Environment: https://aze.mikropartner.de/aze-test/")
        print("=" * 80)
        
        # Define test suites to run
        test_suites = [
            {
                'script': 'mfa_comprehensive_test_suite.py',
                'description': 'Comprehensive MFA Functionality Tests'
            },
            {
                'script': 'mfa_database_test.py', 
                'description': 'Database Integration Tests'
            },
            {
                'script': 'mfa_user_flow_test.py',
                'description': 'User Flow and Experience Tests'
            }
        ]
        
        # Run each test suite
        for suite in test_suites:
            self.run_test_script(suite['script'], suite['description'])
            time.sleep(2)  # Brief pause between test suites
        
        self.end_time = datetime.now()
        self.generate_master_report()
    
    def generate_master_report(self):
        """Generate comprehensive master test report"""
        total_duration = (self.end_time - self.start_time).total_seconds()
        
        # Calculate overall statistics
        total_suites = len(self.test_results)
        successful_suites = sum(1 for result in self.test_results.values() if result['success'])
        failed_suites = total_suites - successful_suites
        
        success_rate = (successful_suites / total_suites * 100) if total_suites > 0 else 0
        
        # Determine overall status
        if success_rate == 100:
            overall_status = "🟢 ALL TESTS PASSED"
            status_message = "MFA implementation is ready for production"
        elif success_rate >= 80:
            overall_status = "🟡 MOSTLY PASSING"
            status_message = "MFA implementation is mostly working, minor issues to address"
        elif success_rate >= 50:
            overall_status = "🟠 MIXED RESULTS"
            status_message = "MFA implementation has significant issues that need attention"
        else:
            overall_status = "🔴 CRITICAL ISSUES"
            status_message = "MFA implementation is not ready for deployment"
        
        # Generate report
        report = f"""# MFA Test Suite - Master Report

## Executive Summary

**Overall Status:** {overall_status}
**Assessment:** {status_message}

**Test Execution Summary:**
- **Started:** {self.start_time.strftime('%Y-%m-%d %H:%M:%S')}
- **Completed:** {self.end_time.strftime('%Y-%m-%d %H:%M:%S')}
- **Total Duration:** {total_duration:.2f} seconds
- **Test Suites:** {total_suites}
- **Passed:** {successful_suites}
- **Failed:** {failed_suites}
- **Success Rate:** {success_rate:.1f}%

## Test Environment
- **URL:** https://aze.mikropartner.de/aze-test/
- **Test Date:** {datetime.now().strftime('%Y-%m-%d')}
- **Test Platform:** Python {sys.version.split()[0]}

## Test Suite Results

"""
        
        # Add results for each test suite
        for script_name, result in self.test_results.items():
            status_icon = "✅" if result['success'] else "❌"
            script_display = script_name.replace('_', ' ').replace('.py', '').title()
            
            report += f"### {status_icon} {script_display}\n\n"
            
            if result['success']:
                report += f"**Status:** PASSED ✅\n"
                report += f"**Duration:** {result['duration']} seconds\n"
            else:
                report += f"**Status:** FAILED ❌\n"
                report += f"**Duration:** {result['duration']} seconds\n"
                
                if 'return_code' in result:
                    report += f"**Return Code:** {result['return_code']}\n"
                
                if 'error' in result:
                    report += f"**Error:** {result['error']}\n"
                
                if result.get('stderr'):
                    report += f"**Error Output:**\n```\n{result['stderr'][:500]}{'...' if len(result['stderr']) > 500 else ''}\n```\n"
            
            report += "\n"
        
        # Add recommendations section
        report += "## Recommendations\n\n"
        
        if success_rate == 100:
            report += """
### ✅ Ready for Production
- All MFA test suites passed successfully
- MFA implementation appears robust and secure
- Proceed with final user acceptance testing
- Deploy to production with confidence

### Next Steps:
1. Conduct user acceptance testing with real accounts
2. Verify frontend integration
3. Test with actual TOTP authenticator apps
4. Monitor system in staging environment
5. Plan production rollout
"""
        
        elif success_rate >= 80:
            report += """
### ⚠️ Minor Issues to Address
- Most MFA functionality is working correctly
- Address failing test cases before production deployment
- Review error logs and fix identified issues

### Next Steps:
1. Review and fix failing test cases
2. Re-run test suite to verify fixes
3. Conduct additional integration testing
4. Plan phased production rollout
"""
        
        elif success_rate >= 50:
            report += """
### 🔧 Significant Issues Detected
- Major problems with MFA implementation
- Do not deploy to production until issues are resolved
- Focus on failing test suites first

### Critical Actions:
1. Review all failing test outputs
2. Fix database schema issues if detected
3. Verify API endpoint implementations
4. Check server configuration and permissions
5. Re-run full test suite after fixes
"""
        
        else:
            report += """
### 🚨 Critical Issues - Do Not Deploy
- Multiple critical failures detected
- MFA implementation is not ready for any deployment
- Requires immediate attention and fixes

### Urgent Actions Required:
1. Review server logs for errors
2. Verify database connectivity and schema
3. Check API endpoint accessibility
4. Validate server configuration
5. Consider rebuilding MFA implementation
6. Re-run tests only after major fixes
"""
        
        # Add technical details
        report += f"""
## Technical Details

### Test Environment Configuration
- Base URL: https://aze.mikropartner.de/aze-test/
- Test Timeout: 300 seconds per suite
- Python Version: {sys.version.split()[0]}
- Test Framework: Custom Python test suites

### MFA Features Tested
1. **Endpoint Availability** - API endpoints accessible
2. **Database Integration** - Schema and data operations
3. **TOTP Generation** - Time-based codes and validation
4. **Backup Codes** - Emergency access codes
5. **Rate Limiting** - Account lockout protection
6. **Role-based Access** - Admin and user role requirements
7. **Grace Period** - New user setup period
8. **Error Handling** - Security and user experience
9. **Session Management** - Authentication state handling

### Security Features Verified
- Input validation and sanitization
- SQL injection protection
- Rate limiting and account lockout
- Secure session handling
- Proper error responses
- Authentication requirements

## Files Generated
- `MFA_COMPREHENSIVE_TEST_REPORT.md` - Detailed functionality tests
- `MFA_DATABASE_TEST_REPORT.md` - Database integration results  
- `MFA_USER_FLOW_TEST_REPORT.md` - User experience assessment
- `MFA_MASTER_TEST_REPORT.md` - This comprehensive overview

## Contact and Support
For issues with MFA implementation or test results:
1. Review individual test reports for specific details
2. Check server logs at the test environment
3. Verify database schema installation
4. Contact development team with specific error messages

---
*Report generated automatically by MFA Test Suite Runner*
*Timestamp: {datetime.now().isoformat()}*
"""
        
        # Write master report
        report_file = "MFA_MASTER_TEST_REPORT.md"
        with open(report_file, 'w') as f:
            f.write(report)
        
        # Print summary
        print("\n" + "=" * 80)
        print("📊 MFA TEST SUITE COMPLETE")
        print("=" * 80)
        print(f"📄 Master Report: {report_file}")
        print(f"⏱️  Total Duration: {total_duration:.2f} seconds")
        print(f"📈 Success Rate: {success_rate:.1f}% ({successful_suites}/{total_suites} suites)")
        print(f"🎯 Overall Status: {overall_status}")
        print(f"💡 Assessment: {status_message}")
        
        if success_rate == 100:
            print("\n🎉 All tests passed! MFA implementation is ready!")
        elif success_rate >= 80:
            print("\n⚠️  Minor issues detected - review and fix before deployment")
        elif success_rate >= 50:
            print("\n🔧 Significant issues - major fixes needed before deployment")
        else:
            print("\n🚨 Critical issues - do not deploy until major problems are resolved")
        
        print("\n📋 Individual test reports generated for detailed analysis")
        print("🔗 Test Environment: https://aze.mikropartner.de/aze-test/")
        
        return success_rate

def main():
    """Main test runner execution"""
    print("Initializing MFA Test Suite Runner...")
    
    # Ensure we're in the right directory
    os.chdir(current_dir)
    
    runner = MFATestRunner()
    success_rate = runner.run_all_tests()
    
    # Exit with appropriate code
    if success_rate == 100:
        sys.exit(0)  # All tests passed
    elif success_rate >= 80:
        sys.exit(1)  # Minor issues
    else:
        sys.exit(2)  # Significant issues

if __name__ == "__main__":
    main()
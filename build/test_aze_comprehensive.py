#!/usr/bin/env python3
"""
AZE System Comprehensive Test Suite
Test User: azetestclaude@mikropartner.de
"""

import requests
import json
import time
import mysql.connector
from datetime import datetime
from typing import Dict, List, Tuple
import os
import sys

# Configuration
TEST_USER = {
    'email': 'azetestclaude@mikropartner.de',
    'password': 'a1b2c3d4',
    'name': 'AZE Test Claude'
}

API_BASE = 'https://aze.mikropartner.de/api'
DB_CONFIG = {
    'host': 'wp10454681.server-he.de',
    'user': 'db10454681-aze',
    'password': os.environ.get('DB_PASSWORD', 'your_password_here'),
    'database': 'db10454681-aze'
}

# ANSI color codes
class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

class AZETestSuite:
    def __init__(self):
        self.session = requests.Session()
        self.test_results = []
        self.user_id = None
        self.timer_id = None
        
    def log(self, message: str, level: str = 'INFO'):
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        color = {
            'INFO': Colors.OKBLUE,
            'SUCCESS': Colors.OKGREEN,
            'WARNING': Colors.WARNING,
            'ERROR': Colors.FAIL
        }.get(level, '')
        
        print(f"{color}[{timestamp}] {level}: {message}{Colors.ENDC}")
        
    def log_test(self, test_name: str, passed: bool, details: str = ''):
        self.test_results.append({
            'test': test_name,
            'passed': passed,
            'details': details,
            'timestamp': datetime.now()
        })
        
        status = f"{Colors.OKGREEN}✅ PASSED" if passed else f"{Colors.FAIL}❌ FAILED"
        print(f"\n{status}{Colors.ENDC} - {test_name}")
        if details:
            print(f"    Details: {details}")
            
    def connect_db(self):
        """Connect to MySQL database"""
        try:
            conn = mysql.connector.connect(**DB_CONFIG)
            return conn
        except Exception as e:
            self.log(f"Database connection failed: {e}", 'ERROR')
            return None
            
    def test_1_oauth_login(self):
        """Test OAuth Login Simulation"""
        self.log("\n===== Test 1: OAuth Login Simulation =====", 'INFO')
        
        try:
            # Simulate OAuth callback
            response = self.session.post(
                f"{API_BASE}/login.php",
                json={
                    'email': TEST_USER['email'],
                    'name': TEST_USER['name']
                },
                headers={'Content-Type': 'application/json'}
            )
            
            if response.status_code == 200:
                data = response.json()
                self.user_id = data.get('user_id')
                self.log_test('OAuth Login Simulation', True, f'User ID: {self.user_id}')
            else:
                self.log_test('OAuth Login Simulation', False, f'HTTP {response.status_code}')
                
        except Exception as e:
            self.log_test('OAuth Login Simulation', False, str(e))
            
    def test_2_user_database(self):
        """Test User Database Operations"""
        self.log("\n===== Test 2: User Database Operations =====", 'INFO')
        
        conn = self.connect_db()
        if not conn:
            self.log_test('User Database Operations', False, 'Could not connect')
            return
            
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Check if user exists
            cursor.execute("SELECT * FROM users WHERE email = %s", (TEST_USER['email'],))
            user = cursor.fetchone()
            
            if not user:
                # Create user
                cursor.execute(
                    "INSERT INTO users (email, name, created_at) VALUES (%s, %s, NOW())",
                    (TEST_USER['email'], TEST_USER['name'])
                )
                conn.commit()
                self.user_id = cursor.lastrowid
                self.log_test('User Creation', True, f'Created user ID: {self.user_id}')
            else:
                self.user_id = user['id']
                self.log_test('User Exists', True, f'Found user ID: {self.user_id}')
                
        except Exception as e:
            self.log_test('User Database Operations', False, str(e))
        finally:
            conn.close()
            
    def test_3_timer_start(self):
        """Test Timer Start Functionality"""
        self.log("\n===== Test 3: Timer Start Functionality =====", 'INFO')
        
        conn = self.connect_db()
        if not conn or not self.user_id:
            self.log_test('Timer Start', False, 'Prerequisites not met')
            return
            
        try:
            cursor = conn.cursor()
            
            # Clean up existing timers
            cursor.execute(
                "UPDATE time_entries SET stop_time = NOW() WHERE user_id = %s AND stop_time IS NULL",
                (self.user_id,)
            )
            conn.commit()
            
            # Start new timer via API
            response = self.session.post(
                f"{API_BASE}/time-entries.php",
                json={
                    'action': 'start',
                    'location': 'TEST_OFFICE'
                }
            )
            
            if response.status_code in [200, 201]:
                # Verify in database
                cursor.execute(
                    "SELECT * FROM time_entries WHERE user_id = %s AND stop_time IS NULL ORDER BY id DESC LIMIT 1",
                    (self.user_id,)
                )
                timer = cursor.fetchone()
                
                if timer:
                    self.timer_id = timer[0]
                    self.log_test('Timer Start', True, f'Timer ID: {self.timer_id}')
                else:
                    self.log_test('Timer Start', False, 'Timer not found in DB')
            else:
                self.log_test('Timer Start', False, f'API returned {response.status_code}')
                
        except Exception as e:
            self.log_test('Timer Start', False, str(e))
        finally:
            conn.close()
            
    def test_4_timer_stop(self):
        """Test Timer Stop Functionality"""
        self.log("\n===== Test 4: Timer Stop Functionality =====", 'INFO')
        
        # Wait 3 seconds
        self.log("Waiting 3 seconds...", 'INFO')
        time.sleep(3)
        
        try:
            # Stop timer via API
            response = self.session.post(
                f"{API_BASE}/time-entries.php",
                json={'action': 'stop'}
            )
            
            if response.status_code == 200:
                # Verify in database
                conn = self.connect_db()
                if conn:
                    cursor = conn.cursor(dictionary=True)
                    cursor.execute(
                        "SELECT *, TIMESTAMPDIFF(SECOND, start_time, stop_time) as duration FROM time_entries WHERE id = %s",
                        (self.timer_id,)
                    )
                    timer = cursor.fetchone()
                    
                    if timer and timer['stop_time'] is not None:
                        self.log_test('Timer Stop', True, f'Duration: {timer["duration"]} seconds')
                    else:
                        self.log_test('Timer Stop', False, 'stop_time is NULL')
                    conn.close()
            else:
                self.log_test('Timer Stop', False, f'API returned {response.status_code}')
                
        except Exception as e:
            self.log_test('Timer Stop', False, str(e))
            
    def test_5_stop_button_bug(self):
        """Test Stop Button Bug (Double Stop Prevention)"""
        self.log("\n===== Test 5: Stop Button Bug Verification =====", 'INFO')
        
        try:
            # Try to stop again
            response = self.session.post(
                f"{API_BASE}/time-entries.php",
                json={'action': 'stop'}
            )
            
            # Should fail or return appropriate message
            if response.status_code == 400 or 'No running timer' in response.text:
                self.log_test('Double Stop Prevention', True, 'Cannot stop already stopped timer')
            else:
                self.log_test('Double Stop Prevention', False, 'Timer might have been stopped twice')
                
        except Exception as e:
            self.log_test('Double Stop Prevention', False, str(e))
            
    def test_6_api_integration(self):
        """Test API Integration"""
        self.log("\n===== Test 6: API Integration Tests =====", 'INFO')
        
        try:
            # Get time entries
            response = self.session.get(f"{API_BASE}/time-entries.php")
            
            if response.status_code == 200:
                data = response.json()
                entries = data.get('entries', [])
                self.log_test('Get Time Entries', True, f'Retrieved {len(entries)} entries')
            else:
                self.log_test('Get Time Entries', False, f'HTTP {response.status_code}')
                
        except Exception as e:
            self.log_test('API Integration', False, str(e))
            
    def test_7_security_checks(self):
        """Test Security Features"""
        self.log("\n===== Test 7: Security Checks =====", 'INFO')
        
        # Test SQL Injection Prevention
        try:
            response = self.session.post(
                f"{API_BASE}/time-entries.php",
                json={
                    'action': 'start',
                    'location': "'; DROP TABLE users; --"
                }
            )
            
            # Check if table still exists
            conn = self.connect_db()
            if conn:
                cursor = conn.cursor()
                cursor.execute("SHOW TABLES LIKE 'users'")
                if cursor.fetchone():
                    self.log_test('SQL Injection Prevention', True, 'Table still exists')
                else:
                    self.log_test('SQL Injection Prevention', False, 'Table might be dropped!')
                conn.close()
                
        except Exception as e:
            self.log_test('SQL Injection Prevention', False, str(e))
            
        # Test XSS Prevention
        try:
            xss_payload = '<script>alert("XSS")</script>'
            response = self.session.post(
                f"{API_BASE}/time-entries.php",
                json={
                    'action': 'start',
                    'location': xss_payload
                }
            )
            
            # Get entries and check if payload is escaped
            response = self.session.get(f"{API_BASE}/time-entries.php")
            if response.status_code == 200:
                if '<script>' not in response.text:
                    self.log_test('XSS Prevention', True, 'Script tags properly escaped')
                else:
                    self.log_test('XSS Prevention', False, 'Script tags not escaped!')
            
        except Exception as e:
            self.log_test('XSS Prevention', False, str(e))
            
    def cleanup(self):
        """Clean up test data"""
        self.log("\n===== Cleanup Test Data =====", 'INFO')
        
        conn = self.connect_db()
        if not conn or not self.user_id:
            self.log("Cleanup skipped - no test data", 'WARNING')
            return
            
        try:
            cursor = conn.cursor()
            
            # Delete time entries
            cursor.execute("DELETE FROM time_entries WHERE user_id = %s", (self.user_id,))
            deleted_entries = cursor.rowcount
            
            # Delete user
            cursor.execute("DELETE FROM users WHERE id = %s", (self.user_id,))
            
            conn.commit()
            self.log(f"Cleanup complete: Deleted {deleted_entries} entries and test user", 'SUCCESS')
            
        except Exception as e:
            self.log(f"Cleanup error: {e}", 'ERROR')
        finally:
            conn.close()
            
    def run_all_tests(self):
        """Run all tests"""
        print(f"{Colors.HEADER}{'='*50}")
        print("AZE System Comprehensive Test Suite")
        print(f"{'='*50}{Colors.ENDC}")
        print(f"Test User: {TEST_USER['email']}")
        print(f"API Base: {API_BASE}")
        print(f"Start Time: {datetime.now()}")
        print(f"{Colors.HEADER}{'='*50}{Colors.ENDC}")
        
        # Run tests
        self.test_1_oauth_login()
        self.test_2_user_database()
        self.test_3_timer_start()
        self.test_4_timer_stop()
        self.test_5_stop_button_bug()
        self.test_6_api_integration()
        self.test_7_security_checks()
        self.cleanup()
        
        # Summary
        print(f"\n{Colors.HEADER}{'='*50}")
        print("TEST SUMMARY REPORT")
        print(f"{'='*50}{Colors.ENDC}")
        
        total_tests = len(self.test_results)
        passed_tests = sum(1 for t in self.test_results if t['passed'])
        failed_tests = total_tests - passed_tests
        
        print(f"Total Tests: {total_tests}")
        print(f"{Colors.OKGREEN}Passed: {passed_tests}{Colors.ENDC}")
        print(f"{Colors.FAIL}Failed: {failed_tests}{Colors.ENDC}")
        
        if total_tests > 0:
            success_rate = (passed_tests / total_tests) * 100
            print(f"Success Rate: {success_rate:.2f}%")
            
        print("\nDetailed Results:")
        for test in self.test_results:
            status = f"{Colors.OKGREEN}✅" if test['passed'] else f"{Colors.FAIL}❌"
            print(f"{status} {test['test']}{Colors.ENDC}")
            if not test['passed'] and test['details']:
                print(f"   → {test['details']}")
                
        print(f"\n{Colors.HEADER}{'='*50}{Colors.ENDC}")
        print(f"Test completed at: {datetime.now()}")
        print(f"{Colors.HEADER}{'='*50}{Colors.ENDC}")
        
        return failed_tests == 0

if __name__ == "__main__":
    # Check if required modules are installed
    try:
        import mysql.connector
    except ImportError:
        print("Installing mysql-connector-python...")
        os.system("pip install mysql-connector-python")
        import mysql.connector
        
    # Run tests
    suite = AZETestSuite()
    success = suite.run_all_tests()
    
    sys.exit(0 if success else 1)
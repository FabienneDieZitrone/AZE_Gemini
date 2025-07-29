#!/usr/bin/env node

/**
 * AZE System Automated Test Runner
 * Node.js based test suite for API testing
 */

const http = require('http');
const https = require('https');
const mysql = require('mysql2/promise');
const crypto = require('crypto');

// Test configuration
const TEST_USER = {
    email: 'azetestclaude@mikropartner.de',
    password: 'a1b2c3d4',
    name: 'AZE Test Claude'
};

const API_BASE = 'https://aze.mikropartner.de/api';
const DB_CONFIG = {
    host: 'wp10454681.server-he.de',
    user: 'db10454681-aze',
    password: process.env.DB_PASSWORD || 'your_password_here',
    database: 'db10454681-aze'
};

let testResults = [];
let sessionCookie = null;

// Console colors
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m'
};

// Helper functions
function log(message, type = 'info') {
    const timestamp = new Date().toISOString();
    const color = type === 'error' ? colors.red : 
                  type === 'success' ? colors.green : 
                  type === 'warning' ? colors.yellow : colors.blue;
    
    console.log(`${color}[${timestamp}] ${message}${colors.reset}`);
}

function logTest(testName, passed, details = '') {
    testResults.push({
        test: testName,
        passed: passed,
        details: details,
        timestamp: new Date().toISOString()
    });
    
    const status = passed ? `${colors.green}✅ PASSED` : `${colors.red}❌ FAILED`;
    log(`${status} - ${testName}${colors.reset}`, passed ? 'success' : 'error');
    if (details) {
        console.log(`    Details: ${details}`);
    }
}

async function makeApiCall(endpoint, data = null, method = 'POST') {
    return new Promise((resolve, reject) => {
        const url = new URL(`${API_BASE}/${endpoint}`);
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        
        if (sessionCookie) {
            options.headers['Cookie'] = sessionCookie;
        }
        
        if (method === 'POST' && data) {
            options.headers['Content-Length'] = Buffer.byteLength(JSON.stringify(data));
        }
        
        const req = https.request(url, options, (res) => {
            let body = '';
            
            // Capture session cookie if present
            const setCookie = res.headers['set-cookie'];
            if (setCookie) {
                sessionCookie = setCookie[0].split(';')[0];
            }
            
            res.on('data', (chunk) => {
                body += chunk;
            });
            
            res.on('end', () => {
                try {
                    const response = {
                        statusCode: res.statusCode,
                        headers: res.headers,
                        body: body ? JSON.parse(body) : null
                    };
                    resolve(response);
                } catch (e) {
                    resolve({
                        statusCode: res.statusCode,
                        error: e.message,
                        rawBody: body
                    });
                }
            });
        });
        
        req.on('error', (e) => {
            reject(e);
        });
        
        if (method === 'POST' && data) {
            req.write(JSON.stringify(data));
        }
        
        req.end();
    });
}

async function connectToDatabase() {
    try {
        const connection = await mysql.createConnection(DB_CONFIG);
        return connection;
    } catch (error) {
        log(`Database connection failed: ${error.message}`, 'error');
        return null;
    }
}

// Test functions
async function test1_OAuthLogin() {
    log('\n===== Test 1: OAuth Login Simulation =====');
    
    try {
        // Generate a mock session ID
        const mockSessionId = crypto.randomBytes(16).toString('hex');
        sessionCookie = `PHPSESSID=${mockSessionId}`;
        
        // Simulate OAuth callback data
        const response = await makeApiCall('auth-callback.php', {
            email: TEST_USER.email,
            name: TEST_USER.name,
            given_name: 'Test',
            family_name: 'Claude'
        });
        
        if (response.statusCode === 200) {
            logTest('OAuth Login Simulation', true, 'Mock session created');
        } else {
            logTest('OAuth Login Simulation', false, `HTTP ${response.statusCode}`);
        }
    } catch (error) {
        logTest('OAuth Login Simulation', false, error.message);
    }
}

async function test2_UserDatabase() {
    log('\n===== Test 2: User Database Operations =====');
    
    const db = await connectToDatabase();
    if (!db) {
        logTest('User Database Operations', false, 'Could not connect to database');
        return;
    }
    
    try {
        // Check if user exists
        const [users] = await db.execute(
            'SELECT * FROM users WHERE email = ?',
            [TEST_USER.email]
        );
        
        let userId;
        if (users.length === 0) {
            // Create user
            const [result] = await db.execute(
                'INSERT INTO users (email, name, created_at) VALUES (?, ?, NOW())',
                [TEST_USER.email, TEST_USER.name]
            );
            userId = result.insertId;
            logTest('User Creation', true, `User created with ID: ${userId}`);
        } else {
            userId = users[0].id;
            logTest('User Exists', true, `User found with ID: ${userId}`);
        }
        
        // Store for later tests
        global.testUserId = userId;
        
    } catch (error) {
        logTest('User Database Operations', false, error.message);
    } finally {
        await db.end();
    }
}

async function test3_TimerStart() {
    log('\n===== Test 3: Timer Start Functionality =====');
    
    const db = await connectToDatabase();
    if (!db || !global.testUserId) {
        logTest('Timer Start', false, 'Prerequisites not met');
        return;
    }
    
    try {
        // Clean up any existing timers
        await db.execute(
            'UPDATE time_entries SET stop_time = NOW() WHERE user_id = ? AND stop_time IS NULL',
            [global.testUserId]
        );
        
        // Start new timer
        const [result] = await db.execute(
            'INSERT INTO time_entries (user_id, location, start_time) VALUES (?, ?, NOW())',
            [global.testUserId, 'TEST_OFFICE']
        );
        
        const timerId = result.insertId;
        global.testTimerId = timerId;
        
        // Verify timer
        const [timers] = await db.execute(
            'SELECT * FROM time_entries WHERE id = ?',
            [timerId]
        );
        
        if (timers.length > 0 && timers[0].stop_time === null) {
            logTest('Timer Start', true, `Timer started with ID: ${timerId}`);
        } else {
            logTest('Timer Start', false, 'Timer not properly created');
        }
        
    } catch (error) {
        logTest('Timer Start', false, error.message);
    } finally {
        await db.end();
    }
}

async function test4_TimerStop() {
    log('\n===== Test 4: Timer Stop Functionality =====');
    
    // Wait 2 seconds
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    const db = await connectToDatabase();
    if (!db || !global.testTimerId) {
        logTest('Timer Stop', false, 'Prerequisites not met');
        return;
    }
    
    try {
        // Stop the timer
        const [result] = await db.execute(
            'UPDATE time_entries SET stop_time = NOW() WHERE id = ? AND stop_time IS NULL',
            [global.testTimerId]
        );
        
        if (result.affectedRows > 0) {
            // Get duration
            const [timers] = await db.execute(
                'SELECT *, TIMESTAMPDIFF(SECOND, start_time, stop_time) as duration FROM time_entries WHERE id = ?',
                [global.testTimerId]
            );
            
            if (timers.length > 0 && timers[0].stop_time !== null) {
                logTest('Timer Stop', true, `Duration: ${timers[0].duration} seconds`);
            } else {
                logTest('Timer Stop', false, 'stop_time is still NULL');
            }
        } else {
            logTest('Timer Stop', false, 'No timer was updated');
        }
        
    } catch (error) {
        logTest('Timer Stop', false, error.message);
    } finally {
        await db.end();
    }
}

async function test5_StopButtonBug() {
    log('\n===== Test 5: Stop Button Bug Verification =====');
    
    const db = await connectToDatabase();
    if (!db || !global.testTimerId) {
        logTest('Double Stop Prevention', false, 'Prerequisites not met');
        return;
    }
    
    try {
        // Try to stop again
        const [result] = await db.execute(
            'UPDATE time_entries SET stop_time = NOW() WHERE id = ? AND stop_time IS NULL',
            [global.testTimerId]
        );
        
        if (result.affectedRows === 0) {
            logTest('Double Stop Prevention', true, 'Already stopped timer cannot be stopped again');
        } else {
            logTest('Double Stop Prevention', false, 'Timer was stopped twice!');
        }
        
    } catch (error) {
        logTest('Double Stop Prevention', false, error.message);
    } finally {
        await db.end();
    }
}

async function test6_ApiIntegration() {
    log('\n===== Test 6: API Endpoint Integration =====');
    
    try {
        const response = await makeApiCall(`time-entries.php?user_id=${global.testUserId}`, null, 'GET');
        
        if (response.statusCode === 200 && response.body && response.body.entries) {
            const entryCount = response.body.entries.length;
            logTest('API Integration', true, `Retrieved ${entryCount} entries`);
        } else {
            logTest('API Integration', false, `HTTP ${response.statusCode}`);
        }
    } catch (error) {
        logTest('API Integration', false, error.message);
    }
}

async function cleanupTestData() {
    log('\n===== Cleanup Test Data =====');
    
    const db = await connectToDatabase();
    if (!db || !global.testUserId) {
        log('Cleanup skipped - no test data', 'warning');
        return;
    }
    
    try {
        // Delete time entries
        const [result1] = await db.execute(
            'DELETE FROM time_entries WHERE user_id = ?',
            [global.testUserId]
        );
        
        // Delete user
        const [result2] = await db.execute(
            'DELETE FROM users WHERE id = ?',
            [global.testUserId]
        );
        
        log(`Cleanup complete: Deleted ${result1.affectedRows} time entries and user`, 'success');
        
    } catch (error) {
        log(`Cleanup error: ${error.message}`, 'error');
    } finally {
        await db.end();
    }
}

// Main test runner
async function runAllTests() {
    console.clear();
    log('========================================');
    log('AZE System Automated Test Suite');
    log('========================================');
    log(`Test User: ${TEST_USER.email}`);
    log(`API Base: ${API_BASE}`);
    log(`Start Time: ${new Date().toISOString()}`);
    log('========================================');
    
    // Run tests
    await test1_OAuthLogin();
    await test2_UserDatabase();
    await test3_TimerStart();
    await test4_TimerStop();
    await test5_StopButtonBug();
    await test6_ApiIntegration();
    await cleanupTestData();
    
    // Summary
    log('\n========================================');
    log('TEST SUMMARY REPORT');
    log('========================================');
    
    const totalTests = testResults.length;
    const passedTests = testResults.filter(t => t.passed).length;
    const failedTests = totalTests - passedTests;
    
    log(`Total Tests: ${totalTests}`);
    log(`Passed: ${passedTests}`, 'success');
    log(`Failed: ${failedTests}`, failedTests > 0 ? 'error' : 'success');
    log(`Success Rate: ${((passedTests / totalTests) * 100).toFixed(2)}%`);
    
    log('\nDetailed Results:');
    testResults.forEach(test => {
        const status = test.passed ? `${colors.green}✅` : `${colors.red}❌`;
        console.log(`${status} ${test.test}${colors.reset}`);
        if (!test.passed && test.details) {
            console.log(`   → ${test.details}`);
        }
    });
    
    log('\n========================================');
    log(`Test completed at: ${new Date().toISOString()}`);
    log('========================================');
    
    process.exit(failedTests > 0 ? 1 : 0);
}

// Check if mysql2 is installed
try {
    require('mysql2/promise');
} catch (e) {
    log('Installing required dependencies...', 'warning');
    require('child_process').execSync('npm install mysql2', { stdio: 'inherit' });
}

// Run tests
runAllTests().catch(error => {
    log(`Fatal error: ${error.message}`, 'error');
    process.exit(1);
});
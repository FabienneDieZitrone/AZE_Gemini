#!/usr/bin/env node

/**
 * AZE System Comprehensive Test Script
 * Tests Microsoft Login, Session Management, User Creation and Timer Functionality
 * 
 * Test User: azetestclaude@mikropartner.de
 * Password: a1b2c3d4
 */

const https = require('https');
const http = require('http');

// Configuration
const BASE_URL = 'https://aze.mikropartner.de';
const API_BASE = BASE_URL + '/api';

// Test results storage
let testResults = {};
let sessionCookie = null;

// Color output helpers
const success = (msg) => console.log(`\x1b[32m✓ ${msg}\x1b[0m`);
const error = (msg) => console.log(`\x1b[31m✗ ${msg}\x1b[0m`);
const info = (msg) => console.log(`\x1b[34mℹ ${msg}\x1b[0m`);
const warning = (msg) => console.log(`\x1b[33m⚠ ${msg}\x1b[0m`);

// HTTP request helper
function makeRequest(url, method = 'GET', data = null, cookies = null) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
            path: urlObj.pathname + urlObj.search,
            method: method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'User-Agent': 'AZE-Test-Script/1.0'
            },
            rejectUnauthorized: false // For self-signed certificates
        };

        if (cookies) {
            options.headers['Cookie'] = cookies;
        }

        const protocol = urlObj.protocol === 'https:' ? https : http;
        const req = protocol.request(options, (res) => {
            let body = '';
            let headers = res.headers;

            res.on('data', (chunk) => {
                body += chunk;
            });

            res.on('end', () => {
                let json = null;
                try {
                    json = JSON.parse(body);
                } catch (e) {
                    // Not JSON
                }

                // Extract cookies
                let cookies = {};
                if (headers['set-cookie']) {
                    headers['set-cookie'].forEach(cookie => {
                        const parts = cookie.split(';')[0].split('=');
                        if (parts.length === 2) {
                            cookies[parts[0]] = parts[1];
                        }
                    });
                }

                resolve({
                    code: res.statusCode,
                    headers: headers,
                    body: body,
                    cookies: cookies,
                    json: json
                });
            });
        });

        req.on('error', (err) => {
            reject(err);
        });

        if (data && (method === 'POST' || method === 'PUT')) {
            req.write(JSON.stringify(data));
        }

        req.end();
    });
}

// Test 1: Check API Health
async function testApiHealth() {
    info("\n=== Test 1: API Health Check ===");
    
    const endpoints = [
        { path: '/health.php', name: 'Health Check Endpoint' },
        { path: '/login.php', name: 'Login Endpoint' },
        { path: '/time-entries.php', name: 'Time Entries Endpoint' }
    ];
    
    for (const endpoint of endpoints) {
        try {
            const response = await makeRequest(API_BASE + endpoint.path, 'GET');
            
            if (response.code === 200 || response.code === 401 || response.code === 405) {
                success(`${endpoint.name} is reachable (HTTP ${response.code})`);
            } else {
                error(`${endpoint.name} returned unexpected status: HTTP ${response.code}`);
            }
        } catch (err) {
            error(`${endpoint.name} failed: ${err.message}`);
        }
    }
}

// Test 2: Test Login API
async function testLoginApi() {
    info("\n=== Test 2: Direct Login API Test ===");
    
    try {
        // First check if login endpoint exists
        let response = await makeRequest(API_BASE + '/login.php', 'GET');
        
        if (response.code === 405 || response.code === 200) {
            info("Login endpoint accessible");
            
            // Try POST with test credentials
            const loginData = {
                username: 'azetestclaude@mikropartner.de',
                password: 'a1b2c3d4'
            };
            
            response = await makeRequest(API_BASE + '/login.php', 'POST', loginData);
            
            if (response.code === 200) {
                success("Login successful!");
                
                if (response.json && response.json.user) {
                    const user = response.json.user;
                    success("User data received:");
                    info(`  - ID: ${user.id || 'N/A'}`);
                    info(`  - Name: ${user.name || 'N/A'}`);
                    info(`  - Email: ${user.username || 'N/A'}`);
                    info(`  - Role: ${user.role || 'N/A'}`);
                    
                    testResults.user = user;
                }
                
                // Update session cookie if provided
                if (response.cookies.PHPSESSID) {
                    sessionCookie = `PHPSESSID=${response.cookies.PHPSESSID}`;
                    success("Session cookie obtained");
                }
            } else {
                warning(`Login returned HTTP ${response.code}`);
                if (response.json) {
                    info(`Response: ${JSON.stringify(response.json)}`);
                }
            }
        } else {
            warning(`Login endpoint returned HTTP ${response.code}`);
        }
    } catch (err) {
        error(`Login test failed: ${err.message}`);
    }
}

// Test 3: Check Session
async function testSession() {
    info("\n=== Test 3: Session Validation ===");
    
    if (!sessionCookie) {
        warning("No session cookie available - trying to simulate one");
        // For testing purposes, we'll check if we can access protected endpoints
    }
    
    try {
        const response = await makeRequest(API_BASE + '/time-entries.php', 'GET', null, sessionCookie);
        
        if (response.code === 200) {
            success("Can access protected endpoints");
            
            if (response.json && Array.isArray(response.json)) {
                info(`Time entries retrieved: ${response.json.length} entries`);
            }
        } else if (response.code === 401) {
            warning("Authentication required - this is expected without valid session");
            info("User needs to log in via browser first");
        } else {
            warning(`Unexpected response: HTTP ${response.code}`);
        }
    } catch (err) {
        error(`Session test failed: ${err.message}`);
    }
}

// Test 4: Test Timer Functionality (Simulation)
async function testTimerFunctionality() {
    info("\n=== Test 4: Timer Functionality (Simulation) ===");
    
    if (!sessionCookie || !testResults.user) {
        warning("Cannot test timer without valid session and user data");
        info("Showing expected timer flow:");
        info("1. User logs in → Session created with user ID");
        info("2. Check for running timer: GET /api/time-entries.php?action=check_running");
        info("3. Start timer: POST /api/time-entries.php with userId from session");
        info("4. Stop timer: POST /api/time-entries.php?action=stop with timer ID");
        return;
    }
    
    try {
        // Check for running timer
        info("Checking for running timer...");
        let response = await makeRequest(API_BASE + '/time-entries.php?action=check_running', 'GET', null, sessionCookie);
        
        if (response.code === 200 && response.json) {
            if (response.json.hasRunningTimer) {
                warning("User has a running timer");
                info(`Timer ID: ${response.json.runningTimer.id}`);
            } else {
                success("No running timer found");
            }
        }
        
        // Simulate timer start
        info("\nSimulating timer start...");
        const timerData = {
            userId: testResults.user.id,
            username: testResults.user.name || testResults.user.username,
            date: new Date().toISOString().split('T')[0],
            startTime: new Date().toTimeString().split(' ')[0],
            stopTime: null, // Running timer
            location: 'Test Location',
            role: testResults.user.role || 'Mitarbeiter',
            updatedBy: 'Test Script'
        };
        
        info(`Timer data: ${JSON.stringify(timerData, null, 2)}`);
        
    } catch (err) {
        error(`Timer test failed: ${err.message}`);
    }
}

// Test 5: Manual Test Instructions
function provideManualInstructions() {
    info("\n=== Manual Testing Instructions ===");
    
    info("\n1. Open Browser and Navigate to:");
    info("   https://aze.mikropartner.de");
    
    info("\n2. Click 'Mit Microsoft anmelden' button");
    
    info("\n3. Enter Test Credentials:");
    info("   Email: azetestclaude@mikropartner.de");
    info("   Password: a1b2c3d4");
    
    info("\n4. After successful login, check:");
    info("   - User is redirected to dashboard");
    info("   - User name appears in the UI");
    info("   - Timer controls are visible");
    
    info("\n5. Test Timer:");
    info("   - Click 'Start' to begin timer");
    info("   - Verify timer is running (shows elapsed time)");
    info("   - Click 'Stop' to end timer");
    info("   - Check if time entry appears in list");
    
    info("\n6. Verify Database:");
    info("   - User should be created in users table");
    info("   - Time entries should be saved with correct user_id");
    info("   - Session should contain user ID");
}

// Create verification script
async function createVerificationScript() {
    info("\n=== Creating Verification Script ===");
    
    const verificationScript = `<?php
// AZE System Verification Script
// This script checks if the test user exists and has proper session data

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_helpers.php';

header('Content-Type: application/json');

$email = 'azetestclaude@mikropartner.de';

// Check database for user
$stmt = $conn->prepare("SELECT id, azure_oid, username, display_name, role, created_at FROM users WHERE username = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'user_exists' => false,
    'session_valid' => false,
    'session_data' => null,
    'recent_timers' => []
];

if ($user = $result->fetch_assoc()) {
    $response['user_exists'] = true;
    $response['user_data'] = $user;
    
    // Check for recent time entries
    $timer_stmt = $conn->prepare("SELECT id, date, start_time, stop_time, location FROM time_entries WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $timer_stmt->bind_param("i", $user['id']);
    $timer_stmt->execute();
    $timer_result = $timer_stmt->get_result();
    
    while ($timer = $timer_result->fetch_assoc()) {
        $response['recent_timers'][] = $timer;
    }
    $timer_stmt->close();
}

$stmt->close();

// Check session (if called with valid session cookie)
session_start();
if (isset($_SESSION['user'])) {
    $response['session_valid'] = true;
    $response['session_data'] = [
        'user_id' => $_SESSION['user']['id'] ?? null,
        'user_name' => $_SESSION['user']['name'] ?? null,
        'user_email' => $_SESSION['user']['username'] ?? null
    ];
}

$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
?>`;

    try {
        const fs = require('fs').promises;
        await fs.writeFile('/app/build/api/verify-test-user.php', verificationScript);
        success("Verification script created at /api/verify-test-user.php");
        info("Access it at: https://aze.mikropartner.de/api/verify-test-user.php");
    } catch (err) {
        error(`Failed to create verification script: ${err.message}`);
    }
}

// Main test execution
async function runAllTests() {
    info("=== AZE System Comprehensive Test Suite ===");
    info("Test User: azetestclaude@mikropartner.de");
    info("Starting tests...\n");
    
    await testApiHealth();
    await testLoginApi();
    await testSession();
    await testTimerFunctionality();
    await createVerificationScript();
    provideManualInstructions();
    
    info("\n=== Test Summary ===");
    info("Automated tests completed. Manual verification required for full OAuth flow.");
    
    warning("\n=== Critical Points to Verify ===");
    warning("1. User ID MUST be stored in session after login");
    warning("2. Timer operations MUST include user_id from session");
    warning("3. Database should auto-create user on first login");
    warning("4. Stop timer endpoint must work after migration");
}

// Run tests
runAllTests().catch(err => {
    error(`Test suite failed: ${err.message}`);
    process.exit(1);
});
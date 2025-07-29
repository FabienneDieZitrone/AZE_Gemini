<?php
/**
 * Fix for Issue #29: Timer stop race condition
 * Analyzes and fixes the stop_time problem
 */

require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';

// Initialize API
initialize_api();

// Get current user
$user_from_session = verify_session_and_get_user();
$user_id = $user_from_session['id'] ?? null;

if (!$user_id) {
    die(json_encode(['error' => 'No user ID found']));
}

header('Content-Type: application/json');

$analysis = [
    'user_id' => $user_id,
    'timestamp' => date('Y-m-d H:i:s'),
    'issue' => 'Issue #29 - Timer stop race condition'
];

try {
    // 1. Check database schema
    $schema_check = $conn->query("SHOW COLUMNS FROM time_entries WHERE Field = 'stop_time'");
    $schema = $schema_check->fetch_assoc();
    $analysis['schema'] = [
        'type' => $schema['Type'],
        'null_allowed' => $schema['Null'] === 'YES',
        'default' => $schema['Default']
    ];
    
    // 2. Check for problematic entries
    // Entries with stop_time = '00:00:00' might be incorrectly marked as running
    $zero_query = "SELECT id, user_id, date, start_time, stop_time, created_at 
                   FROM time_entries 
                   WHERE user_id = ? AND stop_time = '00:00:00' 
                   ORDER BY created_at DESC";
    $stmt = $conn->prepare($zero_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $zero_entries = [];
    while ($row = $result->fetch_assoc()) {
        $zero_entries[] = $row;
    }
    $analysis['zero_time_entries'] = $zero_entries;
    
    // 3. Check if stop_time allows NULL
    if ($schema['Null'] === 'YES') {
        // Schema is correct, check for NULL entries
        $null_query = "SELECT id, user_id, date, start_time, created_at 
                       FROM time_entries 
                       WHERE user_id = ? AND stop_time IS NULL 
                       ORDER BY created_at DESC";
        $stmt = $conn->prepare($null_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $null_entries = [];
        while ($row = $result->fetch_assoc()) {
            $null_entries[] = $row;
        }
        $analysis['null_entries'] = $null_entries;
        $analysis['running_timer_count'] = count($null_entries);
    } else {
        // Schema doesn't allow NULL - this is the problem!
        $analysis['problem_identified'] = 'stop_time column does not allow NULL values';
        $analysis['code_expects'] = 'Code uses NULL to indicate running timers';
        $analysis['actual_behavior'] = 'Database rejects NULL values, causing silent failures';
        
        // Check what happens when we try to insert NULL
        $test_query = "SELECT COALESCE(NULL, '00:00:00') as null_fallback";
        $test_result = $conn->query($test_query);
        $test = $test_result->fetch_assoc();
        $analysis['null_handling'] = $test;
    }
    
    // 4. Check recent timer operations
    $recent_query = "SELECT id, user_id, date, start_time, stop_time, updated_by, updated_at 
                     FROM time_entries 
                     WHERE user_id = ? 
                     ORDER BY updated_at DESC 
                     LIMIT 10";
    $stmt = $conn->prepare($recent_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_entries = [];
    while ($row = $result->fetch_assoc()) {
        $recent_entries[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'start_time' => $row['start_time'],
            'stop_time' => $row['stop_time'],
            'is_running' => $row['stop_time'] === null || $row['stop_time'] === '00:00:00',
            'updated_by' => $row['updated_by'],
            'updated_at' => $row['updated_at']
        ];
    }
    $analysis['recent_entries'] = $recent_entries;
    
    // 5. Provide solution
    if ($schema['Null'] !== 'YES') {
        $analysis['solution'] = [
            'immediate' => 'Run migration script: /api/migrate-stop-time-nullable.php',
            'sql' => 'ALTER TABLE time_entries MODIFY COLUMN stop_time TIME DEFAULT NULL',
            'workaround' => 'Use stop_time = "00:00:00" as running indicator (not recommended)'
        ];
    } else {
        $analysis['status'] = 'Schema is correct, checking for other issues';
        
        // If schema is correct but still having issues, check for multiple running timers
        if (count($null_entries ?? []) > 1) {
            $analysis['issue_found'] = 'Multiple running timers detected';
            $analysis['recommendation'] = 'Clean up duplicate running timers';
        }
    }
    
    echo json_encode($analysis, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

$conn->close();
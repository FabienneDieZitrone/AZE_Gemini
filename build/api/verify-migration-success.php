<?php
/**
 * Verifiziert ob die Migration erfolgreich war
 * Prüft Schema und Timer-Funktionalität
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$response = [
    'migration_status' => 'unknown',
    'schema_check' => [],
    'timer_stats' => [],
    'functionality_test' => []
];

// 1. Schema Check
$result = $conn->query("DESCRIBE time_entries");
while ($row = $result->fetch_assoc()) {
    if ($row['Field'] === 'stop_time') {
        $response['schema_check'] = [
            'column' => 'stop_time',
            'type' => $row['Type'],
            'null_allowed' => $row['Null'],
            'default' => $row['Default']
        ];
        
        // Check if migration successful
        if ($row['Null'] === 'YES') {
            $response['migration_status'] = 'completed';
        } else {
            $response['migration_status'] = 'pending';
        }
        break;
    }
}

// 2. Timer Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_timers,
        SUM(CASE WHEN stop_time IS NULL THEN 1 ELSE 0 END) as running_timers,
        SUM(CASE WHEN stop_time IS NOT NULL THEN 1 ELSE 0 END) as stopped_timers,
        SUM(CASE WHEN stop_time = '00:00:00' THEN 1 ELSE 0 END) as legacy_running
    FROM time_entries
";
$stats = $conn->query($stats_query)->fetch_assoc();
$response['timer_stats'] = $stats;

// 3. Functionality Test
$response['functionality_test']['null_query_works'] = false;
$test_null = $conn->query("SELECT COUNT(*) as count FROM time_entries WHERE stop_time IS NULL");
if ($test_null) {
    $response['functionality_test']['null_query_works'] = true;
    $response['functionality_test']['null_timer_count'] = $test_null->fetch_assoc()['count'];
}

// 4. Recent Timers
$recent_query = "
    SELECT id, user_id, date, start_time, stop_time, 
           CASE WHEN stop_time IS NULL THEN 'RUNNING' ELSE 'STOPPED' END as status
    FROM time_entries 
    ORDER BY created_at DESC 
    LIMIT 5
";
$recent = $conn->query($recent_query);
$response['recent_timers'] = [];
while ($row = $recent->fetch_assoc()) {
    $response['recent_timers'][] = $row;
}

// Summary
$response['summary'] = [
    'migration_required' => $response['migration_status'] === 'pending',
    'migration_successful' => $response['migration_status'] === 'completed',
    'ready_for_production' => $response['migration_status'] === 'completed' && 
                             $response['functionality_test']['null_query_works']
];

$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
?>
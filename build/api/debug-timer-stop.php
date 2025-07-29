<?php
/**
 * Debug script to test timer stop functionality
 */

require_once __DIR__ . '/error-handler.php';
require_once __DIR__ . '/db-init.php';
require_once __DIR__ . '/auth_helpers.php';

// Initialize API
initialize_api();

// Get user from session
$user_from_session = verify_session_and_get_user();

header('Content-Type: application/json');

if (!isset($_GET['action'])) {
    echo json_encode(['error' => 'No action specified']);
    exit;
}

$action = $_GET['action'];

switch ($action) {
    case 'list_timers':
        // List all timers for current user
        $stmt = $conn->prepare("
            SELECT id, user_id, date, start_time, stop_time, 
                   CASE WHEN stop_time IS NULL THEN 'RUNNING' ELSE 'STOPPED' END as status,
                   created_at, updated_at, updated_by
            FROM time_entries 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->bind_param("i", $user_from_session['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $timers = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'user_id' => $user_from_session['id'],
            'timers' => $timers,
            'running_count' => array_reduce($timers, function($count, $timer) {
                return $count + ($timer['status'] === 'RUNNING' ? 1 : 0);
            }, 0)
        ]);
        break;
        
    case 'force_stop_all':
        // Force stop all running timers
        $stmt = $conn->prepare("
            UPDATE time_entries 
            SET stop_time = NOW(), 
                updated_by = 'Debug Force Stop', 
                updated_at = NOW() 
            WHERE user_id = ? AND stop_time IS NULL
        ");
        $stmt->bind_param("i", $user_from_session['id']);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        
        echo json_encode([
            'message' => "Force stopped $affected running timers",
            'affected_rows' => $affected
        ]);
        break;
        
    case 'test_stop':
        // Test stop a specific timer
        if (!isset($_GET['timer_id'])) {
            echo json_encode(['error' => 'No timer_id specified']);
            exit;
        }
        
        $timer_id = intval($_GET['timer_id']);
        
        // First check current state
        $check_stmt = $conn->prepare("SELECT * FROM time_entries WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $timer_id, $user_from_session['id']);
        $check_stmt->execute();
        $before = $check_stmt->get_result()->fetch_assoc();
        
        // Try to stop it
        $stop_stmt = $conn->prepare("
            UPDATE time_entries 
            SET stop_time = NOW(), 
                updated_by = 'Debug Test Stop', 
                updated_at = NOW() 
            WHERE id = ? AND user_id = ? AND stop_time IS NULL
        ");
        $stop_stmt->bind_param("ii", $timer_id, $user_from_session['id']);
        $stop_stmt->execute();
        $affected = $stop_stmt->affected_rows;
        
        // Check state after
        $check_stmt->execute();
        $after = $check_stmt->get_result()->fetch_assoc();
        
        echo json_encode([
            'timer_id' => $timer_id,
            'before' => $before,
            'after' => $after,
            'affected_rows' => $affected,
            'success' => $affected > 0
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}

$conn->close();
?>
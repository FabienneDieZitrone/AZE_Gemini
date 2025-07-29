<?php
/**
 * Timer Stop API
 * Stops the running timer for the authenticated user
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db.php';

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    $userId = $_SESSION['user_id'];
    $db = getDBConnection();
    
    // Find running timer
    $stmt = $db->prepare("SELECT * FROM time_entries WHERE user_id = ? AND stop_time IS NULL ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No running timer found'
        ]);
        exit;
    }
    
    $timer = $result->fetch_assoc();
    $timerId = $timer['id'];
    
    // Stop the timer
    $stmt = $db->prepare("UPDATE time_entries SET stop_time = NOW() WHERE id = ? AND stop_time IS NULL");
    $stmt->bind_param("i", $timerId);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Timer already stopped or not found');
    }
    
    // Get updated timer
    $stmt = $db->prepare("SELECT *, TIMESTAMPDIFF(SECOND, start_time, stop_time) as duration FROM time_entries WHERE id = ?");
    $stmt->bind_param("i", $timerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $updatedTimer = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'timer_id' => $timerId,
        'stop_time' => $updatedTimer['stop_time'],
        'duration' => $updatedTimer['duration'],
        'message' => 'Timer stopped successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
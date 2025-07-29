<?php
/**
 * Timer Start API
 * Starts a new timer for the authenticated user
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
    $input = json_decode(file_get_contents('php://input'), true);
    $location = $input['location'] ?? 'OFFICE';
    
    $db = getDBConnection();
    
    // Stop any running timers first
    $stmt = $db->prepare("UPDATE time_entries SET stop_time = NOW() WHERE user_id = ? AND stop_time IS NULL");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Start new timer
    $stmt = $db->prepare("INSERT INTO time_entries (user_id, location, start_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $userId, $location);
    $stmt->execute();
    
    $timerId = $db->insert_id;
    
    // Get the created timer
    $stmt = $db->prepare("SELECT * FROM time_entries WHERE id = ?");
    $stmt->bind_param("i", $timerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $timer = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'timer_id' => $timerId,
        'start_time' => $timer['start_time'],
        'location' => $location
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
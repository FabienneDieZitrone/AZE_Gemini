<?php
/**
 * Quick diagnostic: Check master_data table structure
 */
define('API_GUARD', true);
require_once __DIR__ . '/DatabaseConnection.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = DatabaseConnection::getInstance()->getConnection();

    // Get all columns from master_data
    $columns = [];
    if ($res = $conn->query("SHOW COLUMNS FROM master_data")) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = [
                'Field' => $row['Field'],
                'Type' => $row['Type'],
                'Null' => $row['Null'],
                'Default' => $row['Default']
            ];
        }
        $res->close();
    }

    // Check if flexible_workdays column exists
    $hasFlexible = false;
    foreach ($columns as $col) {
        if (strtolower($col['Field']) === 'flexible_workdays') {
            $hasFlexible = true;
            break;
        }
    }

    // Get sample data
    $sampleData = [];
    if ($st = $conn->prepare("SELECT user_id, weekly_hours, workdays, flexible_workdays, can_work_from_home FROM master_data LIMIT 3")) {
        if ($st->execute()) {
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $sampleData[] = $row;
            }
        }
        $st->close();
    }

    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'hasFlexibleColumn' => $hasFlexible,
        'sampleData' => $sampleData
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

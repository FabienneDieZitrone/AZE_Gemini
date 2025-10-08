<?php
// Diagnostics: dump latest approval_requests for admins/leads
define('API_GUARD', true);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/db.php';

try {
    $user = verify_session_and_get_user();
    $role = $user['role'] ?? 'Mitarbeiter';
    if (!in_array($role, ['Admin','Bereichsleiter','Standortleiter'], true)) {
        http_response_code(403);
        echo json_encode(['message' => 'forbidden']);
        exit;
    }

    $out = [
        'who' => [
            'username' => $user['username'] ?? ($user['name'] ?? ''),
            'role' => $role
        ],
        'counts' => [],
        'latest' => []
    ];

    // Counts by normalized status
    if ($rs = $conn->query("SELECT COALESCE(NULLIF(TRIM(LOWER(status)),'') , '(null)') AS s, COUNT(*) AS c FROM approval_requests GROUP BY s ORDER BY c DESC")) {
        while ($r = $rs->fetch_assoc()) { $out['counts'][] = $r; }
        $rs->close();
    }

    // Latest 50 rows (robust without created_at)
    $sql = "SELECT id, type, requested_by, status,
                   JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.date')) AS new_date,
                   JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.location')) AS new_loc
            FROM approval_requests
            ORDER BY id DESC
            LIMIT 50";
    if ($rs = $conn->query($sql)) {
        while ($r = $rs->fetch_assoc()) { $out['latest'][] = $r; }
        $rs->close();
    }

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

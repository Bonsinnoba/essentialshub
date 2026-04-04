<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');

// Authenticate and Require Roles
try {
    $userId = requireRole(RBAC_ALL_ADMINS, $pdo);
    $userName = getUserName($userId, $pdo);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'stats';

    if ($action === 'stats') {
        try {
            // Get total hits
            $totalHits = $pdo->query("SELECT COUNT(*) FROM traffic_logs")->fetchColumn();

            // Get hits by country
            $countryStats = $pdo->query("SELECT country, COUNT(*) as count FROM traffic_logs GROUP BY country ORDER BY count DESC LIMIT 10")->fetchAll();

            // Get recent logs
            $recentLogs = $pdo->query("SELECT * FROM traffic_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();

            // Get current restrictions
            $restrictions = $pdo->query("SELECT * FROM access_restrictions")->fetchAll();

            // Get hourly stats (last 48 hours)
            $hourlyStats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as count FROM traffic_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) GROUP BY hour ORDER BY hour DESC")->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => [
                    'totalHits' => $totalHits,
                    'countryStats' => $countryStats,
                    'hourlyStats' => $hourlyStats,
                    'recentLogs' => $recentLogs,
                    'restrictions' => $restrictions
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    $content = trim(file_get_contents("php://input"));
    $decoded = json_decode($content, true);
    $action = $decoded['action'] ?? '';

    if ($action === 'add_restriction') {
        $type = $decoded['type'] ?? 'country';
        $value = $decoded['value'] ?? '';
        $reason = $decoded['reason'] ?? 'Administrator restricted access';

        if (empty($value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Value is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO access_restrictions (type, value, reason) VALUES (?, ?, ?)");
            $stmt->execute([$type, $value, $reason]);

            logger('info', 'SECURITY', "New restriction added: {$type} = {$value} by {$userName}");

            echo json_encode(['success' => true, 'message' => 'Restriction added successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'remove_restriction') {
        $id = $decoded['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Restriction ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM access_restrictions WHERE id = ?");
            $stmt->execute([$id]);

            logger('info', 'SECURITY', "Restriction removed (ID: {$id}) by {$userName}");

            echo json_encode(['success' => true, 'message' => 'Restriction removed successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'clear_hour') {
        $hour = $decoded['hour'] ?? null;

        if (!$hour) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Hour timestamp is required']);
            exit;
        }

        try {
            // Optimized range-based delete for performance with index
            $start = $hour;
            $end = date('Y-m-d H:i:s', strtotime($hour . ' +1 hour'));
            
            $stmt = $pdo->prepare("DELETE FROM traffic_logs WHERE created_at >= ? AND created_at < ?");
            $stmt->execute([$start, $end]);
            $count = $stmt->rowCount();

            logger('info', 'SECURITY', "Traffic logs for {$hour} cleared by {$userName} ({$count} entries)");

            echo json_encode(['success' => true, 'message' => "Successfully cleared {$count} entries for {$hour}"]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

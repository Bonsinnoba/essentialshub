<?php
// Mocking Auth for testing
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'stats';

// Bypass authenticate() by defining it or using a dummy token
// Actually, I'll just manually run the query logic from admin_traffic.php
require_once 'api/db.php';
require_once 'api/security.php';

try {
    echo "Running Stats Queries...\n";
    $totalHits = $pdo->query("SELECT COUNT(*) FROM traffic_logs")->fetchColumn();
    echo "Total Hits: $totalHits\n";

    $countryStats = $pdo->query("SELECT country, COUNT(*) as count FROM traffic_logs GROUP BY country ORDER BY count DESC LIMIT 10")->fetchAll();
    echo "Country Stats Fetched\n";

    $recentLogs = $pdo->query("SELECT * FROM traffic_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();
    echo "Recent Logs Fetched\n";

    $restrictions = $pdo->query("SELECT * FROM access_restrictions")->fetchAll();
    echo "Restrictions Fetched\n";

    $hourlyStats = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as count FROM traffic_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) GROUP BY hour ORDER BY hour DESC")->fetchAll();
    echo "Hourly Stats Fetched\n";

    echo "SUCCESS: ALL QUERIES PASSED\n";
} catch (Exception $e) {
    echo "CRASH: " . $e->getMessage() . "\n";
}

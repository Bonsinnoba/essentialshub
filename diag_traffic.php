<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'api/db.php';
    echo "DB loaded\n";
    require_once 'api/security.php';
    echo "Security loaded\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'traffic_logs'");
    $exists = $stmt->fetch();
    echo "Traffic Table Exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if($exists) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM traffic_logs");
        echo "Traffic Logs Count: " . $stmt->fetchColumn() . "\n";
    }
} catch (Exception $e) {
    echo "CRASH: " . $e->getMessage() . "\n";
}

<?php
$config = require __DIR__ . '/../api/.env.php';

$host = $config['DB_HOST'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];
$db   = $config['DB_NAME'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Running OPTIMIZE TABLE traffic_logs...\n";
    $stmt = $pdo->query("OPTIMIZE TABLE traffic_logs");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    print_r($result);
    echo "Done.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

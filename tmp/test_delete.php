<?php
require 'api/db.php';

// Insert test record
$pdo->exec("INSERT INTO traffic_logs (ip_address, created_at) VALUES ('1.2.3.4', '2020-01-01 10:30:00')");

// Delete by hour
$hour = '2020-01-01 10:00:00';
$stmt = $pdo->prepare("DELETE FROM traffic_logs WHERE DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') = ?");
$stmt->execute([$hour]);
echo "Deleted rows: " . $stmt->rowCount() . PHP_EOL;

// Verify
$stmt = $pdo->query("SELECT COUNT(*) FROM traffic_logs WHERE ip_address = '1.2.3.4'");
echo "Remaining rows: " . $stmt->fetchColumn() . PHP_EOL;

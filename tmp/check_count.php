<?php
require 'api/db.php';
$count = $pdo->query('SELECT COUNT(*) FROM traffic_logs')->fetchColumn();
echo "Total traffic logs: " . $count . PHP_EOL;

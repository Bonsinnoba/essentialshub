<?php
require 'api/db.php';
$cols = $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols);

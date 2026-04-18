<?php
require 'api/db.php';
$stmt = $pdo->query('SELECT slug, is_published FROM cms_pages');
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($data);

<?php
require 'api/db.php';
$stmt = $pdo->query('SELECT slug, title, content FROM cms_pages');
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($data as &$row) {
    $row['content_preview'] = substr($row['content'], 0, 50) . '...';
    unset($row['content']);
}
print_r($data);

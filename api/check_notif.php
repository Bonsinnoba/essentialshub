<?php require 'db.php'; print_r($pdo->query('DESCRIBE admin_notifications')->fetchAll(PDO::FETCH_ASSOC));

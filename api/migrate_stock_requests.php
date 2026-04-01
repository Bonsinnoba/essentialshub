<?php
// api/migrate_stock_requests.php
require_once 'db.php';

try {
    echo "Starting Stock Request Migration..." . PHP_EOL;

    // 1. Create stock_requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch_id INT NOT NULL,
        requester_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'fulfilled') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (branch_id),
        INDEX (requester_id),
        INDEX (status),
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "Created table: stock_requests" . PHP_EOL;

    // 2. Create stock_request_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_request_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        INDEX (request_id),
        INDEX (product_id),
        FOREIGN KEY (request_id) REFERENCES stock_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
    echo "Created table: stock_request_items" . PHP_EOL;

    echo "Migration Completed Successfully!" . PHP_EOL;
} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . PHP_EOL;
}

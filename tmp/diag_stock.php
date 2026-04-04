<?php
require_once __DIR__ . '/../api/db.php';
try {
    // Check tables exist
    $tables = $pdo->query("SHOW TABLES LIKE 'stock_request%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . (empty($tables) ? "NONE - Tables are missing!" : implode(', ', $tables)) . PHP_EOL;

    if (in_array('stock_requests', $tables)) {
        // Total count
        $count = $pdo->query("SELECT COUNT(*) FROM stock_requests")->fetchColumn();
        echo "Total stock_requests rows: " . $count . PHP_EOL;

        // Recent rows
        $rows = $pdo->query("SELECT id, branch_id, requester_id, status, notes, created_at FROM stock_requests ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo "Recent requests:" . PHP_EOL;
        foreach ($rows as $r) {
            echo "  " . json_encode($r) . PHP_EOL;
        }
    }

    // Check staff users and their branch assignments
    $staff = $pdo->query("SELECT id, name, role, branch_id FROM users WHERE role IN ('pos_cashier','branch_admin','store_manager') LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "Staff users:" . PHP_EOL;
    foreach ($staff as $u) {
        echo "  " . json_encode($u) . PHP_EOL;
    }

    // Check if the submitting user has branch_id = 0 or NULL
    $noBranch = $pdo->query("SELECT id, name, role, branch_id FROM users WHERE role IN ('pos_cashier','branch_admin','store_manager') AND (branch_id IS NULL OR branch_id = 0)")->fetchAll(PDO::FETCH_ASSOC);
    echo "Staff with NO branch assigned (these will get blocked):" . PHP_EOL;
    foreach ($noBranch as $u) {
        echo "  " . json_encode($u) . PHP_EOL;
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

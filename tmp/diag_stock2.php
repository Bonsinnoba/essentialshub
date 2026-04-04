<?php
require_once __DIR__ . '/../api/db.php';
$out = [];

try {
    $tables = $pdo->query("SHOW TABLES LIKE 'stock_request%'")->fetchAll(PDO::FETCH_COLUMN);
    $out[] = "TABLES: " . (empty($tables) ? "NONE - Tables are MISSING!" : implode(', ', $tables));

    if (in_array('stock_requests', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM stock_requests")->fetchColumn();
        $out[] = "stock_requests row count: $count";

        $rows = $pdo->query("SELECT id, branch_id, requester_id, status, created_at FROM stock_requests ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $out[] = "Recent requests:";
            foreach ($rows as $r) {
                $out[] = "  #" . $r['id'] . " branch_id=" . $r['branch_id'] . " requester=" . $r['requester_id'] . " status=" . $r['status'] . " at=" . $r['created_at'];
            }
        } else {
            $out[] = "No rows in stock_requests.";
        }
    }

    $staff = $pdo->query("SELECT id, name, role, branch_id FROM users WHERE role IN ('pos_cashier','branch_admin','store_manager')")->fetchAll(PDO::FETCH_ASSOC);
    $out[] = "Staff users (" . count($staff) . "):";
    foreach ($staff as $u) {
        $out[] = "  id=" . $u['id'] . " name=" . $u['name'] . " role=" . $u['role'] . " branch_id=" . ($u['branch_id'] ?? 'NULL');
    }

    $blocked = $pdo->query("SELECT id, name, role, branch_id FROM users WHERE role IN ('pos_cashier','branch_admin','store_manager') AND (branch_id IS NULL OR branch_id = 0)")->fetchAll(PDO::FETCH_ASSOC);
    $out[] = "Blocked staff (no branch, " . count($blocked) . "):";
    foreach ($blocked as $u) {
        $out[] = "  id=" . $u['id'] . " name=" . $u['name'] . " role=" . $u['role'];
    }

} catch (Exception $e) {
    $out[] = "ERROR: " . $e->getMessage();
}

file_put_contents(__DIR__ . '/diag_stock_result.txt', implode("\n", $out) . "\n");
echo "Done. Check tmp/diag_stock_result.txt\n";

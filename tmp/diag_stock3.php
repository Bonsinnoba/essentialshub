<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/security.php';
$out = [];

// Simulate what happens when User ID 5 (the HQ admin, branch_admin, branch_id=NULL) calls GET /stock_requests.php

$userId = 5;
$role = getUserRole($userId, $pdo);
$branchId = getManagerBranchId($userId, $pdo) ?? 0;

$out[] = "=== Simulating GET for User #5 ===";
$out[] = "Role: $role";
$out[] = "BranchId: $branchId";
$out[] = "Is admin? " . (in_array($role, RBAC_ALL_ADMINS) ? 'YES - sees ALL' : 'NO - filtered to branch');

try {
    if (in_array($role, RBAC_ALL_ADMINS)) {
        $stmt = $pdo->query("
            SELECT r.*, u.name as requester_name 
            FROM stock_requests r 
            JOIN users u ON r.requester_id = u.id 
            ORDER BY r.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, u.name as requester_name 
            FROM stock_requests r 
            JOIN users u ON r.requester_id = u.id 
            WHERE r.branch_id = ? 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$branchId]);
    }
    $requests = $stmt->fetchAll();
    $out[] = "Requests returned: " . count($requests);
    foreach ($requests as $req) {
        $out[] = "  #" . $req['id'] . " branch=" . $req['branch_id'] . " status=" . $req['status'] . " requester=" . $req['requester_name'];
    }
} catch (Exception $e) {
    $out[] = "QUERY ERROR: " . $e->getMessage();
}

// Also check the App.jsx routing — does the role have access to StockManagement?
$out[] = "";
$out[] = "=== RBAC Groups ===";
$out[] = "RBAC_STAFF_GROUP: " . implode(', ', RBAC_STAFF_GROUP);
$out[] = "RBAC_ALL_ADMINS: " . implode(', ', RBAC_ALL_ADMINS);

// Check if updated_at column exists on stock_requests
$cols = $pdo->query("SHOW COLUMNS FROM stock_requests")->fetchAll(PDO::FETCH_COLUMN);
$out[] = "";
$out[] = "stock_requests columns: " . implode(', ', $cols);

file_put_contents(__DIR__ . '/diag_stock_result.txt', implode("\n", $out) . "\n");
echo "Done.\n";

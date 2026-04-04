<?php
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/security.php';

$out = [];
$out[] = "=== Simulating GET for Super Admin ===";

// Find a super user
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'super' LIMIT 1");
$super = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$super) {
    $out[] = "NO SUPER USER FOUND IN DATABASE";
} else {
    $userId = $super['id'];
    $role = 'super';
    $branchId = getManagerBranchId($userId, $pdo) ?? 0;

    $out[] = "User logic: Role=$role, BranchId=$branchId";
    $out[] = "Is in RBAC_STAFF_GROUP? " . (in_array($role, RBAC_STAFF_GROUP) ? 'YES' : 'NO');
    $out[] = "Is in RBAC_ALL_ADMINS? " . (in_array($role, RBAC_ALL_ADMINS) ? 'YES' : 'NO');
    
    // Simulate listRequests
    try {
        if (in_array($role, RBAC_ALL_ADMINS)) {
            $stmt = $pdo->query("
                SELECT r.*, u.name as requester_name 
                FROM stock_requests r 
                JOIN users u ON r.requester_id = u.id 
                ORDER BY r.created_at DESC
            ");
            $out[] = "Executing ADMIN query...";
        } else {
            $stmt = $pdo->prepare("
                SELECT r.*, u.name as requester_name 
                FROM stock_requests r 
                JOIN users u ON r.requester_id = u.id 
                WHERE r.branch_id = ? 
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$branchId]);
            $out[] = "Executing STAFF query config for branch $branchId...";
        }
        $requests = $stmt->fetchAll();
        $out[] = "Requests fetched: " . count($requests);

        // Fetch items logic
        foreach ($requests as &$req) {
            $itemStmt = $pdo->prepare("
                SELECT ri.*, p.name as product_name, p.sku 
                FROM stock_request_items ri 
                JOIN products p ON ri.product_id = p.id 
                WHERE ri.request_id = ?
            ");
            $itemStmt->execute([$req['id']]);
            $req['items'] = $itemStmt->fetchAll();
            $out[] = "  Request #{$req['id']} items count: " . count($req['items']);
        }
        
    } catch (Exception $e) {
        $out[] = "ERROR: " . $e->getMessage();
    }
}

file_put_contents(__DIR__ . '/diag_super.txt', implode("\n", $out));
echo "Done.";

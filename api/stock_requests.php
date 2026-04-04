<?php
// api/stock_requests.php
require_once 'cors_middleware.php';
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');

// Authentication & RBAC
$userId = authenticate();
$role = getUserRole($userId, $pdo);
$branchId = getManagerBranchId($userId, $pdo) ?? 0;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Only staff can request stock
        requireRole(RBAC_STAFF_GROUP, $pdo);
        createRequest($pdo, $userId, $branchId);
        break;

    case 'GET':
        // Admins can see all, staff see their branch's
        requireRole(array_unique(array_merge(RBAC_STAFF_GROUP, RBAC_ALL_ADMINS)), $pdo);
        listRequests($pdo, $role, $branchId);
        break;

    case 'PATCH':
        // Admins approve/reject; branch staff fulfill
        requireRole(array_unique(array_merge(RBAC_STAFF_GROUP, RBAC_ALL_ADMINS)), $pdo);
        updateRequestStatus($pdo, $userId, $role, $branchId);
        break;

    default:
        sendResponse(false, 'Method Not Allowed', null, 405);
}

/**
 * Create a new stock request
 */
function createRequest($pdo, $userId, $branchId) {
    if ($branchId == 0) {
        sendResponse(false, 'Main branch cannot request stock from itself via this system.');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $items = $data['items'] ?? [];
    $notes = sanitizeInput($data['notes'] ?? '');

    if (empty($items)) {
        sendResponse(false, 'No items requested.');
    }

    try {
        $pdo->beginTransaction();

        // 1. Create main request record
        $stmt = $pdo->prepare("INSERT INTO stock_requests (branch_id, requester_id, status, notes) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$branchId, $userId, $notes]);
        $requestId = $pdo->lastInsertId();

        // 2. Add items
        $itemStmt = $pdo->prepare("INSERT INTO stock_request_items (request_id, product_id, quantity) VALUES (?, ?, ?)");
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            if ($pid > 0 && $qty > 0) {
                $itemStmt->execute([$requestId, $pid, $qty]);
            }
        }

        $pdo->commit();
        sendResponse(true, 'Stock request submitted successfully.', ['id' => $requestId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Stock Request Error: " . $e->getMessage());
        sendResponse(false, 'Internal Server Error while creating request.');
    }
}

/**
 * List stock requests
 */
function listRequests($pdo, $role, $branchId) {
    try {
        if (in_array($role, ['admin', 'super']) || empty($branchId)) {
            // HQ Admin sees everything
            $stmt = $pdo->query("
                SELECT r.*, u.name as requester_name 
                FROM stock_requests r 
                JOIN users u ON r.requester_id = u.id 
                ORDER BY r.created_at DESC
            ");
        } else {
            // Staff see their branch
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

        // Fetch items for each request
        foreach ($requests as &$req) {
            $itemStmt = $pdo->prepare("
                SELECT ri.*, p.name as product_name, p.product_code as sku 
                FROM stock_request_items ri 
                JOIN products p ON ri.product_id = p.id 
                WHERE ri.request_id = ?
            ");
            $itemStmt->execute([$req['id']]);
            $req['items'] = $itemStmt->fetchAll();
        }

        sendResponse(true, 'Success', $requests);
    } catch (Exception $e) {
        error_log("List Requests Error: " . $e->getMessage());
        sendResponse(false, 'Failed to fetch requests.');
    }
}

/**
 * Update request status (Internal Transfers)
 */
function updateRequestStatus($pdo, $userId, $role, $branchId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $requestId = (int)($data['id'] ?? 0);
    $newStatus = sanitizeInput($data['status'] ?? '');

    $validStatuses = ['approved', 'rejected', 'fulfilled'];
    if (!$requestId || !in_array($newStatus, $validStatuses)) {
        sendResponse(false, 'Invalid request parameters.');
    }

    $isHqAdmin = in_array($role, ['admin', 'super']) || empty($branchId);

    if (!$isHqAdmin) {
        if ($newStatus !== 'fulfilled') {
            sendResponse(false, 'Forbidden: Only HQ can approve or reject requests.', null, 403);
        }
        
        $stmt = $pdo->prepare("SELECT branch_id FROM stock_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $reqBranchId = $stmt->fetchColumn();

        if ($reqBranchId != $branchId) {
            sendResponse(false, 'Forbidden: You can only fulfill requests for your own branch.', null, 403);
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE stock_requests SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $requestId]);

        // CRITICAL: Internal Transfer Logic
        // We do NOT reduce products.stock_quantity here because:
        // 1. The item stays in the business.
        // 2. Only a POS sale at the target branch should reduce global stock.
        
        sendResponse(true, "Request marked as " . ucfirst($newStatus));
    } catch (Exception $e) {
        error_log("Update Request Error: " . $e->getMessage());
        sendResponse(false, 'Failed to update request.');
    }
}

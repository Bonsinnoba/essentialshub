<?php
require 'cors_middleware.php';
require 'db.php';
require 'security.php';

header('Content-Type: application/json');

// Authenticate User
try {
    $userId = authenticate();
    $role = getUserRole($userId, $pdo);
    $userName = getUserName($userId, $pdo);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Granular Role Access
if ($method === 'GET') {
    // Basic audit: Admins, Branch Admins, Accountants, Super
    requireRole(RBAC_ALL_ADMINS, $pdo);
} elseif ($method === 'POST') {
    // Moderation: Admins and Branch Admins only
    requireRole(['super', 'admin', 'branch_admin'], $pdo);
}

if ($method === 'GET') {
    try {
        $filterSql = "";
        $params = [];
        
        // Restriction: managers only see 'customer' role users for data privacy
        if ($role === 'store_manager' || $role === 'branch_admin') {
            $filterSql = " WHERE u.role = 'customer' ";
        }

        // Fetch all users with basic order summary and branch name
        $stmt = $pdo->prepare("
            SELECT 
                u.*, 
                sb.name as branch_name,
                (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as orders_count,
                (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id) as total_spent
            FROM users u 
            LEFT JOIN store_branches sb ON u.branch_id = sb.id
            $filterSql
            ORDER BY u.created_at DESC
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Security & Performance: Remove sensitive/heavy data
        foreach ($users as &$user) {
            unset($user['password_hash']);
            if (isset($user['profile_image'])) unset($user['profile_image']);
        }

        echo json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $content = trim(file_get_contents("php://input"));
    $decoded = json_decode($content, true);
    $action = $decoded['action'] ?? '';

    if ($action === 'delete') {
        $id = $decoded['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            logger('warn', 'STAFF', "User ID: {$id} was permanently deleted by {$userName}");

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'toggle_status') {
        $id = $decoded['id'] ?? null;
        $currentStatus = $decoded['status'] ?? 'Active';

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        try {
            $newStatus = ($currentStatus === 'Suspended') ? 'Active' : 'Suspended';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            logger('info', 'STAFF', "User ID: {$id} status updated to {$newStatus} by {$userName}");

            echo json_encode(['success' => true, 'status' => $newStatus]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'set_role') {
        $id = $decoded['id'] ?? null;
        $newRole = $decoded['role'] ?? 'customer';

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        try {
            // First, find the target user's current role
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            $currentRole = $targetUser['role'];

            // Security Check: Only a super admin can alter a super admin, 
            // or assign the super admin role to someone else.
            if (($currentRole === 'super' || $newRole === 'super') && $role !== 'super') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied: Super admin privileges required.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $id]);

            logger('info', 'STAFF', "User ID: {$id} role updated to " . strtoupper($newRole) . " by {$userName}");

            echo json_encode(['success' => true, 'role' => $newRole]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }

    } elseif ($action === 'set_branch') {
        $id = $decoded['id'] ?? null;
        $branch_id = $decoded['branch_id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE users SET branch_id = ? WHERE id = ?");
            $stmt->execute([$branch_id, $id]);

            logger('info', 'STAFF', "User ID: {$id} assigned to Branch ID: {$branch_id} by {$userName}");

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');

/**
 * POS Checkout Handler
 * High-speed endpoint for physical store sales.
 */

// 1. Authenticate Cashier (Admin/Branch Admin/Super)
try {
    $cashierId = authenticate($pdo);
    // Fetch branch info and role
    $stmt = $pdo->prepare("SELECT role, branch_id, name FROM users WHERE id = ?");
    $stmt->execute([$cashierId]);
    $cashier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cashier || !in_array($cashier['role'], ['super', 'admin', 'branch_admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: Only authorized staff can perform POS sales.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || empty($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid transaction data.']);
    exit;
}

$items = $data['items'];
$totalAmount = (float)($data['total_amount'] ?? 0);
$paymentMethod = sanitizeInput($data['payment_method'] ?? 'cash'); // 'cash', 'momo'
$customerEmail = sanitizeInput($data['customer_email'] ?? ''); // Optional loyalty link
$branchId = $cashier['branch_id'] ?? 1; // Fallback to HQ if not assigned

try {
    $pdo->beginTransaction();

    // 2. Identify/Link Customer if provided
    $customerId = null;
    if ($customerEmail) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$customerEmail]);
        $customerId = $stmt->fetchColumn() ?: null;
    }

    // 3. Create POS Order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, total_amount, status, payment_method, 
            order_type, source_branch_id, cashier_id
        ) VALUES (?, ?, 'delivered', ?, 'pos', ?, ?)
    ");
    $stmt->execute([$customerId, $totalAmount, $paymentMethod, $branchId, $cashierId]);
    $orderId = $pdo->lastInsertId();

    // 4. Process Items & Deduct Stock
    $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
    $updateStock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    $checkProduct = $pdo->prepare("SELECT name, stock_quantity FROM products WHERE id = ?");

    foreach ($items as $item) {
        $pId = (int)$item['id'];
        $qty = (int)$item['quantity'];
        $price = (float)$item['price'];

        $insertItem->execute([$orderId, $pId, $qty, $price]);
        $updateStock->execute([$qty, $pId]);

        // Low stock alerts logic
        $checkProduct->execute([$pId]);
        $prod = $checkProduct->fetch(PDO::FETCH_ASSOC);
        if ($prod && $prod['stock_quantity'] <= 10) {
            $notifTitle = "Low Stock: " . $prod['name'];
            $notifMsg = "Physical sale (ORD-{$orderId}) reduced stock to {$prod['stock_quantity']}. Please restock.";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) SELECT id, ?, ?, 'system' FROM users WHERE role IN ('admin', 'super')")
                ->execute([$notifTitle, $notifMsg]);
        }
    }

    // 5. Loyalty Points (1 point per 10 currency units)
    if ($customerId) {
        $points = floor($totalAmount / 10);
        if ($points > 0) {
            $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?")->execute([$points, $customerId]);
        }
    }

    $pdo->commit();

    logger('ok', 'POS', "Physical sale completed: ORD-{$orderId} (Amt: {$totalAmount}) by {$cashier['name']} at Branch ID {$branchId}");

    echo json_encode([
        'success' => true,
        'message' => 'Sale completed successfully',
        'order_id' => $orderId,
        'points_earned' => $customerId ? floor($totalAmount / 10) : 0
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("POS Sale failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Transaction failed. ' . $e->getMessage()]);
}

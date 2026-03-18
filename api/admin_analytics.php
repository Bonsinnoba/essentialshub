<?php
// backend/admin_analytics.php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');

// Only Admins/Super Admins
requireRole(RBAC_ALL_ADMINS, $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $data = [];

        // 1. Total Revenue (Completed/Delivered orders)
        $revStmt = $pdo->query("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('delivered', 'shipped', 'completed')");
        $data['total_revenue'] = (float)$revStmt->fetchColumn() ?: 0;

        // 2. Active Users count
        $usersStmt = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'customer'");
        $data['total_customers'] = (int)$usersStmt->fetchColumn();

        // 3. New Orders (Pending)
        $ordersStmt = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'pending'");
        $data['pending_orders'] = (int)$ordersStmt->fetchColumn();

        // 4. Low Stock Products
        $stockStmt = $pdo->query("SELECT COUNT(id) FROM products WHERE stock_quantity <= 10");
        $data['low_stock_count'] = (int)$stockStmt->fetchColumn();

        // 5. Revenue by Day (Last 30 Days)
        $chartStmt = $pdo->query("
            SELECT DATE(created_at) as date, SUM(total_amount) as daily_revenue 
            FROM orders 
            WHERE status IN ('delivered', 'shipped', 'completed', 'processing')
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC
        ");
        $data['revenue_chart'] = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Top Selling Products
        $topProdsStmt = $pdo->query("
            SELECT p.name, SUM(oi.quantity) as total_sold
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status != 'cancelled'
            GROUP BY oi.product_id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $data['top_products'] = $topProdsStmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. Inventory Status Breakdown
        $invStmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN stock_quantity > 10 THEN 1 ELSE 0 END) as optimal,
                SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
            FROM products
        ");
        $data['inventory_status'] = $invStmt->fetch(PDO::FETCH_ASSOC);

        // 8. Strategic Insights
        // a. Revenue Peak
        $peakRevenue = 0;
        foreach ($data['revenue_chart'] as $day) {
            if ($day['daily_revenue'] > $peakRevenue) {
                $peakRevenue = $day['daily_revenue'];
            }
        }
        $data['strategic_insights']['revenue_peak'] = $peakRevenue;

        // b. Fulfillment Efficiency (Avg time to ship in hours)
        $effStmt = $pdo->query("
            SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) 
            FROM orders 
            WHERE status IN ('shipped', 'delivered')
        ");
        $data['strategic_insights']['ship_efficiency'] = round((float)$effStmt->fetchColumn(), 1) ?: 0;

        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        error_log("Analytics fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch analytics data']);
    }
    exit;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}

<?php
/**
 * EssentialsHub - Cleanup Demo Data
 * This script is intended to wipe development/demo data before production launch.
 * SECURITY: Only runs if APP_ENV is set to 'development'.
 */

require_once 'db.php';
require_once 'security.php';

$config = $GLOBALS['config'] ?? require '.env.php';

// Authenticate Super Admin
try {
    $userId = requireRole(['super'], $pdo);
} catch (Exception $e) {
    sendResponse(false, 'Unauthorized: Super Admin access required.', null, 401);
}

// 1. Environment Guard
if (($config['APP_ENV'] ?? 'production') !== 'development') {
    if (function_exists('logApp')) logApp('security', 'CLEANUP', 'Blocked cleanup attempt in production environment.');
    sendResponse(false, 'Security Error: Cleanup is only allowed in Development mode.', null, 403);
}

try {
    $pdo->beginTransaction();

    // 2. Transactional Tables to Wipe
    $tablesToWipe = [
        'order_items',
        'order_notes',
        'order_tracking',
        'returns',
        'warehouse_dispatches',
        'abandoned_carts',
        'notifications',
        'api_rate_limits',
        'orders' // Parent table last if using standard constraints
    ];

    foreach ($tablesToWipe as $table) {
        // Check if table exists before trying to wipe
        $exists = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($exists) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("TRUNCATE TABLE `$table` ");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }
    }

    // 3. User Cleanup (Wipe all non-admin/staff customers if desired)
    // For now, let's just wipe customers and keep staff/admins to avoid locking out the user
    $pdo->exec("DELETE FROM users WHERE role NOT IN ('admin', 'super', 'branch_admin', 'store_manager', 'pos_cashier', 'accountant', 'marketing')");

    $pdo->commit();
    logApp('ok', 'CLEANUP', "Demo data wiped successfully by UID: $userId");
    sendResponse(true, 'System cleanup completed successfully. Transactional records and customer accounts have been reset.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logApp('error', 'CLEANUP', 'Cleanup failed: ' . $e->getMessage());
    sendResponse(false, 'Cleanup failed: ' . $e->getMessage(), null, 500);
}

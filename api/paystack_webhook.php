<?php
/**
 * Paystack Webhook Handler
 * This script handles asynchronous notifications from Paystack.
 * It does NOT require user authentication via session/cookie.
 */

require_once 'db.php';
require_once 'security.php';

// Disable error reporting for cleaner output to Paystack
error_reporting(0);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

// 1. Validate Paystack Signature
$config = require '.env.php';
$secretKey = $config['PAYSTACK_SECRET'] ?? "";

if (!$secretKey) {
    logger('error', 'WEBHOOK', "Paystack Secret Key is missing in .env.php");
    exit;
}

$input = file_get_contents("php://input");
$paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if ($paystackSignature !== hash_hmac('sha256', $input, $secretKey)) {
    logger('warning', 'WEBHOOK', "Invalid Paystack signature received.");
    http_response_code(401);
    exit;
}

// 2. Parse Event Data
$event = json_decode($input, true);

if (!$event || !isset($event['event'])) {
    exit;
}

// 3. Handle Events
http_response_code(200); // Acknowledge receipt early

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $reference = $data['reference'];
    $amountPaid = $data['amount'] / 100; // kobo to GHS
    $customerEmail = $data['customer']['email'];

    try {
        // Find user by email (as fallback) or metadata if we sent user_id in payload
        $userId = $data['metadata']['user_id'] ?? null;
        if (!$userId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$customerEmail]);
            $userId = $stmt->fetchColumn();
        }

        if (!$userId) {
            logger('error', 'WEBHOOK', "Could not find user for email: $customerEmail (Ref: $reference)");
            exit;
        }

        $pdo->beginTransaction();

        // Check if reference already processed
        // In orders:
        $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE payment_reference = ?");
        $stmt->execute([$reference]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            if ($order['status'] === 'pending') {
                $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?")->execute([$order['id']]);
                logger('ok', 'WEBHOOK', "Order #{$order['id']} updated to processing via webhook.");
            }
        } else {
            // Check if it's a wallet top-up (reference might be in wallet_transactions)
            $stmt = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference = ?");
            $stmt->execute([$reference]);
            if (!$stmt->fetch()) {
                // If not order and not wallet yet, maybe it's a top-up we didn't record yet
                if (($data['metadata']['type'] ?? '') === 'wallet_topup') {
                    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, reference, title, details, status) VALUES (?, ?, 'credit', ?, 'Wallet Top-up', 'Funded via Paystack (Webhook)', 'completed')");
                    $stmt->execute([$userId, $amountPaid, $reference]);

                    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->execute([$amountPaid, $userId]);
                    logger('ok', 'WEBHOOK', "Wallet for User #$userId topped up via webhook.");
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logger('error', 'WEBHOOK', "Webhook processing error: " . $e->getMessage());
    }
}

exit;

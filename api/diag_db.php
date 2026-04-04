<?php
require_once 'db.php';
header('Content-Type: text/plain');

echo "--- PRODUCTS TABLE STRUCTURE ---\n";
try {
    $stmt = $pdo->query("DESCRIBE products");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("%-20s %-20s %s\n", $row['Field'], $row['Type'], $row['Null']);
    }
} catch (Exception $e) {
    echo "Error describing products: " . $e->getMessage() . "\n";
}

echo "\n--- SAMPLE PRODUCT VALUES (ID 1) ---\n";
try {
    $stmt = $pdo->query("SELECT id, name, price, discount_percent, sale_ends_at FROM products LIMIT 10");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error fetching samples: " . $e->getMessage() . "\n";
}

<?php
require 'db.php';

function addColumn($pdo, $table, $column, $definition) {
    try {
        $pdo->query("SELECT $column FROM $table LIMIT 1");
        echo "Column $column already exists in $table.\n";
    } catch (Exception $e) {
        echo "Adding column $column to $table...\n";
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        echo "Success: $column added.\n";
    }
}

addColumn($pdo, 'orders', 'order_type', "ENUM('online', 'pos') DEFAULT 'online'");
addColumn($pdo, 'orders', 'source_branch_id', "INT DEFAULT NULL");
addColumn($pdo, 'orders', 'cashier_id', "INT DEFAULT NULL");

echo "Migration complete.\n";

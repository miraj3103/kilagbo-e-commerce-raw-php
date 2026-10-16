<?php
require_once __DIR__ . '/config/config.php';

echo "Updating database...\n";

try {
    // Add payment columns to affiliate_orders if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM affiliate_orders")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('payment_method', $columns)) {
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cod' AFTER total_amount");
        echo "Added payment_method column.\n";
    }

    if (!in_array('payment_number', $columns)) {
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN payment_number VARCHAR(20) DEFAULT NULL AFTER payment_method");
        echo "Added payment_number column.\n";
    }

    if (!in_array('transaction_id', $columns)) {
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL AFTER payment_number");
        echo "Added transaction_id column.\n";
    }

    echo "Database update successful!\n";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}

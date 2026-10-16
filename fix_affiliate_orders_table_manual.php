<?php
// Manual connection for localhost XAMPP
$host = 'localhost';
$db = 'kilagboc_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected to database.\n";
    
    // Check if columns exist
    $columns = [];
    $stmt = $pdo->query("DESCRIBE affiliate_orders");
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
    }
    
    // Add payment_method if not exists
    if (!in_array('payment_method', $columns)) {
        echo "Adding payment_method column...\n";
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN payment_method VARCHAR(20) DEFAULT 'cod' AFTER commission_amount");
    } else {
        echo "payment_method column already exists.\n";
    }
    
    // Add payment_number if not exists
    if (!in_array('payment_number', $columns)) {
        echo "Adding payment_number column...\n";
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN payment_number VARCHAR(20) DEFAULT NULL AFTER payment_method");
    } else {
        echo "payment_number column already exists.\n";
    }
    
    // Add transaction_id if not exists
    if (!in_array('transaction_id', $columns)) {
        echo "Adding transaction_id column...\n";
        $pdo->exec("ALTER TABLE affiliate_orders ADD COLUMN transaction_id VARCHAR(50) DEFAULT NULL AFTER payment_number");
    } else {
        echo "transaction_id column already exists.\n";
    }
    
    echo "Table update completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

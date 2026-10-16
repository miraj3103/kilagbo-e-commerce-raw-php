<?php
$host = 'localhost';
$db = 'kilagboc_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Starting migration...\n";
    
    // Convert delivery_area to VARCHAR in orders
    echo "Updating orders table...\n";
    $pdo->query("ALTER TABLE orders MODIFY COLUMN delivery_area VARCHAR(50)");
    echo "orders table updated.\n";
    
    // Convert shipping_area to VARCHAR in wholesale_orders
    echo "Updating wholesale_orders table...\n";
    $pdo->query("ALTER TABLE wholesale_orders MODIFY COLUMN shipping_area VARCHAR(50)");
    echo "wholesale_orders table updated.\n";
    
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}

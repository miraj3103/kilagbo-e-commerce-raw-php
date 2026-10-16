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
    
    echo "Dropping foreign key constraint from order_items...\n";
    
    // Drop the constraint that limits product_id to the products table
    $pdo->query("ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2");
    
    echo "Constraint order_items_ibfk_2 dropped successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "This might mean the constraint was already dropped or has a different name.\n";
}

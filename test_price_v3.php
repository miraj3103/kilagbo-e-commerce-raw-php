<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=kilagboc_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $stmt = $pdo->prepare("INSERT INTO seller_products (seller_id, name, slug, description, seller_price, status) VALUES (1, 'Test Price Product', 'test-price-product-" . time() . "', 'Test description', 16.25, 'pending')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT seller_price FROM seller_products WHERE id = ?");
    $stmt->execute([$id]);
    $price = $stmt->fetchColumn();
    
    echo "ID: $id, PRICE: $price\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

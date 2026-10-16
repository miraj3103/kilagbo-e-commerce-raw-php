<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=kilagboc_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Insert a test product with fractional price
    $stmt = $pdo->prepare("INSERT INTO seller_products (seller_id, name, slug, description, seller_price, status) VALUES (1, 'Test Price Product', 'test-price-product-" . time() . "', 'Test description', 16.25, 'pending')");
    $stmt->execute();
    $id = $pdo->lastInsertId();
    
    echo "Inserted product ID: $id with price 16.25\n";
    
    // Retrieve it
    $stmt = $pdo->prepare("SELECT seller_price FROM seller_products WHERE id = ?");
    $stmt->execute([$id]);
    $price = $stmt->fetchColumn();
    
    echo "Retrieved price: $price\n";
    
    if ($price == 16.25) {
        echo "SUCCESS: Database supports and preserves fractional prices.\n";
    } else {
        echo "FAILURE: Database rounded the price to $price.\n";
    }
    
    // Clean up
    $pdo->prepare("DELETE FROM seller_products WHERE id = ?")->execute([$id]);
    echo "Cleaned up test product.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

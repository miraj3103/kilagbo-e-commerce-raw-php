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
    
    // Check affiliate_products columns
    $stmt = $pdo->query("DESCRIBE affiliate_products");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in affiliate_products: " . implode(", ", $columns) . "\n\n";
    
    // Check affiliate_stores columns
    $stmt = $pdo->query("DESCRIBE affiliate_stores");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in affiliate_stores: " . implode(", ", $columns) . "\n\n";

    // Check resell_products if relevant (since user mentioned wholesale logic)
    // Maybe affiliate products are just resell_products? Or linked to them?
    $stmt = $pdo->query("SHOW TABLES LIKE 'resell_products'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("DESCRIBE resell_products");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Columns in resell_products: " . implode(", ", $columns) . "\n\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

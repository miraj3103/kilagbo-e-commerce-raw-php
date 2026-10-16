<?php
require_once 'config/config.php';
try {
    $stmt = $pdo->query("DESCRIBE affiliate_products");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in affiliate_products: " . implode(", ", $columns) . "\n";
    
    // Also check if there is a separate wholesale configuration or if it's just one table
    // User mentioned wholesale store vs retail store.
    // Let's check affiliate_stores table to see if it has a type.
    $stmt = $pdo->query("DESCRIBE affiliate_stores");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in affiliate_stores: " . implode(", ", $columns) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
